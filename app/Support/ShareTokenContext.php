<?php

namespace App\Support;

use App\Enums\ShareTokenMode;
use App\Models\ListShareToken;
use App\Models\ShoppingList;

class ShareTokenContext
{
    public function __construct(
        public readonly ListShareToken $token,
        public readonly ShoppingList $list,
        public readonly ShareTokenMode $mode,
    ) {}

    public function tokenId(): int
    {
        return $this->token->id;
    }

    public function allowsWrite(): bool
    {
        return $this->mode->allowsWrite();
    }
}
