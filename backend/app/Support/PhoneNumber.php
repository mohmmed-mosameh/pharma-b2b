<?php

declare(strict_types=1);

namespace App\Support;

class PhoneNumber
{
    /**
     * يتحقق من طول الرقم قبل أي تطبيع: صيغة محلية (0 ثم رقم غير صفر) لا
     * تتجاوز 10 أرقام إجمالًا، وصيغة دولية (00 أو +) لا يتجاوز ما بعدها
     * 12 رقمًا. يُستدعى على القيمة الخام كما كتبها المستخدم، قبل normalize()
     * التي توحّد كل الصيغ إلى +970... وتُفقِد التمييز بينها.
     */
    public static function isValid(string $raw): bool
    {
        $cleaned = preg_replace('/[\s\-\(\)]+/', '', $raw) ?? '';

        return (bool) preg_match('/^(0[1-9]\d{0,8}|(00|\+)\d{1,12}|[1-9]\d{0,8})$/', $cleaned);
    }

    /**
     * يطبّع رقم هاتف فلسطيني إلى صيغة E.164 (+970XXXXXXXXX) بصرف النظر عن
     * الصيغة التي أدخلها المستخدم (بصفر بادئ محلي، برمز الدولة، بمسافات/شرطات).
     * مطلوب لكي يتطابق الرقم المخزّن وقت التسجيل مع ما يُدخله المستخدم لاحقًا
     * (تسجيل الدخول، استعادة كلمة المرور)، ولأن Twilio يتطلب E.164 عند الإرسال.
     */
    public static function normalize(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '970')) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return '+970'.$digits;
    }
}
