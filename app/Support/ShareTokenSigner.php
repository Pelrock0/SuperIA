<?php

namespace App\Support;

use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

class ShareTokenSigner
{
    public static function sign(string $tokenId, int $listId, string $mode): string
    {
        $payload = $tokenId.'|'.$listId.'|'.$mode;
        $rawKey = self::rawKey();

        $digest = hash_hmac('sha256', $payload, $rawKey, true);

        return rtrim(strtr(base64_encode($digest), '+/', '-_'), '=');
    }

    public static function urlToken(string $tokenId, int $listId, string $mode): string
    {
        return $tokenId.'.'.self::sign($tokenId, $listId, $mode);
    }

    /**
     * @return array{token_id: string, signature: string}|null
     */
    public static function parse(string $raw): ?array
    {
        if (! str_contains($raw, '.')) {
            return null;
        }

        [$tokenId, $signature] = explode('.', $raw, 2);

        if ($tokenId === '' || $signature === '') {
            return null;
        }

        return ['token_id' => $tokenId, 'signature' => $signature];
    }

    public static function verify(string $signature, string $tokenId, int $listId, string $mode): bool
    {
        $expected = self::sign($tokenId, $listId, $mode);

        return hash_equals($expected, $signature);
    }

    private static function rawKey(): string
    {
        $key = Config::get('app.key');

        if (! is_string($key) || $key === '') {
            throw new InvalidArgumentException('APP_KEY is not configured.');
        }

        if (str_starts_with($key, 'base64:')) {
            return base64_decode(substr($key, 7));
        }

        return $key;
    }
}
