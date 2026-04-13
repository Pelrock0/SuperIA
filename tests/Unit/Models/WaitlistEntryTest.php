<?php

namespace Tests\Unit\Models;

use App\Models\WaitlistEntry;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class WaitlistEntryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_is_pending_returns_true_when_status_pending(): void
    {
        $entry = WaitlistEntry::factory()->createOne(['status' => 'pending']);

        $this->assertTrue($entry->isPending());
        $this->assertFalse($entry->isInvited());
    }

    public function test_is_invited_returns_true_when_status_invited(): void
    {
        $entry = WaitlistEntry::factory()->invited()->createOne();

        $this->assertTrue($entry->isInvited());
        $this->assertFalse($entry->isPending());
    }

    public function test_has_valid_invitation_returns_true_when_not_expired(): void
    {
        $entry = WaitlistEntry::factory()->invited()->createOne();

        $this->assertTrue($entry->hasValidInvitation());
    }

    public function test_has_valid_invitation_returns_false_when_expired(): void
    {
        $entry = WaitlistEntry::factory()->expiredInvitation()->createOne();

        $this->assertFalse($entry->hasValidInvitation());
    }

    public function test_has_valid_invitation_returns_false_when_pending(): void
    {
        $entry = WaitlistEntry::factory()->createOne(['status' => 'pending']);

        $this->assertFalse($entry->hasValidInvitation());
    }

    public function test_casts_dates_correctly(): void
    {
        $entry = WaitlistEntry::factory()->invited()->createOne();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $entry->invitation_sent_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $entry->invitation_expires_at);
    }

    public function test_fillable_attributes(): void
    {
        $entry = WaitlistEntry::factory()->createOne([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'shopping_companion' => 'pareja',
        ]);

        $this->assertEquals('Test User', $entry->name);
        $this->assertEquals('test@example.com', $entry->email);
        $this->assertEquals('pareja', $entry->shopping_companion);
    }
}
