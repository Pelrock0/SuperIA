<?php

namespace Tests\Unit\Support\Ai;

use App\Models\ProductoHistorial;
use App\Models\ShoppingList;
use App\Models\User;
use App\Support\Ai\HistoryAnonymizer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class HistoryAnonymizerTest extends TestCase
{
    use DatabaseTransactions;

    private HistoryAnonymizer $anonymizer;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->anonymizer = new HistoryAnonymizer();
    }

    private function record(User $user, string $name, ?ShoppingList $list = null): void
    {
        ProductoHistorial::create([
            'user_id' => $user->id,
            'producto_nombre' => $name,
            'categoria' => null,
            'cantidad' => null,
            'unidad' => null,
            'precio_real' => null,
            'fecha_compra' => now(),
            'lista_id' => $list?->id,
        ]);
    }

    public function test_returns_only_strings(): void
    {
        $user = User::factory()->createOne();
        $this->record($user, 'Leche');
        $this->record($user, 'Pan');

        $result = $this->anonymizer->topProducts($user, 10);

        $this->assertIsArray($result);
        foreach ($result as $item) {
            $this->assertIsString($item);
        }
    }

    public function test_orders_by_frequency_desc(): void
    {
        $user = User::factory()->createOne();
        $this->record($user, 'Pan');
        $this->record($user, 'Leche');
        $this->record($user, 'Leche');
        $this->record($user, 'Leche');
        $this->record($user, 'Agua');
        $this->record($user, 'Agua');

        $result = $this->anonymizer->topProducts($user, 10);

        $this->assertSame(['Leche', 'Agua', 'Pan'], $result);
    }

    public function test_respects_limit(): void
    {
        $user = User::factory()->createOne();
        foreach (range(1, 30) as $i) {
            $this->record($user, "Producto{$i}");
        }

        $result = $this->anonymizer->topProducts($user, 5);

        $this->assertCount(5, $result);
    }

    public function test_excludes_other_users(): void
    {
        $user = User::factory()->createOne();
        $other = User::factory()->createOne();
        $this->record($user, 'Mio');
        $this->record($other, 'Suyo');

        $result = $this->anonymizer->topProducts($user, 10);

        $this->assertSame(['Mio'], $result);
    }

    public function test_returns_empty_when_no_history(): void
    {
        $user = User::factory()->createOne();

        $this->assertSame([], $this->anonymizer->topProducts($user, 10));
    }

    public function test_never_contains_pii(): void
    {
        $user = User::factory()->createOne(['email' => 'secret@superia.test']);
        $this->record($user, 'Leche');

        $result = $this->anonymizer->topProducts($user, 10);

        $serialized = json_encode($result);
        $this->assertStringNotContainsString((string) $user->id, $serialized);
        $this->assertStringNotContainsString('secret@superia.test', $serialized);
        $this->assertStringNotContainsString('user_id', $serialized);
        $this->assertStringNotContainsString('email', $serialized);
    }
}
