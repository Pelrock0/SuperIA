<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSilencedProduct extends Model
{
    /** @use HasFactory<\Database\Factories\UserSilencedProductFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'producto_nombre',
        'silenced_at',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'silenced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
