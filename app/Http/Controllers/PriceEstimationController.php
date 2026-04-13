<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmPricesRequest;
use App\Models\ShoppingList;
use App\Services\PriceEstimationService;
use Illuminate\Http\JsonResponse;

class PriceEstimationController extends Controller
{
    public function __construct(
        private PriceEstimationService $service,
    ) {}

    public function estimate(ShoppingList $list): JsonResponse
    {
        $user = auth('api')->user();

        if ($list->user_id !== $user->id) {
            abort(404);
        }

        $result = $this->service->estimateForList($user, $list);

        return response()->json(['data' => $result->toArray()]);
    }

    public function confirmPrices(ConfirmPricesRequest $request, ShoppingList $list): JsonResponse
    {
        $user = auth('api')->user();

        if ($list->user_id !== $user->id) {
            abort(404);
        }

        $total = $request->validated('total');
        $itemPrices = $request->validated('items') ?? [];

        if (! empty($itemPrices)) {
            $updated = $this->service->recordItemPrices($user, $list, $itemPrices);
        } else {
            $updated = 0;
        }

        if ($total !== null) {
            $this->service->recordTotalPrice($user, $list, (float) $total);
        }

        return response()->json([
            'data' => ['updated_count' => $updated],
        ]);
    }
}
