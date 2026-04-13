<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListCollaboratorSession extends Model
{
    /** @use HasFactory<\Database\Factories\ListCollaboratorSessionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'list_share_token_id',
        'session_uuid',
        'last_heartbeat_at',
        'created_at',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'last_heartbeat_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function shareToken(): BelongsTo
    {
        return $this->belongsTo(ListShareToken::class, 'list_share_token_id');
    }
}
