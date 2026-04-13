<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiDismissedSuggestion extends Model
{
    /** @use HasFactory<\Database\Factories\AiDismissedSuggestionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'ai_dismissed_suggestions';

    protected $fillable = [
        'user_id',
        'producto_nombre',
        'dismissed_until',
        'created_at',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'dismissed_until' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
