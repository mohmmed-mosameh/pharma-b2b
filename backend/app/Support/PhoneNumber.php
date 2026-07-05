<?php

declare(strict_types=1);

namespace App\Support;

class PhoneNumber
{
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
