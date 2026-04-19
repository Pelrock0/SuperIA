<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceCache extends Model
{
    protected $table = 'price_cache';

    protected $fillable = [
        'input_name',
        'precio_min',
        'precio_max',
        'expires_at',
    ];

    protected $casts = [
        'precio_min' => 'float',
        'precio_max' => 'float',
        'expires_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
