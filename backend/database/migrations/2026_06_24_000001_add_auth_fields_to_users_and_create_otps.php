<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->unique()->after('email');
            $table->string('social_provider')->nullable()->after('password');
            $table->string('social_provider_id')->nullable()->after('social_provider');
        });

        // جدول OTP لاستعادة كلمة المرور
        Schema::create('password_reset_otps', function (Blueprint $table) {
            $table->id();
            $table->string('identifier'); // email أو phone
            $table->string('otp', 6);
            $table->timestamp('expires_at');
            $table->boolean('used')->default(false);
            $table->timestamps();
            $table->index('identifier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_otps');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'social_provider', 'social_provider_id']);
        });
    }
};
