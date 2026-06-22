<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\ChatMaterialContext;
use App\Models\Message;
use App\Models\PersonalMaterial;
use App\Models\Subject;
use App\Models\SubjectMaterial;
use App\Models\User;
use App\Services\ChatMaterialContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ChatMaterialContextTest extends TestCase
{
    use RefreshDatabase;

    protected ChatMaterialContextService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ChatMaterialContextService();
    }

    /**
     * Test personal material activation only for owner.
     */
    public function test_personal_material_activation_only_for_owner(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $chat = Chat::create([
            'user_id' => $owner->id,
            'context_type' => 'uc',
            'title' => 'Owner Chat',
        ]);

        $ownerMaterial = PersonalMaterial::create([
            'owner_id' => $owner->id,
            'uploaded_by' => $owner->id,
            'original_name' => 'owner_doc.pdf',
            'storage_disk' => 'r2',
            'storage_key' => 'keys/owner_doc.pdf',
        ]);

        $otherMaterial = PersonalMaterial::create([
            'owner_id' => $otherUser->id,
            'uploaded_by' => $otherUser->id,
            'original_name' => 'other_doc.pdf',
            'storage_disk' => 'r2',
            'storage_key' => 'keys/other_doc.pdf',
        ]);

        // 1. Activating owner's material should succeed
        $context = $this->service->activatePersonalMaterial($chat, $ownerMaterial);
        $this->assertInstanceOf(ChatMaterialContext::class, $context);
        $this->assertTrue($context->active);
        $this->assertEquals($ownerMaterial->id, $context->personal_material_id);

        // 2. Activating other user's material should fail with InvalidArgumentException
        $this->expectException(InvalidArgumentException::class);
        $this->service->activatePersonalMaterial($chat, $otherMaterial);
    }

    /**
     * Test subject material activation creates active context.
     */
    public function test_subject_material_activation_creates_active_context(): void
    {
        $user = User::factory()->create();

        $chat = Chat::create([
            'user_id' => $user->id,
            'context_type' => 'uc',
            'title' => 'Subject Chat',
        ]);

        $subject = Subject::create([
            'name' => 'Mathematics',
            'acronym' => 'MATH',
        ]);

        $material = SubjectMaterial::create([
            'subject_id' => $subject->id,
            'name' => 'Syllabus.pdf',
            'disk' => 'r2',
            'path' => 'syllabus.pdf',
        ]);

        $context = $this->service->activateSubjectMaterial($chat, $material);

        $this->assertInstanceOf(ChatMaterialContext::class, $context);
        $this->assertTrue($context->active);
        $this->assertEquals($material->id, $context->subject_material_id);
        $this->assertEquals($subject->id, $context->subject_id);
        $this->assertEquals($user->id, $context->user_id);
        $this->assertNull($context->expires_at);
    }

    /**
     * Test duplicate activation reuses/updates existing row.
     */
    public function test_duplicate_activation_reuses_and_updates_existing_row(): void
    {
        $user = User::factory()->create();

        $chat = Chat::create([
            'user_id' => $user->id,
            'context_type' => 'uc',
            'title' => 'Re-use Chat',
        ]);

        $material = PersonalMaterial::create([
            'owner_id' => $user->id,
            'uploaded_by' => $user->id,
            'original_name' => 'doc.pdf',
            'storage_disk' => 'r2',
            'storage_key' => 'keys/doc.pdf',
        ]);

        $message = Message::create([
            'chat_id' => $chat->id,
            'role' => 'user',
            'content' => 'Hello',
        ]);

        // First activation
        $context1 = $this->service->activatePersonalMaterial($chat, $material);
        $this->assertTrue($context1->active);
        $this->assertNull($context1->added_from_message_id);

        // Deactivate it
        $this->service->deactivateForChatMaterial($chat, ChatMaterialContext::SOURCE_PERSONAL, $material->id);
        $this->assertFalse($context1->fresh()->active);

        // Second activation with message
        $context2 = $this->service->activatePersonalMaterial($chat, $material, $message);
        
        $this->assertEquals($context1->id, $context2->id); // Same row is updated/re-used
        $this->assertTrue($context2->active);
        $this->assertEquals($message->id, $context2->added_from_message_id);

        // Assert database only contains one row for this chat & material
        $this->assertEquals(1, ChatMaterialContext::where('chat_id', $chat->id)->count());
    }

    /**
     * Test listActiveForChat returns active non-expired contexts.
     */
    public function test_list_active_for_chat_returns_active_non_expired_contexts(): void
    {
        $user = User::factory()->create();

        $chat = Chat::create([
            'user_id' => $user->id,
            'context_type' => 'uc',
            'title' => 'Listing Chat',
        ]);

        $subject = Subject::create([
            'name' => 'Physics',
            'acronym' => 'PHYS',
        ]);

        $material1 = SubjectMaterial::create([
            'subject_id' => $subject->id,
            'name' => 'Lab1.pdf',
            'disk' => 'r2',
            'path' => 'lab1.pdf',
        ]);

        $material2 = SubjectMaterial::create([
            'subject_id' => $subject->id,
            'name' => 'Lab2.pdf',
            'disk' => 'r2',
            'path' => 'lab2.pdf',
        ]);

        $material3 = SubjectMaterial::create([
            'subject_id' => $subject->id,
            'name' => 'Lab3.pdf',
            'disk' => 'r2',
            'path' => 'lab3.pdf',
        ]);

        // Active, not expired
        $this->service->activateSubjectMaterial($chat, $material1);

        // Active, but expired
        $expiredContext = $this->service->activateSubjectMaterial($chat, $material2);
        $expiredContext->update([
            'expires_at' => now()->subMinutes(10),
        ]);

        // Inactive
        $inactiveContext = $this->service->activateSubjectMaterial($chat, $material3);
        $inactiveContext->update([
            'active' => false,
        ]);

        $activeList = $this->service->listActiveForChat($chat);

        $this->assertCount(1, $activeList);
        $this->assertEquals($material1->id, $activeList->first()->subject_material_id);
    }
}
