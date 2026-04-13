<?php

namespace Database\Factories;

use App\Enums\WeeklySummaryStatus;
use App\Models\User;
use App\Models\WeeklySummary;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<WeeklySummary>
 */
class WeeklySummaryFactory extends Factory
{
    protected $model = WeeklySummary::class;

    #[\Override]
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'week_start_date' => Carbon::now('Europe/Madrid')->startOfWeek(Carbon::MONDAY)->toDateString(),
            'status' => WeeklySummaryStatus::Pending,
            'payload_json' => [
                ['nombre' => 'Leche', 'cantidad_tipica' => 1.0, 'unidad_tipica' => 'L', 'categoria' => 'lacteos_huevos', 'reason' => 'Compra semanal habitual'],
                ['nombre' => 'Pan', 'cantidad_tipica' => 1.0, 'unidad_tipica' => 'ud', 'categoria' => 'panaderia', 'reason' => null],
            ],
            'claude_cost_usd' => 0.005,
            'dispatched_at' => null,
            'error_message' => null,
        ];
    }

    public function dispatched(): static
    {
        return $this->state(fn () => [
            'status' => WeeklySummaryStatus::Dispatched,
            'dispatched_at' => now(),
        ]);
    }

    public function failed(string $reason = 'test failure'): static
    {
        return $this->state(fn () => [
            'status' => WeeklySummaryStatus::Failed,
            'error_message' => $reason,
            'payload_json' => null,
        ]);
    }
}
