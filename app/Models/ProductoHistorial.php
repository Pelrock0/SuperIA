<?php

namespace App\Models;

use App\Enums\ItemUnit;
use App\Enums\ProductCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoHistorial extends Model
{
    public $timestamps = false;

    protected $table = 'producto_historial';

    protected $fillable = [
        'user_id',
        'producto_nombre',
        'categoria',
        'cantidad',
        'unidad',
        'precio_real',
        'fecha_compra',
        'lista_id',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'categoria' => ProductCategory::class,
            'unidad' => ItemUnit::class,
            'cantidad' => 'decimal:2',
            'precio_real' => 'decimal:2',
            'fecha_compra' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lista(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class, 'lista_id');
    }

    public static function recordPurchase(ListItem $item, int $userId, int $listaId): self
    {
        return static::create([
            'user_id' => $userId,
            'producto_nombre' => $item->name,
            'categoria' => $item->category,
            'cantidad' => $item->quantity,
            'unidad' => $item->unit,
            'precio_real' => null,
            'fecha_compra' => now(),
            'lista_id' => $listaId,
        ]);
    }
}
