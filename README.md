# PharmaLink (pharma-b2b)

منصة B2B لربط الصيدليات بموردي الأدوية، تدعم دورة كاملة للمناقصات (RFQ): إنشاء مناقصة، استقبال عروض الأسعار، فتح المظاريف، الترسية، وتقييم الموردين.

## بنية المشروع

```
pharma-b2b/
├── backend/    Laravel 12 API (Sanctum, MySQL)
└── frontend/   واجهة ثابتة HTML/CSS/JS (Bootstrap 5) تتصل بالـ backend عبر REST API
```

المشروعان منفصلان ويعملان كل واحد على بورت خاص به، ويتواصلان عبر HTTP:

- **Backend**: `http://localhost:8000/api/...`
- **Frontend**: يُفتح مباشرة كملفات ثابتة (أو عبر أي static server)، ويتصل بالـ backend من خلال `frontend/js/api.js` (المتغير `API_URL`).

## تشغيل الـ Backend

```bash
cd backend
composer install
cp .env.example .env   # إذا لم يكن موجود
php artisan key:generate
php artisan migrate
php artisan serve
```

يعمل على `http://localhost:8000`.

## تشغيل الـ Frontend

أي static server يكفي، مثلاً:

```bash
cd frontend
php -S localhost:5500
```

ثم افتح `http://localhost:5500/index.html`.

> ملاحظة: صفحات تسجيل الدخول/التسجيل/نسيان كلمة المرور (`login.html`, `forgot-password.html`, `otp-verify.html`, `new-password.html`) مربوطة فعليًا بالـ API. باقي الشاشات (لوحة التحكم، المناقصات، العروض...) حاليًا واجهات ثابتة ولم تُربط بعد بالـ backend.

## المصادقة

المصادقة عبر Laravel Sanctum بنظام Bearer Token (وليس جلسات/كوكيز)، لذلك لا تأثير لاختلاف المنفذ (port) بين الفرونت والباك أثناء التطوير المحلي.
