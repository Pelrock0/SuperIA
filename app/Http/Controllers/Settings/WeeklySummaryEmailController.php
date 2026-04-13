<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateWeeklySummaryEmailRequest;
use Illuminate\Http\JsonResponse;

class WeeklySummaryEmailController extends Controller
{
    public function update(UpdateWeeklySummaryEmailRequest $request): JsonResponse
    {
        $user = auth('api')->user();
        $user->forceFill([
            'weekly_summary_email_opted_in' => (bool) $request->validated('enabled'),
        ])->save();

        return response()->json([
            'data' => [
                'weekly_summary_email_opted_in' => (bool) $user->weekly_summary_email_opted_in,
            ],
        ]);
    }
}
