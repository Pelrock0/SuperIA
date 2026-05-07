<?php

namespace App\Http\Controllers;

use App\Enums\WeeklySummaryStatus;
use App\Http\Requests\SaveWeeklySummarySelectionRequest;
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

        if ($summary === null
            || $summary->status === WeeklySummaryStatus::Failed
            || $summary->status === WeeklySummaryStatus::Actioned
        ) {
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

    public function save(SaveWeeklySummarySelectionRequest $request, WeeklySummary $summary): JsonResponse
    {
        $user = auth('api')->user();
        $validated = $request->validated();

        try {
            $result = $this->service->saveSelection(
                $user,
                $summary,
                $validated['selected_indices'],
                $validated['target_list_id'] ?? null,
                $validated['new_list_name'] ?? null,
            );
        } catch (OverflowException $e) {
            return response()->json([
                'error' => ['code' => 'FREEMIUM_LIMIT', 'message' => $e->getMessage()],
            ], 403);
        }

        $resultSummary = $result['summary'];

        return response()->json([
            'data' => [
                'list' => $result['list'],
                'summary' => [
                    'id' => $resultSummary->id,
                    'status' => $resultSummary->status->value,
                    'remaining_items' => $resultSummary->payload_json ?? [],
                    'is_actioned' => $resultSummary->status === WeeklySummaryStatus::Actioned,
                ],
            ],
        ], 200);
    }
}
