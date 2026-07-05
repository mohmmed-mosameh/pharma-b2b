<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class SmsService
{
    private string $apiKey;
    private string $senderName;

    public function __construct()
    {
        $this->apiKey     = (string) config('services.brevo.key');
        $this->senderName = (string) config('services.brevo.from_name');

        if (! $this->apiKey) {
            throw new RuntimeException('Brevo غير مهيّأ: تأكد من ضبط BREVO_API_KEY.');
        }
    }

    /**
     * يرسل رسالة نصية عبر Brevo Transactional SMS API. $to يجب أن تكون
     * بصيغة E.164 (مثال: +970599123456).
     */
    public function send(string $to, string $body): void
    {
        $response = Http::withHeaders([
            'api-key'      => $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ])->post('https://api.brevo.com/v3/transactionalSMS/sms', [
            'sender'    => $this->senderName,
            'recipient' => $to,
            'content'   => $body,
            'type'      => 'transactional',
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Brevo SMS API error ('.$response->status().'): '.$response->body());
        }
    }
}
