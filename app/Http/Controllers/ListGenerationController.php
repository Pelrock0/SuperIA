<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmExistingListRequest;
use App\Http\Requests\ConfirmNewListRequest;
use App\Http\Requests\GenerateListRequest;
use App\Models\ShoppingList;
use App\Services\ListGenerationService;
use App\Support\Ai\Exceptions\ClaudeException;
use Illuminate\Http\JsonResponse;
use OverflowException;

class ListGenerationController extends Controller
{
    public function __construct(
        private ListGenerationService $service,
    ) {}

    public function generate(GenerateListRequest $request): JsonResponse
    {
        $user = auth('api')->user();

        try {
            $result = $this->service->generate(
                $user,
                $request->validated('description'),
                $request->validated('people') ?? (int) config('ai.generation.default_people', 2),
            );
        } catch (ClaudeException $e) {
            return response()->json([
                'error' => ['code' => 'GENERATION_FAILED', 'message' => 'No se pudo generar la lista. Intentalo de nuevo.'],
            ], 500);
        } catch (\RuntimeException $e) {
            $code = $e->getMessage();
            $status = match ($code) {
                'GENERATION_LIMIT' => 429,
                'AI_LIMIT' => 429,
                'BUDGET_CAPPED' => 429,
                'CIRCUIT_OPEN' => 429,
                default => 500,
            };
            $message = match ($code) {
                'GENERATION_LIMIT' => 'Has alcanzado tu limite de 5 generaciones diarias.',
                'AI_LIMIT' => 'Has alcanzado tu limite diario de operaciones IA.',
                'BUDGET_CAPPED' => 'El servicio de IA no esta disponible temporalmente.',
                'CIRCUIT_OPEN' => 'El servicio de IA no esta disponible temporalmente.',
                default => 'Error inesperado.',
            };
            return response()->json([
                'error' => ['code' => $code, 'message' => $message],
            ], $status);
        }

        return response()->json(['data' => $result]);
    }

    public function confirmNew(ConfirmNewListRequest $request): JsonResponse
    {
        $user = auth('api')->user();

        try {
            $list = $this->service->confirmAsNewList(
                $user,
                $request->validated('items'),
                $request->validated('name'),
            );
        } catch (OverflowException $e) {
            return response()->json([
                'error' => ['code' => 'FREEMIUM_LIMIT', 'message' => $e->getMessage()],
            ], 403);
        }

        return response()->json(['data' => $list], 201);
    }

    public function confirmExisting(ConfirmExistingListRequest $request): JsonResponse
    {
        $user = auth('api')->user();
        $list = ShoppingList::findOrFail($request->validated('list_id'));

        if ($list->user_id !== $user->id) {
            abort(404);
        }

        $list = $this->service->confirmAddToExisting(
            $user,
            $list,
            $request->validated('items'),
        );

        return response()->json(['data' => $list]);
    }
}
