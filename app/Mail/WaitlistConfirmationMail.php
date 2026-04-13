<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WaitlistConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $userName,
        public readonly int $position,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Superia — Estás en la lista de espera',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.waitlist-confirmation',
        );
    }
}
