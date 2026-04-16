<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\TrustPath\EmptyTrustPath;

class WebauthnCredential extends Model
{
    /** @use HasFactory<\Database\Factories\WebauthnCredentialFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'credential_id',
        'public_key',
        'sign_count',
        'transports',
        'aaguid',
        'attestation_type',
        'name',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'transports' => 'array',
            'last_used_at' => 'datetime',
            'sign_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toPublicKeyCredentialSource(): PublicKeyCredentialSource
    {
        return PublicKeyCredentialSource::create(
            publicKeyCredentialId: self::base64UrlDecode($this->credential_id),
            type: 'public-key',
            transports: $this->transports ?? [],
            attestationType: $this->attestation_type,
            trustPath: EmptyTrustPath::create(),
            aaguid: \Symfony\Component\Uid\Uuid::fromString($this->aaguid ?? '00000000-0000-0000-0000-000000000000'),
            credentialPublicKey: self::base64UrlDecode($this->public_key),
            userHandle: self::userHandleFor($this->user_id),
            counter: $this->sign_count,
        );
    }

    public static function fromPublicKeyCredentialSource(
        PublicKeyCredentialSource $source,
        User $user,
        string $name,
    ): self {
        return self::create([
            'user_id' => $user->id,
            'credential_id' => self::base64UrlEncode($source->publicKeyCredentialId),
            'public_key' => self::base64UrlEncode($source->credentialPublicKey),
            'sign_count' => $source->counter,
            'transports' => $source->transports,
            'aaguid' => $source->aaguid->toRfc4122(),
            'attestation_type' => $source->attestationType,
            'name' => $name,
        ]);
    }

    public static function userHandleFor(int $userId): string
    {
        return (string) $userId;
    }

    public static function base64UrlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $encoded): string
    {
        $padded = str_pad($encoded, strlen($encoded) + (4 - strlen($encoded) % 4) % 4, '=', STR_PAD_RIGHT);
        return base64_decode(strtr($padded, '-_', '+/'));
    }
}
