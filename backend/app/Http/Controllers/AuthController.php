<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Organization;
use App\Models\PasswordResetOtp; // تأكد أن الموديل موجود لديك
use App\Models\PendingRegistration;
use App\Services\BrevoMailService;
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
        // فحص طول الرقم يجب أن يتم على القيمة الخام قبل التطبيع، لأن normalize()
        // توحّد كل الصيغ إلى +970... فتُفقِد التمييز بين محلي/دولي المطلوب للفحص
        if ($request->filled('phone') && ! PhoneNumber::isValid($request->input('phone'))) {
            throw ValidationException::withMessages([
                'phone' => ['رقم الهاتف غير صالح.'],
            ]);
        }

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
            // "admin" مستثنى عمدًا: هذا مسار تسجيل ذاتي عام غير موثّق،
            // لا يجوز أن يسمح لأي زائر بإنشاء حساب مدير من نفسه
            'role'                 => 'required|in:pharmacy,supplier',
        ]);

        // لا يُنشأ الحساب فعليًا الآن — نخزّن بياناته مؤقتًا وننشئه فقط بعد
        // تأكيد رمز التحقق المُرسَل للإيميل (انظر verifyRegistration)
        $otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        PendingRegistration::where('email', $validated['email'])->delete();

        PendingRegistration::create([
            'email' => $validated['email'],
            'payload' => [
                'organization_name'    => $validated['organization_name'],
                'organization_address' => $validated['organization_address'] ?? null,
                'organization_phone'   => $validated['organization_phone'] ?? null,
                'name'                 => $validated['name'],
                'email'                => $validated['email'],
                'phone'                => $validated['phone'] ?? null,
                'password'             => Hash::make($validated['password']),
                'role'                 => $validated['role'],
            ],
            'otp'        => $otp,
            'expires_at' => now()->addMinutes(10),
        ]);

        if (! $this->sendRegistrationOtpEmail($validated['email'], $otp)) {
            return response()->json(['message' => 'تعذّر إرسال رمز التحقق إلى بريدك الإلكتروني، حاول لاحقاً.'], 500);
        }

        return response()->json([
            'message' => 'تم إرسال رمز التحقق إلى بريدك الإلكتروني، أدخله لإكمال إنشاء الحساب.',
            'email'   => $validated['email'],
        ]);
    }

    /** * 1ب. تأكيد رمز التسجيل وإنشاء الحساب فعليًا
     */
    public function verifyRegistration(Request $request)
    {
        $v = $request->validate([
            'email' => 'required|string|email',
            'otp'   => 'required|string|size:4',
        ]);

        $pending = PendingRegistration::where('email', $v['email'])->latest()->first();

        if (! $pending || $pending->otp !== $v['otp'] || ! $pending->isValid()) {
            return response()->json(['message' => 'رمز التحقق غير صحيح أو منتهي الصلاحية.'], 400);
        }

        $payload = $pending->payload;

        $result = DB::transaction(function () use ($payload) {
            $org = Organization::create([
                'name'        => $payload['organization_name'],
                'type'        => $payload['role'] === 'pharmacy' ? 'pharmacy' : 'supplier',
                'address'     => $payload['organization_address'] ?? null,
                'phone'       => $payload['organization_phone'] ?? null,
                'is_verified' => false,
            ]);

            $user = User::create([
                'organization_id' => $org->id,
                'name'            => $payload['name'],
                'email'           => $payload['email'],
                'phone'           => $payload['phone'] ?? null,
                'password'        => $payload['password'], // مُشفّرة مسبقًا وقت التسجيل
                'role'            => $payload['role'],
            ]);

            $token = $user->createToken('web')->plainTextToken;

            return compact('user', 'token');
        });

        $pending->delete();

        return response()->json([
            'message'    => 'تم إنشاء الحساب بنجاح.',
            'token'      => $result['token'],
            'token_type' => 'Bearer',
            'user'       => $result['user']->load('organization'),
        ], 201);
    }

    /** * 1ج. إعادة إرسال رمز تأكيد التسجيل
     */
    public function resendRegistrationOtp(Request $request)
    {
        $v = $request->validate([
            'email' => 'required|string|email',
        ]);

        $pending = PendingRegistration::where('email', $v['email'])->latest()->first();

        if (! $pending) {
            return response()->json(['message' => 'إذا كان طلب التسجيل هذا موجودًا، تم إعادة إرسال الرمز.']);
        }

        $otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $pending->update(['otp' => $otp, 'expires_at' => now()->addMinutes(10)]);

        if (! $this->sendRegistrationOtpEmail($v['email'], $otp)) {
            return response()->json(['message' => 'تعذّر إرسال رمز التحقق، حاول لاحقاً.'], 500);
        }

        return response()->json(['message' => 'تم إعادة إرسال رمز التحقق.']);
    }

    private function sendRegistrationOtpEmail(string $email, string $otp): bool
    {
        try {
            $html = view('emails.registration-otp', ['otp' => $otp])->render();
            (new BrevoMailService())->send($email, 'رمز تأكيد إنشاء الحساب - PharmaLink', $html);

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to send registration OTP email', ['email' => $email, 'error' => $e->getMessage()]);

            return false;
        }
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
        // استعادة كلمة المرور عبر البريد الإلكتروني فقط (رقم الهاتف أُلغي من هذه الميزة تحديدًا)
        $identifier = strtolower(trim((string) $request->input('identifier')));

        if (! filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['message' => 'الرجاء إدخال بريد إلكتروني صحيح، لا يمكن استعادة كلمة المرور برقم الهاتف.'], 422);
        }

        $user = User::where('email', $identifier)->first();

        if (! $user) {
            return response()->json(['message' => 'لا يوجد حساب مرتبط بهذا البريد الإلكتروني.'], 404);
        }

        // مسح أي أكواد قديمة
        PasswordResetOtp::where('identifier', $identifier)->delete();

        $otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        PasswordResetOtp::create([
            'identifier' => $identifier,
            'otp'        => $otp,
            'expires_at' => now()->addMinutes(10),
        ]);

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