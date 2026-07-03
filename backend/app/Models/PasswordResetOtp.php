<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordResetOtp extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'identifier',
        'otp',
        'expires_at',
        'used',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'used' => 'boolean',
    ];

    /**
     * الرمز صالح فقط إذا لم يُستخدم بعد ولم تنتهِ صلاحيته.
     */
    public function isValid(): bool
    {
        return ! $this->used && $this->expires_at->isFuture();
    }
}
