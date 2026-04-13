<?php

namespace App\Http\Controllers;

use App\Models\WeeklySummary;
use App\Services\WeeklySummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use OverflowException;

class WeeklySummaryController extends Controller
{
    public function __construct(
        private WeeklySummaryService $service,
    ) {}

    public function latest(): JsonResponse
    {
        $user = auth('api')->user();
        $weekStart = $this->service->currentWeekStart();

        $summary = WeeklySummary::query()
            ->where('user_id', $user->id)
            ->whereDate('week_start_date', $weekStart)
            ->first();

        if ($summary === null || $summary->status->value === 'failed') {
            return response()->json([
                'error' => ['code' => 'NO_SUMMARY_THIS_WEEK', 'message' => 'No hay resumen para esta semana.'],
            ], 404);
        }

        $dismissedAt = $user->weekly_summary_in_app_dismissed_at;
        if ($dismissedAt !== null && Carbon::parse($dismissedAt)->greaterThanOrEqualTo(Carbon::parse($weekStart))) {
            return response()->json([
                'error' => ['code' => 'DISMISSED', 'message' => 'Ya descartaste el resumen de esta semana.'],
            ], 404);
        }

        return response()->json([
            'data' => [
                'summary' => [
                    'id' => $summary->id,
                    'week_start_date' => $summary->week_start_date?->toDateString(),
                    'products' => $summary->payload_json ?? [],
                ],
            ],
        ]);
    }

    public function dismiss(): JsonResponse
    {
        $user = auth('api')->user();
        $this->service->markDismissed($user);

        return response()->json(['data' => ['message' => 'Resumen descartado.']]);
    }

    public function convertToList(WeeklySummary $summary): JsonResponse
    {
        $user = auth('api')->user();

        if ($summary->user_id !== $user->id) {
            abort(404);
        }

        try {
            $list = $this->service->convertToList($user, $summary);
        } catch (OverflowException $e) {
            return response()->json([
                'error' => ['code' => 'FREEMIUM_LIMIT', 'message' => $e->getMessage()],
            ], 403);
        }

        return response()->json(['data' => $list], 201);
    }
}
