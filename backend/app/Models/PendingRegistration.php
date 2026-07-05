<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingRegistration extends Model
{
    protected $fillable = [
        'email',
        'payload',
        'otp',
        'expires_at',
    ];

    protected $casts = [
        'payload'    => 'array',
        'expires_at' => 'datetime',
    ];

    public function isValid(): bool
    {
        return $this->expires_at->isFuture();
    }
}
