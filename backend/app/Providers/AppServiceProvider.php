<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // مسارات التوثيق الحساسة (تسجيل الدخول، رموز OTP، استعادة كلمة
        // المرور) عرضة لهجمات تخمين/إغراق لو تُركت بدون حد أقصى — خصوصًا
        // أن رمز الـ OTP 4 أرقام فقط (10,000 احتمال). حدّ صارم بالـ IP.
        RateLimiter::for('auth', function ($request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // اسم المحدّد العام "api" الذي يفعّله throttleApi() بـ bootstrap/app.php
        // على كل مسارات routes/api.php — يجب تعريفه صراحة وإلا ترمي كل
        // الطلبات استثناء "Rate limiter [api] is not defined" (خطأ 500).
        RateLimiter::for('api', function ($request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
