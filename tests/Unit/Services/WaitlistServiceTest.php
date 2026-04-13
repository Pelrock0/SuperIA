<?php

namespace Tests\Unit\Services;

use App\Mail\InvitationMail;
use App\Mail\WaitlistConfirmationMail;
use App\Models\WaitlistEntry;
use App\Services\WaitlistService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WaitlistServiceTest extends TestCase
{
    use DatabaseTransactions;

    private WaitlistService $service;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WaitlistService();
    }

    public function test_register_creates_entry_and_sends_email(): void
    {
        Mail::fake();

        $result = $this->service->register('Juan', 'juan@example.com', 'pareja');

        $this->assertDatabaseHas('waitlist_entries', [
            'name' => 'Juan',
            'email' => 'juan@example.com',
            'shopping_companion' => 'pareja',
            'status' => 'pending',
        ]);

        $this->assertEquals('Te has registrado en la lista de espera', $result['message']);
        $this->assertIsInt($result['position']);
        $this->assertGreaterThanOrEqual(1, $result['position']);

        Mail::assertQueued(WaitlistConfirmationMail::class, function ($mail) {
            return $mail->userName === 'Juan';
        });
    }

    public function test_register_with_null_companion(): void
    {
        Mail::fake();

        $result = $this->service->register('Ana', 'ana@example.com', null);

        $this->assertDatabaseHas('waitlist_entries', [
            'email' => 'ana@example.com',
            'shopping_companion' => null,
        ]);

        $this->assertArrayHasKey('position', $result);
    }

    public function test_register_duplicate_email_returns_success_without_creating(): void
    {
        Mail::fake();

        WaitlistEntry::factory()->createOne([
            'email' => 'existing@example.com',
            'position' => 5,
        ]);

        $result = $this->service->register('Otro', 'existing@example.com', null);

        $this->assertCount(1, WaitlistEntry::where('email', 'existing@example.com')->get());
        $this->assertEquals('Te has registrado en la lista de espera', $result['message']);

        Mail::assertNotQueued(WaitlistConfirmationMail::class);
    }

    public function test_register_assigns_sequential_position(): void
    {
        Mail::fake();

        $this->service->register('Uno', 'uno@example.com', null);
        $this->service->register('Dos', 'dos@example.com', null);
        $this->service->register('Tres', 'tres@example.com', null);

        $posUno = WaitlistEntry::where('email', 'uno@example.com')->first()->position;
        $posDos = WaitlistEntry::where('email', 'dos@example.com')->first()->position;
        $posTres = WaitlistEntry::where('email', 'tres@example.com')->first()->position;

        $this->assertEquals($posUno + 1, $posDos);
        $this->assertEquals($posDos + 1, $posTres);
    }

    public function test_invite_sends_email_and_updates_entry(): void
    {
        Mail::fake();

        $entry = WaitlistEntry::factory()->createOne(['status' => 'pending']);

        $this->service->invite($entry);

        $entry->refresh();

        $this->assertEquals('invited', $entry->status);
        $this->assertNotNull($entry->invitation_token);
        $this->assertNotNull($entry->invitation_sent_at);
        $this->assertNotNull($entry->invitation_expires_at);
        $this->assertTrue($entry->invitation_expires_at->isFuture());

        Mail::assertQueued(InvitationMail::class, function ($mail) use ($entry) {
            return $mail->userName === $entry->name;
        });
    }

    public function test_invite_throws_if_not_pending(): void
    {
        $entry = WaitlistEntry::factory()->invited()->createOne();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Solo se pueden invitar entradas pendientes.');

        $this->service->invite($entry);
    }

    public function test_find_by_invitation_token_returns_entry(): void
    {
        $entry = WaitlistEntry::factory()->invited()->createOne();

        $found = $this->service->findByInvitationToken($entry->invitation_token);

        $this->assertNotNull($found);
        $this->assertEquals($entry->id, $found->id);
    }

    public function test_find_by_invitation_token_returns_null_for_unknown(): void
    {
        $found = $this->service->findByInvitationToken('nonexistent-token');

        $this->assertNull($found);
    }

    public function test_find_by_invitation_token_returns_null_for_expired(): void
    {
        $entry = WaitlistEntry::factory()->expiredInvitation()->createOne();

        $found = $this->service->findByInvitationToken($entry->invitation_token);

        $this->assertNull($found);
    }
}
