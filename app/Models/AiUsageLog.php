<?php

namespace App\Models;

use App\Enums\AiOperation;
use App\Enums\AiUsageStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    use \Backpack\CRUD\app\Models\Traits\CrudTrait;
    /** @use HasFactory<\Database\Factories\AiUsageLogFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'ai_usage_log';

    protected $fillable = [
        'user_id',
        'operation',
        'status',
        'date',
        'estimated_cost_usd',
        'created_at',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'operation' => AiOperation::class,
            'status' => AiUsageStatus::class,
            'date' => 'date',
            'estimated_cost_usd' => 'decimal:4',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
