<?php

namespace Tests\Feature\Api;

use App\Mail\WaitlistConfirmationMail;
use App\Models\WaitlistEntry;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WaitlistControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_store_creates_waitlist_entry(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/waitlist', [
            'name' => 'Juan García',
            'email' => 'juan@example.com',
            'shopping_companion' => 'familia',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'position'])
            ->assertJson(['message' => 'Te has registrado en la lista de espera']);

        $this->assertDatabaseHas('waitlist_entries', [
            'name' => 'Juan García',
            'email' => 'juan@example.com',
            'shopping_companion' => 'familia',
        ]);
    }

    public function test_store_without_optional_companion(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/waitlist', [
            'name' => 'Ana',
            'email' => 'ana@example.com',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('waitlist_entries', [
            'email' => 'ana@example.com',
            'shopping_companion' => null,
        ]);
    }

    public function test_store_validates_name_required(): void
    {
        $response = $this->postJson('/api/waitlist', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_validates_email_required(): void
    {
        $response = $this->postJson('/api/waitlist', [
            'name' => 'Test',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_store_validates_email_format(): void
    {
        $response = $this->postJson('/api/waitlist', [
            'name' => 'Test',
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_store_validates_name_max_length(): void
    {
        $response = $this->postJson('/api/waitlist', [
            'name' => str_repeat('a', 101),
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_validates_shopping_companion_values(): void
    {
        $response = $this->postJson('/api/waitlist', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'shopping_companion' => 'invalid-value',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['shopping_companion']);
    }

    public function test_store_duplicate_email_returns_success(): void
    {
        Mail::fake();

        WaitlistEntry::factory()->createOne(['email' => 'existing@example.com']);

        $response = $this->postJson('/api/waitlist', [
            'name' => 'Another',
            'email' => 'existing@example.com',
        ]);

        $response->assertStatus(201)
            ->assertJson(['message' => 'Te has registrado en la lista de espera']);

        $this->assertCount(1, WaitlistEntry::where('email', 'existing@example.com')->get());
    }

    public function test_store_sends_confirmation_email(): void
    {
        Mail::fake();

        $this->postJson('/api/waitlist', [
            'name' => 'Email Test',
            'email' => 'emailtest@example.com',
        ]);

        Mail::assertQueued(WaitlistConfirmationMail::class, function ($mail) {
            return $mail->userName === 'Email Test';
        });
    }

    public function test_store_rate_limited_after_3_requests(): void
    {
        Mail::fake();

        for ($i = 1; $i <= 3; $i++) {
            $response = $this->postJson('/api/waitlist', [
                'name' => "User $i",
                'email' => "user$i@example.com",
            ]);
            $response->assertStatus(201);
        }

        $response = $this->postJson('/api/waitlist', [
            'name' => 'User 4',
            'email' => 'user4@example.com',
        ]);

        $response->assertStatus(429);
    }

    public function test_store_returns_position(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/waitlist', [
            'name' => 'First',
            'email' => 'first@example.com',
        ]);

        $response->assertStatus(201);
        $position = $response->json('position');
        $this->assertIsInt($position);
        $this->assertGreaterThanOrEqual(1, $position);
    }

    public function test_landing_page_loads(): void
    {
        $response = $this->withoutVite()->get('/');

        $response->assertStatus(200);
        $response->assertViewIs('landing');
    }

    public function test_privacy_page_loads(): void
    {
        $response = $this->withoutVite()->get('/privacy');

        $response->assertStatus(200);
        $response->assertViewIs('landing');
    }
}
