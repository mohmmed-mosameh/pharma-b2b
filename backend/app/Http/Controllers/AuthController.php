<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Organization;
use App\Models\PasswordResetOtp; // تأكد أن الموديل موجود لديك
use App\Services\BrevoMailService;
use App\Services\SmsService;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    /**
     * يحدّد نوع المعرّف (إيميل أو هاتف) ويطبّعه، كي يتطابق دائمًا مع القيمة
     * المخزّنة بغضّ النظر عن الصيغة التي كتبها المستخدم بكل مرة.
     *
     * @return array{0: string, 1: string} [$field, $normalizedValue]
     */
    private function resolveIdentifier(string $raw): array
    {
        if (filter_var($raw, FILTER_VALIDATE_EMAIL)) {
            return ['email', strtolower(trim($raw))];
        }

        return ['phone', PhoneNumber::normalize($raw)];
    }

    /** * 1. دالة إنشاء حساب جديد (تم دمجها لإنشاء المؤسسة والمستخدم معاً)
     */
    public function register(Request $request)
    {
        // نطبّع رقم الهاتف قبل التحقق كي يُفحص التكرار (unique) على نفس الصيغة
        // المخزّنة فعليًا، بصرف النظر عن شكل الرقم الذي كتبه المستخدم
        if ($request->filled('phone')) {
            $request->merge(['phone' => PhoneNumber::normalize($request->input('phone'))]);
        }

        // التحقق المدمج (من الملف الأصلي)
        $validated = $request->validate([
            'organization_name'    => 'required|string|max:255',
            'organization_address' => 'nullable|string',
            'organization_phone'   => 'nullable|string',
            'name'                 => 'required|string|max:255',
            'email'                => 'required|string|email|max:255|unique:users',
            'phone'                => 'nullable|string|max:20|unique:users',
            'password'             => 'required|string|min:8',
            'role'                 => 'required|in:admin,pharmacy,supplier',
            'device_name'          => 'nullable|string'
        ]);

        // حماية العملية بقاعدة البيانات (لكي لا ينشئ مستخدم بدون مؤسسة إذا حدث خطأ)
        $result = DB::transaction(function () use ($validated) {
            
            // إنشاء المؤسسة (الصيدلية أو المورد)
            $org = Organization::create([
                'name'        => $validated['organization_name'],
                'type'        => $validated['role'] === 'pharmacy' ? 'pharmacy' : 'supplier',
                'address'     => $validated['organization_address'] ?? null,
                'phone'       => $validated['organization_phone'] ?? null,
                'is_verified' => false,
            ]);

            // إنشاء المستخدم وربطه بالمؤسسة وتشفير الباسورد
            $user = User::create([
                'organization_id' => $org->id,
                'name'            => $validated['name'],
                'email'           => $validated['email'],
                'phone'           => $validated['phone'] ?? null,
                'password'        => Hash::make($validated['password']),
                'role'            => $validated['role'],
            ]);

            $token = $user->createToken($validated['device_name'] ?? 'web')->plainTextToken;
            return compact('user', 'token');
        });

        return response()->json([
            'message'    => 'تم إنشاء الحساب بنجاح. حسابك قيد المراجعة.',
            'token'      => $result['token'],
            'token_type' => 'Bearer',
            'user'       => $result['user']->load('organization'),
        ], 201);
    }

    /** * 2. دالة تسجيل الدخول (تقبل الإيميل أو رقم الهاتف)
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'login'       => 'required|string', // يمكن أن يكون إيميل أو رقم جوال
            'password'    => 'required|string',
            'device_name' => 'nullable|string'
        ]);

        [$field, $login] = $this->resolveIdentifier($validated['login']);

        $user = User::where($field, $login)->first();

        // التحقق من الباسورد (من الملف الأصلي)
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'بيانات الدخول غير صحيحة. تأكد من البريد/الرقم أو كلمة المرور.'
            ], 401);
        }

        $device = $validated['device_name'] ?? 'web';
        $user->tokens()->where('name', $device)->delete();
        $token = $user->createToken($device)->plainTextToken;

        return response()->json([
            'message'    => 'تم تسجيل الدخول بنجاح.',
            'token'      => $token,
            'token_type' => 'Bearer',
            'user'       => $user->load('organization'),
        ]);
    }

    /** * 3. تسجيل الخروج من الجهاز الحالي
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'تم تسجيل الخروج بنجاح.']);
    }

    /** * 4. جلب بيانات المستخدم الحالي
     */
    public function me(Request $request)
    {
        return response()->json(['data' => $request->user()->load('organization')]);
    }

    /** * 5. طلب إعادة تعيين كلمة المرور (إرسال OTP)
     */
    public function forgotPassword(Request $request)
    {
        $validated = $request->validate([
            'identifier' => 'required|string',
        ]);

        [$field, $identifier] = $this->resolveIdentifier($validated['identifier']);
        $user = User::where($field, $identifier)->first();

        if (! $user) {
            return response()->json(['message' => 'إذا كان هذا الحساب موجوداً، سيتم إرسال رمز التحقق.']);
        }

        // مسح أي أكواد قديمة
        PasswordResetOtp::where('identifier', $identifier)->delete();

        $otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        PasswordResetOtp::create([
            'identifier' => $identifier,
            'otp'        => $otp,
            'expires_at' => now()->addMinutes(10),
        ]);

        if ($field === 'email') {
            try {
                $html = view('emails.password-reset-otp', ['otp' => $otp])->render();
                (new BrevoMailService())->send($identifier, 'رمز إعادة تعيين كلمة المرور - PharmaLink', $html);
            } catch (\Throwable $e) {
                Log::error('Failed to send password-reset email', ['identifier' => $identifier, 'error' => $e->getMessage()]);

                return response()->json(['message' => 'تعذّر إرسال رمز التحقق إلى بريدك الإلكتروني، حاول لاحقاً.'], 500);
            }

            return response()->json([
                'message' => 'تم إرسال رمز التحقق إلى بريدك الإلكتروني.',
            ]);
        }

        try {
            (new SmsService())->send($identifier, "رمز التحقق الخاص بك في PharmaLink هو: {$otp}");
        } catch (\Throwable $e) {
            Log::error('Failed to send password-reset SMS', ['identifier' => $identifier, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'تعذّر إرسال رمز التحقق عبر الرسائل النصية، حاول لاحقاً.'], 500);
        }

        return response()->json(['message' => 'تم إرسال رمز التحقق إلى رقم هاتفك.']);
    }

    /** * 6. التحقق من الـ OTP
     */
    public function verifyOtp(Request $request)
    {
        $v = $request->validate([
            'identifier' => 'required|string',
            'otp'        => 'required|string|size:4',
        ]);

        [, $identifier] = $this->resolveIdentifier($v['identifier']);

        $rec = PasswordResetOtp::where('identifier', $identifier)
                               ->where('otp', $v['otp'])->latest()->first();

        // افتراض وجود دالة isValid() في موديل PasswordResetOtp
        if (! $rec || ! $rec->isValid()) {
            return response()->json(['message' => 'رمز التحقق غير صحيح أو منتهي الصلاحية.'], 400);
        }

        return response()->json(['message' => 'رمز التحقق صحيح.']);
    }

    /** * 7. تعيين كلمة المرور الجديدة
     */
    public function resetPassword(Request $request)
    {
        $v = $request->validate([
            'identifier' => 'required|string',
            'otp'        => 'required|string|size:4',
            'password'   => 'required|string|min:8|confirmed',
        ]);

        [$field, $identifier] = $this->resolveIdentifier($v['identifier']);

        $rec = PasswordResetOtp::where('identifier', $identifier)
                               ->where('otp', $v['otp'])->latest()->first();

        if (! $rec || ! $rec->isValid()) {
            return response()->json(['message' => 'رمز التحقق غير صحيح أو منتهي الصلاحية.'], 400);
        }

        $user = User::where($field, $identifier)->first();

        if($user) {
            $user->update(['password' => Hash::make($v['password'])]);
            $user->tokens()->delete(); // إنهاء جميع الجلسات القديمة
            $rec->update(['used' => true]);
        }

        return response()->json(['message' => 'تم تغيير كلمة المرور بنجاح. يرجى تسجيل الدخول.']);
    }

    /** * 8. تسجيل الدخول عبر جوجل/فيسبوك
     */
    public function googleRedirect(Request $request)
    {
        return $this->socialRedirect('google', $request);
    }

    public function googleCallback(Request $request)
    {
        return $this->socialCallback('google', $request);
    }

    public function facebookRedirect(Request $request)
    {
        return $this->socialRedirect('facebook', $request);
    }

    public function facebookCallback(Request $request)
    {
        return $this->socialCallback('facebook', $request);
    }

    /**
     * يحوّل المستخدم لصفحة تسجيل الدخول عند المزوّد. الدور (صيدلية/مورد) المختار
     * بصفحة الدخول يُمرَّر عبر state كي نستخدمه لاحقًا فقط لو الحساب جديد كليًا،
     * لأنه المتصفح بينتقل لموقع خارجي والـ localStorage ما بيرجع معنا.
     */
    private function socialRedirect(string $provider, Request $request)
    {
        $role = $request->query('role') === 'supplier' ? 'supplier' : 'pharmacy';

        return Socialite::driver($provider)->stateless()->with(['state' => $role])->redirect();
    }

    private function socialCallback(string $provider, Request $request)
    {
        $frontendUrl = rtrim((string) config('services.frontend_url'), '/');
        $role = $request->query('state') === 'supplier' ? 'supplier' : 'pharmacy';

        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
        } catch (\Throwable $e) {
            Log::error("Social login ($provider) failed", ['error' => $e->getMessage()]);

            return redirect($frontendUrl.'/login.html?social_error=1');
        }

        $user = User::where('email', $socialUser->getEmail())->first();

        if (! $user) {
            $user = DB::transaction(function () use ($socialUser, $provider, $role) {
                $org = Organization::create([
                    'name'        => $socialUser->getName() ?: $socialUser->getEmail(),
                    'type'        => $role === 'pharmacy' ? 'pharmacy' : 'supplier',
                    'is_verified' => false,
                ]);

                return User::create([
                    'organization_id'    => $org->id,
                    'name'               => $socialUser->getName() ?: $socialUser->getEmail(),
                    'email'              => $socialUser->getEmail(),
                    'password'           => Hash::make(Str::random(40)),
                    'role'               => $role,
                    'social_provider'    => $provider,
                    'social_provider_id' => $socialUser->getId(),
                ]);
            });
        } elseif (! $user->social_provider) {
            // حساب موجود بإيميل/كلمة مرور من قبل: نربطه بمزوّد السوشيال دون التأثير على كلمة مروره الحالية
            $user->update([
                'social_provider'    => $provider,
                'social_provider_id' => $socialUser->getId(),
            ]);
        }

        $token = $user->createToken('social-'.$provider)->plainTextToken;

        return redirect($frontendUrl.'/social-callback.html?token='.$token);
    }
}