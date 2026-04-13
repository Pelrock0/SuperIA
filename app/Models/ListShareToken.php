<?php

namespace App\Models;

use App\Enums\ShareTokenMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property \Illuminate\Support\Carbon|null $revoked_at
 */
class ListShareToken extends Model
{
    /** @use HasFactory<\Database\Factories\ListShareTokenFactory> */
    use HasFactory;

    protected $fillable = [
        'shopping_list_id',
        'token_id',
        'mode',
        'revoked_at',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'mode' => ShareTokenMode::class,
            'revoked_at' => 'datetime',
        ];
    }

    public function shoppingList(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ListCollaboratorSession::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
