<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\RfqController;
use App\Http\Controllers\SupplierRatingController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// ─── مسارات التوثيق (Auth) ───────────────────────────────────────────────────
// وضعناها داخل مجموعة 'auth' للترتيب، الرابط سيكون مثلاً: /api/auth/login
Route::prefix('auth')->group(function () {
    
    // مسارات لا تحتاج تسجيل دخول (للزوار) — محدودة بمعدل صارم (5/دقيقة لكل IP)
    // لأنها الهدف المعتاد لهجمات تخمين OTP/كلمة المرور أو إغراق الإيميل
    Route::middleware('throttle:auth')->group(function () {
        Route::post('register',                [AuthController::class, 'register']);
        Route::post('verify-registration',     [AuthController::class, 'verifyRegistration']);
        Route::post('resend-registration-otp', [AuthController::class, 'resendRegistrationOtp']);
        Route::post('login',           [AuthController::class, 'login']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('verify-otp',      [AuthController::class, 'verifyOtp']);
        Route::post('reset-password',  [AuthController::class, 'resetPassword']);
    });

    // تسجيل الدخول عبر جوجل/فيسبوك (تحويل المتصفح كاملاً، وليس عبر fetch)
    Route::get('google/redirect',   [AuthController::class, 'googleRedirect']);
    Route::get('google/callback',   [AuthController::class, 'googleCallback']);
    Route::get('facebook/redirect', [AuthController::class, 'facebookRedirect']);
    Route::get('facebook/callback', [AuthController::class, 'facebookCallback']);

    // مسارات توثيق تحتاج لتسجيل دخول (Token)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me',          [AuthController::class, 'me']);
        Route::post('logout',     [AuthController::class, 'logout']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);
    });
});

// ─── المسارات المحمية الأساسية للمشروع ─────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Products
    Route::apiResource('products', ProductController::class);

    // Cart
    Route::get('cart',                     [CartController::class, 'index']);
    Route::post('cart/items',              [CartController::class, 'store']);
    Route::delete('cart/items/{cartItem}', [CartController::class, 'destroy']);

    // Orders
    Route::get('orders',                  [OrderController::class, 'index']);
    Route::get('orders/{order}',          [OrderController::class, 'show']);
    Route::post('orders/checkout',        [OrderController::class, 'store']);
    Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus']);

    // ─── RFQ Engine (المناقصات وعروض الأسعار) ─────────────────────
    Route::get('rfqs',                              [RfqController::class, 'index']);
    Route::post('rfqs',                             [RfqController::class, 'store']);
    Route::get('rfqs/{rfq}',                        [RfqController::class, 'show']);
    
    // تحميل تقرير الـ PDF للمناقصة
    Route::get('rfqs/{rfq}/report',                 [RfqController::class, 'downloadReport']);
    
    // شاشة المظاريف: عرض العروض
    Route::get('rfqs/{rfq}/quotes',                 [RfqController::class, 'openQuotes']);
    
    // فتح مظروف عرض واحد بعينه
    Route::post('rfqs/{rfq}/quotes/{quote}/open',   [RfqController::class, 'openSingleQuote']);
    
    // الترسية
    Route::post('rfqs/{rfq}/award',                 [RfqController::class, 'award']);

    // ─── Quotes ───────────────────────────────────────────────────
    Route::get('quotes',         [QuoteController::class, 'index']);
    Route::get('quotes/{quote}', [QuoteController::class, 'show']);
    Route::post('rfqs/{rfq}/quotes', [QuoteController::class, 'store']);

    // ─── Supplier Ratings (تقييم الموردين) ───────────────────
    // الصيدلية تقيّم المورد بعد اكتمال التوريد
    Route::post('purchase-orders/{purchaseOrder}/rating', [SupplierRatingController::class, 'store']);
    
    // عرض تقييمات مورد معين
    Route::get('suppliers/{organization}/ratings',        [SupplierRatingController::class, 'index']);
});