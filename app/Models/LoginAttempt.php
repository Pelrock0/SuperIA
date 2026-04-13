<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'email',
        'ip_address',
        'attempted_at',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'attempted_at' => 'datetime',
        ];
    }

    public static function recentFailedCount(string $email, int $minutes = 15): int
    {
        return static::where('email', $email)
            ->where('attempted_at', '>=', now()->subMinutes($minutes))
            ->count();
    }

    public static function clearForEmail(string $email): void
    {
        static::where('email', $email)->delete();
    }

    public static function record(string $email, string $ipAddress): self
    {
        return static::create([
            'email' => $email,
            'ip_address' => $ipAddress,
            'attempted_at' => now(),
        ]);
    }
}
