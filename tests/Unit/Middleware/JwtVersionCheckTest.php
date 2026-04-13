<?php

namespace Tests\Unit\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtVersionCheckTest extends TestCase
{
    use DatabaseTransactions;

    public function test_allows_request_with_matching_jwt_version(): void
    {
        $user = User::factory()->createOne(['jwt_version' => 0]);
        $token = JWTAuth::fromUser($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/profile');

        $response->assertOk();
    }

    public function test_rejects_request_with_stale_jwt_version(): void
    {
        $user = User::factory()->createOne(['jwt_version' => 0]);
        $token = JWTAuth::fromUser($user);

        $user->incrementJwtVersion(); // version is now 1, token has 0

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/profile');

        $response->assertUnauthorized()
            ->assertJsonPath('error.code', 'TOKEN_INVALIDATED');
    }

    public function test_rejects_request_without_token(): void
    {
        $response = $this->getJson('/api/profile');

        $response->assertUnauthorized();
    }
}
