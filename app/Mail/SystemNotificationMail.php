<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SystemNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{sign_off: string, signature: string, header_eyebrow: string, header_title: string}  $branding
     */
    public function __construct(
        public string $notificationTitle,
        public string $notificationBody,
        public ?string $actionUrl = null,
        public ?string $actionLabel = null,
        public array $branding = [],
        public ?string $heroBackgroundUrl = null,
        public ?string $sealUrl = null,
    ) {
        $defaults = [
            'sign_off' => 'Thanks,',
            'signature' => (string) config('app.name'),
            'header_eyebrow' => 'Legislative Information System',
            'header_title' => 'Sangguniang Panlalawigan',
        ];

        $this->branding = [
            'sign_off' => trim((string) ($branding['sign_off'] ?? $defaults['sign_off'])) ?: $defaults['sign_off'],
            'signature' => trim((string) ($branding['signature'] ?? $defaults['signature'])) ?: $defaults['signature'],
            'header_eyebrow' => trim((string) ($branding['header_eyebrow'] ?? $defaults['header_eyebrow'])) ?: $defaults['header_eyebrow'],
            'header_title' => trim((string) ($branding['header_title'] ?? $defaults['header_title'])) ?: $defaults['header_title'],
        ];

        $this->heroBackgroundUrl = $heroBackgroundUrl ?: url('/images/dashboard-hero-bg.png');
        $this->sealUrl = $sealUrl ?: url('/images/bataan-seal.png');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->notificationTitle,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.system-notification',
        );
    }
}
