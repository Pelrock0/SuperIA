<?php

namespace App\Services;

use App\Models\ProductoHistorial;
use App\Models\User;

class ProductHistoryCleanupService
{
    public function clearAll(User $user): int
    {
        return ProductoHistorial::where('user_id', $user->id)->delete();
    }

    public function forget(User $user, string $productName): int
    {
        return ProductoHistorial::where('user_id', $user->id)
            ->where('producto_nombre', $productName)
            ->delete();
    }
}
