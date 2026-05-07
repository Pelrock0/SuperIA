# Tests — Testing Patterns

## Test Infrastructure

| Setting | Value |
|---------|-------|
| Framework | PHPUnit + Laravel TestCase |
| Database | Real MySQL (not SQLite) — database: `superia` |
| Isolation | `DatabaseTransactions` trait (rollback after each test) |
| Queue | `sync` (synchronous, no real queue in tests) |
| Cache | `array` driver (in-memory) |
| Mail | `array` driver (captured, not sent) |
| BCRYPT_ROUNDS | 4 (reduced for speed) |

## AAA Pattern (Arrange-Act-Assert)

All tests follow strict AAA:

```php
public function test_freemium_limit_enforced(): void
{
    // ARRANGE
    $user = User::factory()->createOne();
    ShoppingList::factory()->count(3)->create(['user_id' => $user->id, 'status' => 'active']);

    // ACT
    $response = $this->withHeaders($this->authHeaders($user))
        ->postJson('/api/lists', ['name' => 'Extra list', 'category' => 'Supermercado']);

    // ASSERT
    $response->assertStatus(422)
        ->assertJsonPath('error', 'FREEMIUM_LIMIT_REACHED');
}
```

## Factory Patterns

Factories are state-aware:

```php
User::factory()->createOne()                    // standard user
User::factory()->createOne(['is_active' => false])  // deactivated
ShoppingList::factory()->archived()->createOne(['user_id' => $user->id])
WaitlistEntry::factory()->invited()->createOne()
```

**Important:** Always set `is_active => true` explicitly in factories when testing auth-related flows — DB defaults don't propagate to factory models.

## JWT Authentication Helper

Feature tests use a helper for JWT headers:

```php
private function authHeaders(User $user): array
{
    return ['Authorization' => 'Bearer ' . JWTAuth::fromUser($user)];
}

$response = $this->withHeaders($this->authHeaders($user))
    ->getJson('/api/lists');
```

## Fake Claude Client

Service unit tests use `FakeClaudeClient` to avoid real API calls and assert prompt content:

```php
$this->fakeClaude = new FakeClaudeClient();
$this->service = new ListGenerationService($this->fakeClaude, ...);

// Assert no PII in prompt
$payload = $this->fakeClaude->getLastPayload();
$this->assertStringNotContainsString($user->email, json_encode($payload));
```

## Config Override Pattern

Tests that need specific AI config:

```php
protected function setUp(): void
{
    parent::setUp();
    config([
        'ai.api_key' => 'sk-test-key',
        'ai.model' => 'claude-haiku-4-5-20251001',
        'ai.monthly_budget_cap' => 50.0,
        'ai.daily_quota' => 20,
    ]);
}
```

## Four Test Path Types

Every service test must cover:

| Path | Description |
|------|-------------|
| Happy path | Successful operation |
| Failure path | Expected errors (invalid input, not found, etc.) |
| Edge cases | Boundaries (limit=0, empty list, exact threshold) |
| Security path | Auth bypass, IDOR, cross-user isolation |

Example — security path:

```php
public function test_user_cannot_access_other_users_suggestion_history(): void
{
    $userA = User::factory()->createOne();
    $userB = User::factory()->createOne();
    ProductoHistorial::factory()->create(['user_id' => $userB->id, 'producto_nombre' => 'Leche']);

    $suggestions = $this->service->suggest($userA, 'lech');

    $this->assertNotContains('Leche', array_column($suggestions['suggestions'], 'name'));
}
```

## Transaction Safety Testing

Freemium race condition test uses parallel-simulation approach:

```php
public function test_freemium_limit_is_atomic(): void
{
    $user = User::factory()->createOne();
    ShoppingList::factory()->count(2)->create(['user_id' => $user->id, 'status' => 'active']);

    // Simulate concurrent create by calling service twice without transaction isolation
    // Second call should fail with OverflowException
    $this->service->create($user, ['name' => 'List 3', ...]);
    $this->expectException(\OverflowException::class);
    $this->service->create($user, ['name' => 'List 4', ...]);
}
```

## Custom Test Helper

`tests/Support/SeedsProductHistory.php` trait:

```php
trait SeedsProductHistory
{
    protected function seedWeeklyHistory(
        User $user,
        int $weeks = 3,
        array $productNames = ['Leche', 'Pan', 'Huevos']
    ): void {
        // Creates producto_historial rows spanning N ISO weeks
        // Used for WeeklySummaryService eligibility tests
    }
}
```

## HTTP Mocking

External HTTP calls (ClaudeClient) mocked via `Illuminate\Support\Facades\Http::fake()` or `FakeClaudeClient` injection.

```php
Http::fake([
    'api.anthropic.com/*' => Http::response(['content' => [...]], 200),
]);
```
