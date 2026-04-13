<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $status
 * @property string|null $invitation_token
 * @property \Illuminate\Support\Carbon|null $invitation_expires_at
 */
class WaitlistEntry extends Model
{
    use CrudTrait;
    /** @use HasFactory<\Database\Factories\WaitlistEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'shopping_companion',
        'position',
        'status',
        'invitation_token',
        'invitation_sent_at',
        'invitation_expires_at',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'invitation_sent_at' => 'datetime',
            'invitation_expires_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isInvited(): bool
    {
        return $this->status === 'invited';
    }

    public function hasValidInvitation(): bool
    {
        return $this->isInvited()
            && $this->invitation_token !== null
            && $this->invitation_expires_at !== null
            && $this->invitation_expires_at->isFuture();
    }
}
