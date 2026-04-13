<?php

namespace App\Models;

use App\Enums\ItemUnit;
use App\Enums\ProductCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListItem extends Model
{
    /** @use HasFactory<\Database\Factories\ListItemFactory> */
    use HasFactory;

    protected $fillable = [
        'shopping_list_id',
        'name',
        'quantity',
        'unit',
        'category',
        'estimated_price',
        'is_purchased',
        'position',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'unit' => ItemUnit::class,
            'category' => ProductCategory::class,
            'quantity' => 'decimal:2',
            'estimated_price' => 'decimal:2',
            'is_purchased' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function shoppingList(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class);
    }
}
