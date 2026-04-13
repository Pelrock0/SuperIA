<?php

namespace App\Services;

use App\Enums\ShareTokenMode;
use App\Models\ListShareToken;
use App\Models\ShoppingList;
use App\Support\ShareTokenContext;
use App\Support\ShareTokenSigner;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShareTokenService
{
    public function generate(ShoppingList $list, ShareTokenMode $mode): ListShareToken
    {
        return DB::transaction(function () use ($list, $mode) {
            $token = ListShareToken::create([
                'shopping_list_id' => $list->id,
                'token_id' => (string) Str::uuid(),
                'mode' => $mode,
                'revoked_at' => null,
            ]);

            $this->syncIsShared($list);

            return $token->refresh();
        });
    }

    public function revoke(ListShareToken $token): ListShareToken
    {
        return DB::transaction(function () use ($token) {
            if ($token->revoked_at === null) {
                $token->update(['revoked_at' => now()]);
            }

            $token->sessions()->delete();

            $this->syncIsShared($token->shoppingList);

            return $token->refresh();
        });
    }

    public function activeTokensForList(ShoppingList $list): Collection
    {
        return $list->shareTokens()
            ->whereNull('revoked_at')
            ->orderBy('id')
            ->get();
    }

    public function resolveFromUrlParam(string $raw): ?ShareTokenContext
    {
        $parsed = ShareTokenSigner::parse($raw);

        if ($parsed === null) {
            return null;
        }

        $token = ListShareToken::where('token_id', $parsed['token_id'])->first();

        if ($token === null || $token->isRevoked()) {
            return null;
        }

        $list = $token->shoppingList;

        if ($list === null) {
            return null;
        }

        $expected = ShareTokenSigner::sign(
            $token->token_id,
            $list->id,
            $token->mode->value,
        );

        if (! hash_equals($expected, $parsed['signature'])) {
            return null;
        }

        return new ShareTokenContext($token, $list, $token->mode);
    }

    public function urlFor(ListShareToken $token): string
    {
        $tokenParam = ShareTokenSigner::urlToken(
            $token->token_id,
            $token->shopping_list_id,
            $token->mode->value,
        );

        $base = rtrim((string) Config::get('app.url'), '/');

        return $base.'/shared/'.$tokenParam;
    }

    private function syncIsShared(ShoppingList $list): void
    {
        $hasActive = $list->shareTokens()->whereNull('revoked_at')->exists();

        if ($list->is_shared !== $hasActive) {
            $list->update(['is_shared' => $hasActive]);
        }
    }
}
