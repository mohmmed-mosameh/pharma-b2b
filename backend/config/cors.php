<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // مقصورة على دومين الفرونت الفعلي (نفس متغيّر FRONTEND_URL المستخدم
    // أصلًا لروابط تحويل OAuth) + عناوين التطوير المحلي فقط — لا فتح عام
    'allowed_origins' => array_filter([
        env('FRONTEND_URL', 'https://pharma-b2b-two.vercel.app'),
        'http://localhost:5500',
        'http://127.0.0.1:5500',
        'http://localhost:3000',
    ]),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // المصادقة تتم عبر Bearer token (Sanctum) وليس عبر كوكيز، فلا داعي
    // لكوكيز عابرة للنطاقات هنا
    'supports_credentials' => false,

];
