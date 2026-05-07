<?php

namespace Tests\Unit\Jobs;

use App\Enums\AiOperation;
use App\Enums\AiUsageStatus;
use App\Jobs\InferItemCategoryJob;
use App\Models\ListItem;
use App\Models\ShoppingList;
use App\Models\User;
use App\Support\Ai\Exceptions\ClaudeException;
use App\Support\Ai\FakeClaudeClient;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class InferItemCategoryJobTest extends TestCase
{
    use DatabaseTransactions;

    private FakeClaudeClient $fakeClaude;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeClaude = new FakeClaudeClient();
        $this->fakeClaude->cannedCategory = 'lacteos_huevos';
    }

    private function makeItem(string $name, ?string $category = null): ListItem
    {
        $user = User::factory()->createOne();
        $list = ShoppingList::factory()->createOne(['user_id' => $user->id]);
        return ListItem::factory()->createOne([
            'shopping_list_id' => $list->id,
            'name' => $name,
            'category' => $category,
            'is_purchased' => false,
            'position' => 0,
        ]);
    }

    public function test_happy_path_updates_category_and_logs_usage(): void
    {
        $item = $this->makeItem('Leche entera');

        (new InferItemCategoryJob($item->id))->handle($this->fakeClaude);

        $this->assertSame('lacteos_huevos', $item->refresh()->category->value);
        $this->assertDatabaseHas('ai_usage_log', [
            'operation' => AiOperation::CategoryInference->value,
            'status' => AiUsageStatus::Success->value,
        ]);
    }

    public function test_item_not_found_is_silent_no_op(): void
    {
        (new InferItemCategoryJob(999999))->handle($this->fakeClaude);

        $this->assertEmpty($this->fakeClaude->categoryInferenceCalls);
    }

    public function test_already_categorized_item_is_skipped(): void
    {
        $item = $this->makeItem('Yogur', 'lacteos_huevos');

        (new InferItemCategoryJob($item->id))->handle($this->fakeClaude);

        $this->assertEmpty($this->fakeClaude->categoryInferenceCalls);
    }

    public function test_claude_exception_logs_warning_and_does_not_crash(): void
    {
        $item = $this->makeItem('Producto X');
        $this->fakeClaude->shouldThrow = new ClaudeException('api down');

        Log::shouldReceive('warning')->once()->with('AI category inference failed', \Mockery::any());

        (new InferItemCategoryJob($item->id))->handle($this->fakeClaude);

        $this->assertNull($item->refresh()->category);
        $this->assertDatabaseMissing('ai_usage_log', ['operation' => AiOperation::CategoryInference->value]);
    }

    public function test_invalid_category_value_skips_update(): void
    {
        $item = $this->makeItem('Producto Raro');
        $this->fakeClaude->cannedCategory = 'not_a_valid_category';

        (new InferItemCategoryJob($item->id))->handle($this->fakeClaude);

        $this->assertNull($item->refresh()->category);
    }

    public function test_null_category_in_response_skips_update(): void
    {
        $item = $this->makeItem('Sin Categoria');
        $this->fakeClaude->cannedCategory = null;

        (new InferItemCategoryJob($item->id))->handle($this->fakeClaude);

        $this->assertNull($item->refresh()->category);
    }

    public function test_race_condition_guard_skips_already_categorized_item(): void
    {
        $item = $this->makeItem('Leche');

        // Simulate race: another process categorizes the item just before the update
        ListItem::where('id', $item->id)->update(['category' => 'otros']);

        (new InferItemCategoryJob($item->id))->handle($this->fakeClaude);

        // Category should remain 'otros' — whereNull guard prevented overwrite
        $this->assertSame('otros', $item->refresh()->category->value);
    }

    public function test_usage_log_records_correct_user_id(): void
    {
        $item = $this->makeItem('Pan');

        (new InferItemCategoryJob($item->id))->handle($this->fakeClaude);

        $this->assertDatabaseHas('ai_usage_log', [
            'user_id' => $item->shoppingList->user_id,
            'operation' => AiOperation::CategoryInference->value,
        ]);
    }

    public function test_job_has_two_tries(): void
    {
        $job = new InferItemCategoryJob(1);
        $this->assertSame(2, $job->tries);
    }
}
