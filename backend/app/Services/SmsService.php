<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use Twilio\Rest\Client;

class SmsService
{
    private Client $client;
    private string $from;

    public function __construct()
    {
        $sid   = config('services.twilio.sid');
        $token = config('services.twilio.token');
        $this->from = (string) config('services.twilio.from');

        if (! $sid || ! $token || ! $this->from) {
            throw new RuntimeException('Twilio غير مهيّأ: تأكد من ضبط TWILIO_SID و TWILIO_AUTH_TOKEN و TWILIO_FROM_NUMBER.');
        }

        $this->client = new Client($sid, $token);
    }

    /**
     * يرسل رسالة نصية عبر Twilio. $to يجب أن تكون بصيغة E.164 (مثال: +970599123456).
     */
    public function send(string $to, string $body): void
    {
        $this->client->messages->create($to, [
            'from' => $this->from,
            'body' => $body,
        ]);
    }
}
