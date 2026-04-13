<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ProductoHistorial;
use App\Models\WeeklySummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DataExportController extends Controller
{
    public function show(): JsonResponse
    {
        $user = auth('api')->user();

        $summary = [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at?->toIso8601String(),
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'plan' => $user->plan ?? 'free',
            ],
            'stats' => [
                'lists_active' => $user->shoppingLists()->where('status', 'active')->count(),
                'lists_archived' => $user->shoppingLists()->where('status', 'archived')->count(),
                'products_in_history' => ProductoHistorial::where('user_id', $user->id)->count(),
                'ai_operations_total' => DB::table('ai_usage_log')->where('user_id', $user->id)->count(),
                'weekly_summaries' => WeeklySummary::where('user_id', $user->id)->count(),
            ],
            'settings' => [
                'weekly_summary_email_opted_in' => (bool) $user->weekly_summary_email_opted_in,
                'is_active' => (bool) $user->is_active,
            ],
        ];

        return response()->json(['data' => $summary]);
    }

    public function export(): JsonResponse
    {
        $user = auth('api')->user();

        $data = [
            'export_date' => now()->toIso8601String(),
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at?->toIso8601String(),
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'plan' => $user->plan ?? 'free',
                'weekly_summary_email_opted_in' => (bool) $user->weekly_summary_email_opted_in,
            ],
            'shopping_lists' => $user->shoppingLists()->with('items')->get()->map(fn ($list) => [
                'name' => $list->name,
                'emoji' => $list->emoji,
                'status' => $list->status->value,
                'created_at' => $list->created_at?->toIso8601String(),
                'updated_at' => $list->updated_at?->toIso8601String(),
                'items' => $list->items->map(fn ($item) => [
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit?->value,
                    'category' => $item->category?->value,
                    'is_purchased' => (bool) $item->is_purchased,
                    'estimated_price' => $item->estimated_price,
                ])->toArray(),
            ])->toArray(),
            'product_history' => ProductoHistorial::where('user_id', $user->id)
                ->orderByDesc('fecha_compra')
                ->get()
                ->map(fn ($h) => [
                    'producto_nombre' => $h->producto_nombre,
                    'categoria' => $h->categoria?->value,
                    'cantidad' => $h->cantidad,
                    'unidad' => $h->unidad?->value,
                    'precio_real' => $h->precio_real,
                    'fecha_compra' => $h->fecha_compra?->toIso8601String(),
                ])->toArray(),
            'ai_usage' => DB::table('ai_usage_log')
                ->where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->limit(500)
                ->get()
                ->map(fn ($row) => [
                    'operation' => $row->operation,
                    'status' => $row->status,
                    'date' => $row->date,
                    'estimated_cost_usd' => $row->estimated_cost_usd,
                ])->toArray(),
            'weekly_summaries' => WeeklySummary::where('user_id', $user->id)
                ->orderByDesc('week_start_date')
                ->get()
                ->map(fn ($s) => [
                    'week_start_date' => $s->week_start_date?->toDateString(),
                    'status' => $s->status->value,
                    'products' => $s->payload_json,
                ])->toArray(),
        ];

        return response()->json(['data' => $data], 200, [
            'Content-Disposition' => 'attachment; filename="superia-export-' . now()->format('Y-m-d') . '.json"',
        ]);
    }
}
