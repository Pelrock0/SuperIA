<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BudgetCapExceededAlert extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly float $currentSpendUsd,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Superia AI — Budget cap exceeded',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ai-budget-cap-exceeded',
            with: [
                'currentSpendUsd' => $this->currentSpendUsd,
                'limitUsd' => (float) config('ai.budget_cap_monthly_usd'),
            ],
        );
    }
}
