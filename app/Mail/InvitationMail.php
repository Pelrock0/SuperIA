<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class InvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $userName,
        public readonly string $token,
        public readonly Carbon $expiresAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Superia — Tu invitación está lista',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invitation',
        );
    }
}
