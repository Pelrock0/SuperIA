<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminWaitlistNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $applicantName,
        public readonly string $applicantEmail,
        public readonly int $position,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Superlistia — Nuevo registro en lista de espera',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-waitlist-notification',
            with: [
                'applicantName' => $this->applicantName,
                'applicantEmail' => $this->applicantEmail,
                'position' => $this->position,
            ],
        );
    }
}
