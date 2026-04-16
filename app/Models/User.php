<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

/**
 * @property int $jwt_version
 */
class User extends Authenticatable implements JWTSubject
{
    use HasRoles;
    use CrudTrait;
    use SoftDeletes;
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'jwt_version',
        'privacy_accepted_at',
        'scheduled_hard_delete_at',
        'is_active',
        'ai_daily_limit_override',
        'plan',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'privacy_accepted_at' => 'datetime',
            'scheduled_hard_delete_at' => 'datetime',
            'jwt_version' => 'integer',
            'is_active' => 'boolean',
            'ai_daily_limit_override' => 'integer',
        ];
    }

    #[\Override]
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    #[\Override]
    public function getJWTCustomClaims(): array
    {
        return [
            'jwt_version' => $this->jwt_version,
        ];
    }

    public function incrementJwtVersion(): void
    {
        $this->increment('jwt_version');
    }

    public function shoppingLists(): HasMany
    {
        return $this->hasMany(ShoppingList::class);
    }

    public function productoHistorial(): HasMany
    {
        return $this->hasMany(ProductoHistorial::class);
    }

    public function weeklySummaries(): HasMany
    {
        return $this->hasMany(WeeklySummary::class);
    }

    public function collaboratedLists(): BelongsToMany
    {
        return $this->belongsToMany(ShoppingList::class, 'list_collaborators')
            ->withPivot('mode', 'share_token_id')
            ->withTimestamps();
    }

    public function webauthnCredentials(): HasMany
    {
        return $this->hasMany(WebauthnCredential::class);
    }
}
