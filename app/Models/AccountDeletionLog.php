<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountDeletionLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'hashed_user_id',
        'reason',
        'deleted_at',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }

    public static function logDeletion(int $userId, string $reason = 'user_request'): self
    {
        return static::create([
            'hashed_user_id' => hash('sha256', (string) $userId),
            'reason' => $reason,
            'deleted_at' => now(),
        ]);
    }
}
