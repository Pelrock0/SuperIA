<?php

namespace Tests\Unit\Services;

use App\Models\ProductoHistorial;
use App\Models\User;
use App\Services\ProductHistoryCleanupService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProductHistoryCleanupServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ProductHistoryCleanupService $service;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProductHistoryCleanupService();
    }

    private function record(User $user, string $name): void
    {
        ProductoHistorial::create([
            'user_id' => $user->id,
            'producto_nombre' => $name,
            'fecha_compra' => now(),
            'lista_id' => null,
        ]);
    }

    public function test_clear_all_deletes_only_user_rows(): void
    {
        $user = User::factory()->createOne();
        $other = User::factory()->createOne();
        $this->record($user, 'Mio1');
        $this->record($user, 'Mio2');
        $this->record($other, 'Suyo');

        $deleted = $this->service->clearAll($user);

        $this->assertSame(2, $deleted);
        $this->assertDatabaseHas('producto_historial', ['producto_nombre' => 'Suyo']);
        $this->assertDatabaseMissing('producto_historial', ['producto_nombre' => 'Mio1']);
    }

    public function test_clear_all_returns_zero_for_empty(): void
    {
        $user = User::factory()->createOne();

        $this->assertSame(0, $this->service->clearAll($user));
    }

    public function test_forget_deletes_only_matching_product(): void
    {
        $user = User::factory()->createOne();
        $this->record($user, 'Leche');
        $this->record($user, 'Leche');
        $this->record($user, 'Pan');

        $deleted = $this->service->forget($user, 'Leche');

        $this->assertSame(2, $deleted);
        $this->assertDatabaseHas('producto_historial', ['user_id' => $user->id, 'producto_nombre' => 'Pan']);
        $this->assertDatabaseMissing('producto_historial', ['user_id' => $user->id, 'producto_nombre' => 'Leche']);
    }

    public function test_forget_scopes_to_user(): void
    {
        $user = User::factory()->createOne();
        $other = User::factory()->createOne();
        $this->record($user, 'Leche');
        $this->record($other, 'Leche');

        $deleted = $this->service->forget($user, 'Leche');

        $this->assertSame(1, $deleted);
        $this->assertDatabaseHas('producto_historial', [
            'user_id' => $other->id,
            'producto_nombre' => 'Leche',
        ]);
    }

    public function test_forget_returns_zero_when_product_absent(): void
    {
        $user = User::factory()->createOne();

        $this->assertSame(0, $this->service->forget($user, 'Inexistente'));
    }
}
