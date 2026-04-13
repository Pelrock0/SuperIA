<?php

namespace App\Models;

use App\Enums\ListCategory;
use App\Enums\ListStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property ListStatus $status
 */
class ShoppingList extends Model
{
    /** @use HasFactory<\Database\Factories\ShoppingListFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'emoji',
        'category',
        'status',
        'is_shared',
        'items_total',
        'items_completed',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'status' => ListStatus::class,
            'category' => ListCategory::class,
            'is_shared' => 'boolean',
            'items_total' => 'integer',
            'items_completed' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === ListStatus::Active;
    }

    public function isArchived(): bool
    {
        return $this->status === ListStatus::Archived;
    }

    public function items(): HasMany
    {
        return $this->hasMany(ListItem::class, 'shopping_list_id');
    }

    public function shareTokens(): HasMany
    {
        return $this->hasMany(ListShareToken::class);
    }

    public function activityLog(): HasMany
    {
        return $this->hasMany(ListActivityLog::class);
    }
}
