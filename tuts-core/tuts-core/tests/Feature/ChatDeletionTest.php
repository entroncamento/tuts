<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_delete_own_chat_and_its_messages(): void
    {
        $user = User::factory()->create();
        $chat = Chat::create([
            'user_id' => $user->id,
            'context_type' => 'uc',
            'title' => 'Chat to delete',
        ]);
        $message = Message::create([
            'chat_id' => $chat->id,
            'role' => 'user',
            'content' => 'Delete this message with the chat.',
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/chat/{$chat->id}");

        $response->assertOk()
            ->assertExactJson(['success' => true]);

        $this->assertDatabaseMissing('chats', ['id' => $chat->id]);
        $this->assertDatabaseMissing('messages', ['id' => $message->id]);
    }

    public function test_deleting_nonexistent_chat_returns_not_found(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->deleteJson('/api/chat/999999')
            ->assertNotFound();
    }

    public function test_user_cannot_delete_another_users_chat(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $chat = Chat::create([
            'user_id' => $owner->id,
            'context_type' => 'uc',
            'title' => 'Owner chat',
        ]);
        $message = Message::create([
            'chat_id' => $chat->id,
            'role' => 'user',
            'content' => 'Must remain.',
        ]);

        $this->actingAs($otherUser)
            ->deleteJson("/api/chat/{$chat->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('chats', ['id' => $chat->id]);
        $this->assertDatabaseHas('messages', ['id' => $message->id]);
    }

    public function test_unauthenticated_user_cannot_delete_chat(): void
    {
        $user = User::factory()->create();
        $chat = Chat::create([
            'user_id' => $user->id,
            'context_type' => 'uc',
            'title' => 'Protected chat',
        ]);

        $this->deleteJson("/api/chat/{$chat->id}")
            ->assertUnauthorized();

        $this->assertDatabaseHas('chats', ['id' => $chat->id]);
    }
}
