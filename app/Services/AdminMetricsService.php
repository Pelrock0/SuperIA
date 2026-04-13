<?php

namespace App\Services;

use App\Enums\AiUsageStatus;
use App\Models\AiUsageLog;
use App\Models\ShoppingList;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminMetricsService
{
    public function getMetrics(): array
    {
        $tz = config('ai.timezone', 'Europe/Madrid');
        $today = Carbon::now($tz)->toDateString();
        $sevenDaysAgo = Carbon::now($tz)->subDays(7);
        $monthStart = Carbon::now($tz)->startOfMonth();

        return [
            'users_total' => User::withTrashed()->count(),
            'users_active_7d' => $this->activeUsersLast7Days($sevenDaysAgo),
            'lists_created_today' => ShoppingList::whereDate('created_at', $today)->count(),
            'lists_total' => ShoppingList::count(),
            'ai_calls_today' => AiUsageLog::where('date', $today)->where('status', AiUsageStatus::Success->value)->count(),
            'ai_calls_month' => AiUsageLog::where('date', '>=', $monthStart->toDateString())->where('status', AiUsageStatus::Success->value)->count(),
            'ai_cost_month' => round((float) AiUsageLog::where('date', '>=', $monthStart->toDateString())->where('status', AiUsageStatus::Success->value)->sum('estimated_cost_usd'), 2),
            'waitlist_pending' => WaitlistEntry::where('status', 'pending')->count(),
        ];
    }

    private function activeUsersLast7Days(Carbon $since): int
    {
        return (int) DB::table('producto_historial')
            ->where('fecha_compra', '>=', $since)
            ->distinct('user_id')
            ->count('user_id');
    }
}
