<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_persist_onboarding_completion(): void
    {
        $user = User::factory()->create([
            'onboarding_completed_at' => null,
        ]);
        $otherUser = User::factory()->create([
            'onboarding_completed_at' => null,
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/me/onboarding/complete');

        $response
            ->assertOk()
            ->assertJsonPath('status', 'sucesso')
            ->assertJsonPath('user.id', $user->id);

        $this->assertNotNull($user->refresh()->onboarding_completed_at);
        $this->assertNull($otherUser->refresh()->onboarding_completed_at);
    }

    public function test_unauthenticated_user_cannot_complete_onboarding(): void
    {
        $this->postJson('/api/me/onboarding/complete')
            ->assertUnauthorized();
    }
}
