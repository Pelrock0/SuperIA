<?php

namespace App\Models;

use App\Enums\ShareTokenMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListCollaborator extends Model
{
    protected $fillable = [
        'user_id',
        'shopping_list_id',
        'mode',
        'share_token_id',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'mode' => ShareTokenMode::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shoppingList(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class);
    }

    public function shareToken(): BelongsTo
    {
        return $this->belongsTo(ListShareToken::class, 'share_token_id');
    }
}
