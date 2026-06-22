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
}
