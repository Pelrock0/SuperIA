<?php

namespace App\Enums;

enum AiPlan: string
{
    case Free = 'free';
    case Premium = 'premium';

    public function dailySuggestionQuota(): ?int
    {
        return match ($this) {
            self::Free => (int) config('ai.rate_limits.free.suggestions_per_day'),
            self::Premium => null, // unlimited
        };
    }
}
