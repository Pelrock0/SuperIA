<?php

namespace App\Support\Ai;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CircuitBreaker
{
    public function __construct(
        private string $service = 'claude',
        private ?int $threshold = null,
        private ?int $cooldownSeconds = null,
    ) {
        $this->threshold ??= (int) config('ai.circuit_breaker.failure_threshold', 3);
        $this->cooldownSeconds ??= (int) config('ai.circuit_breaker.cool_down_seconds', 60);
    }

    public function allow(): bool
    {
        $openUntil = Cache::get($this->key('open_until'));

        if ($openUntil === null) {
            return true;
        }

        if (now()->greaterThanOrEqualTo($openUntil)) {
            $this->reset();
            return true;
        }

        return false;
    }

    public function recordSuccess(): void
    {
        $this->reset();
    }

    public function recordFailure(): void
    {
        $count = (int) Cache::increment($this->key('failures'));

        if ($count === 1) {
            Cache::put($this->key('failures'), $count, now()->addSeconds($this->cooldownSeconds * 2));
        }

        if ($count >= $this->threshold) {
            $until = now()->addSeconds($this->cooldownSeconds);
            Cache::put($this->key('open_until'), $until, $until);
            Log::warning('AI circuit breaker opened', [
                'service' => $this->service,
                'failures' => $count,
                'cool_down_seconds' => $this->cooldownSeconds,
            ]);
        }
    }

    public function reset(): void
    {
        Cache::forget($this->key('failures'));
        Cache::forget($this->key('open_until'));
    }

    private function key(string $suffix): string
    {
        return "ai:cb:{$this->service}:{$suffix}";
    }
}
