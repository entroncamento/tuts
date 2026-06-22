<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemporaryChatRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_temporary_chat_creation_gets_default_retention(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/chat', [
            'context_type' => 'temporary',
            'title' => 'Sessao rapida',
        ]);

        $response->assertOk()
            ->assertJsonPath('chat.context_type', 'temporary')
            ->assertJsonPath('chat.is_temporary', true)
            ->assertJsonPath('chat.retention_days', Chat::DEFAULT_TEMPORARY_RETENTION_DAYS)
            ->assertJsonPath('chat.subject_id', null)
            ->assertJsonPath('chat.study_space_id', null);

        $chat = Chat::firstOrFail();

        $this->assertTrue($chat->isTemporary());
        $this->assertSame(Chat::DEFAULT_TEMPORARY_RETENTION_DAYS, $chat->retention_days);
        $this->assertNotNull($chat->expires_at);
        $this->assertTrue($chat->expires_at->between(now()->addDays(6), now()->addDays(8)));
    }

    public function test_expired_temporary_chat_does_not_appear_in_list(): void
    {
        $user = User::factory()->create();

        $active = Chat::create([
            'user_id' => $user->id,
            'context_type' => 'temporary',
            'is_temporary' => true,
            'retention_days' => 7,
            'expires_at' => now()->addDay(),
            'title' => 'Active temporary',
        ]);

        $expired = Chat::create([
            'user_id' => $user->id,
            'context_type' => 'temporary',
            'is_temporary' => true,
            'retention_days' => 7,
            'expires_at' => now()->subMinute(),
            'title' => 'Expired temporary',
        ]);

        Message::create([
            'chat_id' => $active->id,
            'role' => 'user',
            'content' => 'Ainda aparece',
        ]);

        Message::create([
            'chat_id' => $expired->id,
            'role' => 'user',
            'content' => 'Nao deve aparecer',
        ]);

        $response = $this->actingAs($user)->getJson('/api/chat');

        $response->assertOk()
            ->assertJsonCount(1, 'chats')
            ->assertJsonPath('chats.0.chat_id', $active->id)
            ->assertJsonPath('chats.0.retention_days', 7);
    }

    public function test_expired_temporary_chat_history_returns_gone_without_messages(): void
    {
        $user = User::factory()->create();

        $chat = Chat::create([
            'user_id' => $user->id,
            'context_type' => 'temporary',
            'is_temporary' => true,
            'retention_days' => 7,
            'expires_at' => now()->subSecond(),
            'title' => 'Expired temporary',
        ]);

        Message::create([
            'chat_id' => $chat->id,
            'role' => 'user',
            'content' => 'Conteudo sensivel expirado',
        ]);

        $response = $this->actingAs($user)->getJson("/api/chat/{$chat->id}");

        $response->assertGone()
            ->assertJsonPath('status', 'expired')
            ->assertJsonMissing(['mensagens'])
            ->assertJsonMissing(['content' => 'Conteudo sensivel expirado']);
    }

    public function test_temporary_retention_helper_accepts_only_allowed_values(): void
    {
        $chat = new Chat([
            'context_type' => 'temporary',
            'is_temporary' => true,
        ]);

        $chat->applyTemporaryRetention(15, now());

        $this->assertSame(15, $chat->retention_days);
        $this->assertNotNull($chat->expires_at);

        $this->expectException(\InvalidArgumentException::class);

        $chat->applyTemporaryRetention(90, now());
    }

    public function test_authenticated_user_can_update_own_temporary_chat_retention(): void
    {
        $user = User::factory()->create();
        $createdAt = now()->subDays(2)->startOfSecond();

        $chat = Chat::create([
            'user_id' => $user->id,
            'context_type' => 'temporary',
            'is_temporary' => true,
            'retention_days' => 7,
            'expires_at' => $createdAt->copy()->addDays(7),
            'title' => 'Temporary',
        ]);
        $chat->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        $response = $this->actingAs($user)->patchJson("/api/chat/{$chat->id}/retention", [
            'retention_days' => 15,
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'sucesso')
            ->assertJsonPath('chat.id', $chat->id)
            ->assertJsonPath('chat.chat_id', $chat->id)
            ->assertJsonPath('chat.context_type', 'temporary')
            ->assertJsonPath('chat.is_temporary', true)
            ->assertJsonPath('chat.retention_days', 15)
            ->assertJsonPath('chat.expires_at', $createdAt->copy()->addDays(15)->toISOString());

        $this->assertDatabaseHas('chats', [
            'id' => $chat->id,
            'retention_days' => 15,
        ]);

        $this->assertTrue($chat->fresh()->expires_at->eq($createdAt->copy()->addDays(15)));
    }

    public function test_invalid_temporary_chat_retention_returns_validation_error(): void
    {
        $user = User::factory()->create();

        $chat = Chat::create([
            'user_id' => $user->id,
            'context_type' => 'temporary',
            'is_temporary' => true,
            'retention_days' => 7,
            'expires_at' => now()->addDays(7),
            'title' => 'Temporary',
        ]);

        $response = $this->actingAs($user)->patchJson("/api/chat/{$chat->id}/retention", [
            'retention_days' => 31,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['retention_days']);
    }

    public function test_non_temporary_chat_cannot_update_retention(): void
    {
        $user = User::factory()->create();

        $chat = Chat::create([
            'user_id' => $user->id,
            'context_type' => 'uc',
            'is_temporary' => false,
            'title' => 'UC chat',
        ]);

        $response = $this->actingAs($user)->patchJson("/api/chat/{$chat->id}/retention", [
            'retention_days' => 15,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('status', 'erro');

        $this->assertNull($chat->fresh()->retention_days);
        $this->assertNull($chat->fresh()->expires_at);
    }

    public function test_expired_temporary_chat_cannot_update_retention(): void
    {
        $user = User::factory()->create();

        $chat = Chat::create([
            'user_id' => $user->id,
            'context_type' => 'temporary',
            'is_temporary' => true,
            'retention_days' => 7,
            'expires_at' => now()->subMinute(),
            'title' => 'Expired temporary',
        ]);

        $response = $this->actingAs($user)->patchJson("/api/chat/{$chat->id}/retention", [
            'retention_days' => 15,
        ]);

        $response->assertGone()
            ->assertJsonPath('status', 'expired');
    }

    public function test_user_cannot_update_another_users_temporary_chat_retention(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $chat = Chat::create([
            'user_id' => $owner->id,
            'context_type' => 'temporary',
            'is_temporary' => true,
            'retention_days' => 7,
            'expires_at' => now()->addDays(7),
            'title' => 'Other user chat',
        ]);

        $response = $this->actingAs($otherUser)->patchJson("/api/chat/{$chat->id}/retention", [
            'retention_days' => 15,
        ]);

        $response->assertNotFound();

        $this->assertSame(7, $chat->fresh()->retention_days);
    }
}
