<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemporaryChatLimitTest extends TestCase
{
    use RefreshDatabase;

    private function createTemporaryChat(User $user, int $daysAgo, bool $expired = false): Chat
    {
        $createdAt = now()->subDays($daysAgo)->startOfSecond();

        $chat = Chat::create([
            'user_id' => $user->id,
            'context_type' => 'temporary',
            'is_temporary' => true,
            'retention_days' => 7,
            'expires_at' => $expired ? now()->subMinute() : now()->addDays(7),
            'title' => "Temporary {$daysAgo}",
        ]);

        $chat->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $chat;
    }

    private function activeTemporaryCount(User $user): int
    {
        return Chat::query()
            ->activeTemporaryForUser((int) $user->id)
            ->count();
    }

    public function test_user_can_create_up_to_ten_active_temporary_chats(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= Chat::MAX_ACTIVE_TEMPORARY_CHATS; $i++) {
            $response = $this->actingAs($user)->postJson('/api/chat', [
                'context_type' => 'temporary',
                'title' => "Temporary {$i}",
            ]);

            $response->assertOk()
                ->assertJsonPath('chat.context_type', 'temporary');
        }

        $this->assertSame(Chat::MAX_ACTIVE_TEMPORARY_CHATS, $this->activeTemporaryCount($user));
    }

    public function test_creating_eleventh_temporary_chat_without_replacement_returns_conflict(): void
    {
        $user = User::factory()->create();
        $oldest = $this->createTemporaryChat($user, 20);

        for ($i = 1; $i < Chat::MAX_ACTIVE_TEMPORARY_CHATS; $i++) {
            $this->createTemporaryChat($user, 20 - $i);
        }

        $response = $this->actingAs($user)->postJson('/api/chat', [
            'context_type' => 'temporary',
            'title' => 'Blocked temporary',
        ]);

        $response->assertConflict()
            ->assertJsonPath('code', 'temporary_chat_limit_reached')
            ->assertJsonPath('limit', Chat::MAX_ACTIVE_TEMPORARY_CHATS)
            ->assertJsonPath('oldest_chat.id', $oldest->id);

        $this->assertSame(Chat::MAX_ACTIVE_TEMPORARY_CHATS, $this->activeTemporaryCount($user));
        $this->assertDatabaseMissing('chats', [
            'user_id' => $user->id,
            'title' => 'Blocked temporary',
        ]);
    }

    public function test_creating_eleventh_temporary_chat_with_replacement_expires_oldest(): void
    {
        $user = User::factory()->create();
        $oldest = $this->createTemporaryChat($user, 20);

        for ($i = 1; $i < Chat::MAX_ACTIVE_TEMPORARY_CHATS; $i++) {
            $this->createTemporaryChat($user, 20 - $i);
        }

        $response = $this->actingAs($user)->postJson('/api/chat', [
            'context_type' => 'temporary',
            'replace_oldest_temporary' => true,
            'title' => 'Replacement temporary',
        ]);

        $response->assertOk()
            ->assertJsonPath('replaced_oldest_temporary_chat_id', $oldest->id)
            ->assertJsonPath('chat.title', 'Replacement temporary');

        $this->assertTrue($oldest->fresh()->isExpired());
        $this->assertSame(Chat::MAX_ACTIVE_TEMPORARY_CHATS, $this->activeTemporaryCount($user));
        $this->assertDatabaseHas('chats', [
            'user_id' => $user->id,
            'title' => 'Replacement temporary',
            'context_type' => 'temporary',
        ]);
    }

    public function test_expired_temporary_chats_do_not_count_toward_limit(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < Chat::MAX_ACTIVE_TEMPORARY_CHATS; $i++) {
            $this->createTemporaryChat($user, 30 + $i, expired: true);
        }

        for ($i = 1; $i < Chat::MAX_ACTIVE_TEMPORARY_CHATS; $i++) {
            $this->createTemporaryChat($user, $i);
        }

        $response = $this->actingAs($user)->postJson('/api/chat', [
            'context_type' => 'temporary',
            'title' => 'Allowed temporary',
        ]);

        $response->assertOk()
            ->assertJsonPath('chat.title', 'Allowed temporary');

        $this->assertSame(Chat::MAX_ACTIVE_TEMPORARY_CHATS, $this->activeTemporaryCount($user));
    }

    public function test_non_temporary_chats_do_not_count_toward_temporary_limit(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 12; $i++) {
            Chat::create([
                'user_id' => $user->id,
                'context_type' => 'uc',
                'is_temporary' => false,
                'title' => "UC {$i}",
            ]);
        }

        for ($i = 1; $i < Chat::MAX_ACTIVE_TEMPORARY_CHATS; $i++) {
            $this->createTemporaryChat($user, $i);
        }

        $response = $this->actingAs($user)->postJson('/api/chat', [
            'context_type' => 'temporary',
            'title' => 'Allowed after UC chats',
        ]);

        $response->assertOk()
            ->assertJsonPath('chat.title', 'Allowed after UC chats');

        $this->assertSame(Chat::MAX_ACTIVE_TEMPORARY_CHATS, $this->activeTemporaryCount($user));
    }

    public function test_user_cannot_affect_another_users_temporary_chat_limit(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        for ($i = 0; $i < Chat::MAX_ACTIVE_TEMPORARY_CHATS; $i++) {
            $this->createTemporaryChat($owner, 20 - $i);
        }

        $response = $this->actingAs($otherUser)->postJson('/api/chat', [
            'context_type' => 'temporary',
            'title' => 'Other user temporary',
        ]);

        $response->assertOk()
            ->assertJsonPath('chat.title', 'Other user temporary')
            ->assertJsonPath('replaced_oldest_temporary_chat_id', null);

        $this->assertSame(Chat::MAX_ACTIVE_TEMPORARY_CHATS, $this->activeTemporaryCount($owner));
        $this->assertSame(1, $this->activeTemporaryCount($otherUser));
    }
}
