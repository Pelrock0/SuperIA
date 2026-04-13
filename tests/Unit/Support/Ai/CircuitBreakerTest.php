<?php

namespace Tests\Unit\Support\Ai;

use App\Support\Ai\CircuitBreaker;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_allow_is_true_when_closed(): void
    {
        $cb = new CircuitBreaker('test', 3, 60);

        $this->assertTrue($cb->allow());
    }

    public function test_opens_after_threshold_failures(): void
    {
        $cb = new CircuitBreaker('test', 3, 60);

        $cb->recordFailure();
        $this->assertTrue($cb->allow());

        $cb->recordFailure();
        $this->assertTrue($cb->allow());

        $cb->recordFailure();
        $this->assertFalse($cb->allow());
    }

    public function test_reset_closes_breaker(): void
    {
        $cb = new CircuitBreaker('test', 2, 60);
        $cb->recordFailure();
        $cb->recordFailure();
        $this->assertFalse($cb->allow());

        $cb->reset();

        $this->assertTrue($cb->allow());
    }

    public function test_record_success_closes_breaker(): void
    {
        $cb = new CircuitBreaker('test', 2, 60);
        $cb->recordFailure();
        $cb->recordFailure();
        $this->assertFalse($cb->allow());

        $cb->recordSuccess();

        $this->assertTrue($cb->allow());
    }

    public function test_uses_config_defaults_when_not_provided(): void
    {
        config(['ai.circuit_breaker.failure_threshold' => 2]);
        config(['ai.circuit_breaker.cool_down_seconds' => 30]);

        $cb = new CircuitBreaker('test-config');

        $cb->recordFailure();
        $cb->recordFailure();

        $this->assertFalse($cb->allow());
    }

    public function test_different_services_have_independent_state(): void
    {
        $a = new CircuitBreaker('service-a', 2, 60);
        $b = new CircuitBreaker('service-b', 2, 60);

        $a->recordFailure();
        $a->recordFailure();

        $this->assertFalse($a->allow());
        $this->assertTrue($b->allow());
    }
}
