<?php

namespace Database\Factories;

use App\Enums\AiOperation;
use App\Enums\AiUsageStatus;
use App\Models\AiUsageLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiUsageLog>
 */
class AiUsageLogFactory extends Factory
{
    protected $model = AiUsageLog::class;

    #[\Override]
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'operation' => AiOperation::Suggestion,
            'status' => AiUsageStatus::Success,
            'date' => now('Europe/Madrid')->toDateString(),
            'estimated_cost_usd' => 0.01,
            'created_at' => now(),
        ];
    }

    public function budgetCapped(): static
    {
        return $this->state(fn () => ['status' => AiUsageStatus::BudgetCapped]);
    }

    public function error(): static
    {
        return $this->state(fn () => ['status' => AiUsageStatus::Error]);
    }
}
