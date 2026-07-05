<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class BrevoMailService
{
    private string $apiKey;
    private string $fromEmail;
    private string $fromName;

    public function __construct()
    {
        $this->apiKey    = (string) config('services.brevo.key');
        $this->fromEmail = (string) config('services.brevo.from_email');
        $this->fromName  = (string) config('services.brevo.from_name');

        if (! $this->apiKey || ! $this->fromEmail) {
            throw new RuntimeException('Brevo غير مهيّأ: تأكد من ضبط BREVO_API_KEY و BREVO_FROM_EMAIL.');
        }
    }

    /**
     * يرسل بريدًا عبر Brevo HTTP API (بدون SMTP) — مناسب للاستضافات التي تحظر منافذ SMTP.
     */
    public function send(string $toEmail, string $subject, string $htmlContent): void
    {
        $response = Http::withHeaders([
            'api-key'      => $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ])->post('https://api.brevo.com/v3/smtp/email', [
            'sender'      => ['email' => $this->fromEmail, 'name' => $this->fromName],
            'to'          => [['email' => $toEmail]],
            'subject'     => $subject,
            'htmlContent' => $htmlContent,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Brevo API error ('.$response->status().'): '.$response->body());
        }
    }
}
