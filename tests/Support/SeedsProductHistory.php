<?php

namespace Tests\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Test helper trait — seeds `producto_historial` rows for a user across N ISO weeks.
 *
 * Used by WeeklySummaryService tests that exercise the `eligibleUsers` + `generateForUser`
 * paths, both of which require at least 3 distinct ISO weeks of history per AC-2.
 */
trait SeedsProductHistory
{
    /**
     * Insert purchase history rows spanning the last N distinct ISO weeks.
     * Each week gets one row per product by default.
     *
     * @param  list<string>  $productNames  one product name per row per week
     */
    protected function seedWeeklyHistory(User $user, int $weeks = 3, array $productNames = ['Leche', 'Pan', 'Huevos']): void
    {
        $tz = 'Europe/Madrid';
        $weekStart = Carbon::now($tz)->startOfWeek(Carbon::MONDAY);

        for ($i = 1; $i <= $weeks; $i++) {
            $date = (clone $weekStart)->subWeeks($i)->addDays(2); // mid-week
            foreach ($productNames as $idx => $name) {
                DB::table('producto_historial')->insert([
                    'user_id' => $user->id,
                    'producto_nombre' => $name,
                    'categoria' => 'otros',
                    'cantidad' => 1.0,
                    'unidad' => 'ud',
                    'precio_real' => null,
                    'fecha_compra' => $date->copy()->addMinutes($idx),
                    'lista_id' => null,
                ]);
            }
        }
    }
}
