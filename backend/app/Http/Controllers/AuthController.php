<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Organization;
use App\Models\PasswordResetOtp; // تأكد أن الموديل موجود لديك
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** * 1. دالة إنشاء حساب جديد (تم دمجها لإنشاء المؤسسة والمستخدم معاً)
     */
    public function register(Request $request)
    {
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

        $login = $validated['login'];
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

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

        $identifier = $validated['identifier'];
        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $user  = User::where($field, $identifier)->first();

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

        return response()->json([
            'message' => 'تم إرسال رمز التحقق.',
            'otp'     => $otp, // يظهر في الرد لتسهيل الاختبار حالياً
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

        $rec = PasswordResetOtp::where('identifier', $v['identifier'])
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

        $rec = PasswordResetOtp::where('identifier', $v['identifier'])
                               ->where('otp', $v['otp'])->latest()->first();

        if (! $rec || ! $rec->isValid()) {
            return response()->json(['message' => 'رمز التحقق غير صحيح أو منتهي الصلاحية.'], 400);
        }

        $field = filter_var($v['identifier'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $user  = User::where($field, $v['identifier'])->first();

        if($user) {
            $user->update(['password' => Hash::make($v['password'])]);
            $user->tokens()->delete(); // إنهاء جميع الجلسات القديمة
            $rec->update(['used' => true]);
        }

        return response()->json(['message' => 'تم تغيير كلمة المرور بنجاح. يرجى تسجيل الدخول.']);
    }
}