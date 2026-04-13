<?php

namespace App\Models;

use App\Enums\ActivityAction;
use App\Enums\ActorType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListActivityLog extends Model
{
    /** @use HasFactory<\Database\Factories\ListActivityLogFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'list_activity_log';

    protected $fillable = [
        'shopping_list_id',
        'list_share_token_id',
        'actor_type',
        'action',
        'item_name',
        'created_at',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'actor_type' => ActorType::class,
            'action' => ActivityAction::class,
            'created_at' => 'datetime',
        ];
    }

    public function shoppingList(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class);
    }

    public function shareToken(): BelongsTo
    {
        return $this->belongsTo(ListShareToken::class, 'list_share_token_id');
    }
}
