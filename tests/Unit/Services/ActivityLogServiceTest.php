<?php

namespace Tests\Unit\Services;

use App\Enums\ActivityAction;
use App\Enums\ActorType;
use App\Models\ListActivityLog;
use App\Models\ListShareToken;
use App\Models\ShoppingList;
use App\Services\ActivityLogService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ActivityLogServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ActivityLogService $service;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ActivityLogService();
    }

    public function test_record_creates_owner_entry(): void
    {
        $list = ShoppingList::factory()->createOne();

        $log = $this->service->record(
            $list,
            ActorType::Owner,
            ActivityAction::ItemAdded,
            'Leche',
        );

        $this->assertEquals($list->id, $log->shopping_list_id);
        $this->assertEquals(ActorType::Owner, $log->actor_type);
        $this->assertEquals(ActivityAction::ItemAdded, $log->action);
        $this->assertEquals('Leche', $log->item_name);
        $this->assertNull($log->list_share_token_id);
    }

    public function test_record_creates_anonymous_entry_with_token(): void
    {
        $list = ShoppingList::factory()->createOne();
        $token = ListShareToken::factory()->createOne(['shopping_list_id' => $list->id]);

        $log = $this->service->record(
            $list,
            ActorType::Anonymous,
            ActivityAction::ItemChecked,
            'Pan',
            $token->id,
        );

        $this->assertEquals(ActorType::Anonymous, $log->actor_type);
        $this->assertEquals($token->id, $log->list_share_token_id);
    }

    public function test_record_truncates_long_item_name(): void
    {
        $list = ShoppingList::factory()->createOne();
        $long = str_repeat('a', 200);

        $log = $this->service->record(
            $list,
            ActorType::Owner,
            ActivityAction::ItemAdded,
            $long,
        );

        $this->assertEquals(80, mb_strlen($log->item_name));
    }

    public function test_rolling_limit_keeps_only_latest_50(): void
    {
        $list = ShoppingList::factory()->createOne();

        for ($i = 1; $i <= 60; $i++) {
            $this->service->record(
                $list,
                ActorType::Owner,
                ActivityAction::ItemAdded,
                "Item {$i}",
            );
        }

        $this->assertEquals(50, ListActivityLog::where('shopping_list_id', $list->id)->count());
        $oldest = ListActivityLog::where('shopping_list_id', $list->id)->orderBy('id')->first();
        $this->assertEquals('Item 11', $oldest->item_name);
    }

    public function test_rolling_limit_is_per_list(): void
    {
        $list1 = ShoppingList::factory()->createOne();
        $list2 = ShoppingList::factory()->createOne();

        for ($i = 1; $i <= 55; $i++) {
            $this->service->record($list1, ActorType::Owner, ActivityAction::ItemAdded, "L1-{$i}");
        }
        for ($i = 1; $i <= 10; $i++) {
            $this->service->record($list2, ActorType::Owner, ActivityAction::ItemAdded, "L2-{$i}");
        }

        $this->assertEquals(50, ListActivityLog::where('shopping_list_id', $list1->id)->count());
        $this->assertEquals(10, ListActivityLog::where('shopping_list_id', $list2->id)->count());
    }

    public function test_get_recent_returns_newest_first(): void
    {
        $list = ShoppingList::factory()->createOne();
        $this->service->record($list, ActorType::Owner, ActivityAction::ItemAdded, 'Old');
        $this->service->record($list, ActorType::Owner, ActivityAction::ItemAdded, 'New');

        $entries = $this->service->getRecent($list);

        $this->assertEquals('New', $entries->first()->item_name);
        $this->assertEquals('Old', $entries->last()->item_name);
    }

    public function test_get_recent_respects_limit(): void
    {
        $list = ShoppingList::factory()->createOne();
        for ($i = 1; $i <= 5; $i++) {
            $this->service->record($list, ActorType::Owner, ActivityAction::ItemAdded, "Item {$i}");
        }

        $entries = $this->service->getRecent($list, 2);

        $this->assertCount(2, $entries);
    }

    public function test_all_actions_are_recordable(): void
    {
        $list = ShoppingList::factory()->createOne();

        foreach (ActivityAction::cases() as $action) {
            $log = $this->service->record($list, ActorType::Owner, $action, 'test');
            $this->assertEquals($action, $log->action);
        }
    }
}
