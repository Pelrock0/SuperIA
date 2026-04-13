<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Services\ProductHistoryCleanupService;
use App\Services\ProductHistoryWeightingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function show(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth('api')->user();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'weekly_summary_email_opted_in' => (bool) $user->weekly_summary_email_opted_in,
                'created_at' => $user->created_at,
            ],
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth('api')->user();
        $user->update($request->validated());

        return response()->json([
            'data' => [
                'message' => 'Perfil actualizado correctamente.',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ],
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth('api')->user();

        if (!Hash::check($request->validated('current_password'), $user->password)) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_PASSWORD',
                    'message' => 'La contraseña actual es incorrecta.',
                ],
            ], 422);
        }

        $user->update(['password' => $request->validated('password')]);
        $user->incrementJwtVersion();

        return response()->json([
            'data' => ['message' => 'Contrasena actualizada correctamente.'],
        ]);
    }

    public function history(ProductHistoryWeightingService $weighting): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth('api')->user();
        $paginator = $weighting->rankedListPaginated($user, 20);

        /**
         * @var list<\stdClass> $rows
         * @psalm-suppress UndefinedDocblockClass
         */
        $rows = $paginator->items();
        $items = collect($rows)->map(fn (\stdClass $row) => [
            'producto_nombre' => $row->producto_nombre,
            'total_count' => (int) $row->total_count,
            'last_purchased_at' => $row->last_purchased_at,
            'typical_category' => $row->typical_category,
            'typical_unit' => $row->typical_unit,
            'typical_quantity' => $row->typical_quantity !== null ? (float) $row->typical_quantity : null,
            'weighted_score' => round((float) $row->weighted_score, 2),
        ])->values();

        return response()->json([
            'data' => [
                'items' => $items,
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
        ]);
    }

    public function clearHistory(ProductHistoryCleanupService $cleanup): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth('api')->user();
        $deleted = $cleanup->clearAll($user);

        return response()->json([
            'data' => ['deleted' => $deleted, 'message' => 'Historial eliminado.'],
        ]);
    }

    public function forgetProduct(string $producto, ProductHistoryCleanupService $cleanup): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth('api')->user();
        $decoded = urldecode($producto);

        if (trim($decoded) === '') {
            abort(422, 'Nombre de producto requerido.');
        }

        $deleted = $cleanup->forget($user, $decoded);

        return response()->json([
            'data' => ['deleted' => $deleted, 'message' => 'Producto olvidado.'],
        ]);
    }
}
