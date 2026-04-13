<?php

namespace App\Models;

use App\Enums\WeeklySummaryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property \Illuminate\Support\Carbon $week_start_date
 * @property WeeklySummaryStatus $status
 * @property array<int, array<string, mixed>>|null $payload_json
 * @property float|null $claude_cost_usd
 * @property \Illuminate\Support\Carbon|null $dispatched_at
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class WeeklySummary extends Model
{
    /** @use HasFactory<\Database\Factories\WeeklySummaryFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'week_start_date',
        'status',
        'payload_json',
        'claude_cost_usd',
        'dispatched_at',
        'error_message',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'week_start_date' => 'date',
            'status' => WeeklySummaryStatus::class,
            'payload_json' => 'array',
            'claude_cost_usd' => 'decimal:4',
            'dispatched_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
