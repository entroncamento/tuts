<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_login_with_valid_credentials_returns_user_and_token(): void
    {
        $user = User::factory()->create([
            'email' => 'student@ua.pt',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/mobile/login', [
            'email' => 'student@ua.pt',
            'password' => 'password123',
            'device_name' => 'Test Android Device',
        ], [
            'X-Tuts-Client' => 'mobile',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'user' => [
                'id',
                'name',
                'email',
                'role',
                'onboarding_completed_at',
            ],
            'token',
        ]);

        $this->assertNotNull($response->json('token'));
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_mobile_login_with_invalid_credentials_does_not_return_token(): void
    {
        $user = User::factory()->create([
            'email' => 'student@ua.pt',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/mobile/login', [
            'email' => 'student@ua.pt',
            'password' => 'wrong-password',
        ], [
            'X-Tuts-Client' => 'mobile',
        ]);

        $response->assertStatus(422);
        $response->assertJsonMissing(['token']);
    }

    public function test_mobile_me_with_valid_bearer_token_returns_user(): void
    {
        $user = User::factory()->create([
            'email' => 'student@ua.pt',
        ]);
        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->getJson('/api/mobile/me', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
            ]
        ]);
    }

    public function test_mobile_me_without_token_returns_401(): void
    {
        $response = $this->getJson('/api/mobile/me');

        $response->assertStatus(401);
    }

    public function test_mobile_logout_revokes_current_token_only(): void
    {
        $user = User::factory()->create([
            'email' => 'student@ua.pt',
        ]);
        
        $token1 = $user->createToken('device-1')->plainTextToken;
        $token2 = $user->createToken('device-2')->plainTextToken;

        // Verify tokens exist in database
        $this->assertEquals(2, $user->tokens()->count());

        $response = $this->postJson('/api/mobile/logout', [], [
            'Authorization' => 'Bearer ' . $token1,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Only token 1 should be deleted, token 2 remains
        $this->assertEquals(1, $user->tokens()->count());

        // Reset resolved guards cache to force Sanctum to re-authenticate from database
        $this->app['auth']->forgetGuards();

        // Request using token 1 should now be unauthenticated
        $this->getJson('/api/mobile/me', [
            'Authorization' => 'Bearer ' . $token1,
        ])->assertStatus(401);

        // Request using token 2 should still work
        $this->getJson('/api/mobile/me', [
            'Authorization' => 'Bearer ' . $token2,
        ])->assertStatus(200);
    }

    public function test_web_login_without_mobile_header_does_not_return_token(): void
    {
        $user = User::factory()->create([
            'email' => 'student@ua.pt',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'student@ua.pt',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonMissing(['token']);
    }
}
