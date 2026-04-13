<?php

namespace App\Mail;

use App\Models\User;
use App\Models\WeeklySummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class WeeklySummaryMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly WeeklySummary $summary,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Superia — Tu resumen semanal',
        );
    }

    public function content(): Content
    {
        $ttlDays = (int) config('ai.weekly_summary.unsubscribe_token_ttl_days', 30);

        return new Content(
            view: 'emails.weekly-summary',
            with: [
                'userName' => $this->user->name,
                'products' => $this->summary->payload_json ?? [],
                'weekStart' => $this->summary->week_start_date,
                'unsubscribeUrl' => URL::temporarySignedRoute(
                    'weekly-summary.unsubscribe',
                    now()->addDays($ttlDays),
                    ['user' => $this->user->id],
                ),
                'appUrl' => rtrim((string) config('app.url'), '/').'/app/resumen',
            ],
        );
    }
}
