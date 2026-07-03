<?php

use Illuminate\Support\Facades\Route;

// 1. المسار الرئيسي: رسالة ترحيبية بسيطة تتأكد منها أن السيرفر يعمل
Route::get('/', function () {
    return response()->json([
        'message' => 'Pharma B2B API is running successfully.'
    ]);
});

// 2. مسار تسجيل الدخول الوهمي (لحل مشكلة Route [login] not defined)
Route::get('/login', function () {
    return response()->json([
        'message' => 'Unauthenticated. Please provide a valid Bearer Token.'
    ], 401);
})->name('login'); // <-- السر هنا في تسمية المسار