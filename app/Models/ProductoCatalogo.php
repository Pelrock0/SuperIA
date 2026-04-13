<?php

namespace App\Models;

use App\Enums\ItemUnit;
use App\Enums\ProductCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductoCatalogo extends Model
{
    /** @use HasFactory<\Database\Factories\ProductoCatalogoFactory> */
    use HasFactory;

    protected $table = 'producto_catalogo';

    protected $fillable = [
        'nombre',
        'categoria',
        'unidad_tipica',
        'cantidad_tipica',
        'precio_min',
        'precio_max',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'categoria' => ProductCategory::class,
            'unidad_tipica' => ItemUnit::class,
            'cantidad_tipica' => 'decimal:2',
            'precio_min' => 'decimal:2',
            'precio_max' => 'decimal:2',
        ];
    }
}
