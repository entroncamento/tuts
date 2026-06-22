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
        
        config([
            'services.python.internal_token' => 'mocked-internal-token',
            'services.python.url' => 'http://127.0.0.1:8001/perguntar',
        ]);

        $this->withoutMiddleware();
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

    /**
     * Test attaching a personal material to a message via controller creates and activates context.
     */
    public function test_controller_attaching_personal_material_creates_active_context(): void
    {
        $user = User::factory()->create(['role' => 'aluno']);
        
        $chat = Chat::create([
            'user_id' => $user->id,
            'context_type' => 'temporary',
            'is_temporary' => true,
            'title' => 'Personal Material Attachment Chat',
        ]);

        $material = PersonalMaterial::create([
            'owner_id' => $user->id,
            'uploaded_by' => $user->id,
            'original_name' => 'my_doc.pdf',
            'storage_disk' => 'r2',
            'storage_key' => 'keys/my_doc.pdf',
        ]);

        $attachedRefs = [
            [
                'source' => 'personal',
                'material_id' => $material->id,
            ]
        ];

        // Call the stream endpoint
        $response = $this->actingAs($user)
            ->postJson('/api/chat/stream', [
                'texto' => 'Teste com anexo pessoal',
                'chat_id' => $chat->id,
                'context_type' => 'temporary',
                'attachedMaterialRefs' => json_encode($attachedRefs),
            ]);

        // Even if connection to RAG fails / returns error, the database transaction
        // creates the message and active context first.
        $this->assertDatabaseHas('chat_material_contexts', [
            'chat_id' => $chat->id,
            'user_id' => $user->id,
            'source' => 'personal',
            'personal_material_id' => $material->id,
            'active' => true,
        ]);
    }

    /**
     * Test attaching a subject material to a message via controller creates and activates context.
     */
    public function test_controller_attaching_subject_material_creates_active_context(): void
    {
        $user = User::factory()->create(['role' => 'aluno']);
        $subject = Subject::create([
            'name' => 'Sistemas Operativos',
            'acronym' => 'SO',
        ]);
        
        // Authorize user to subject
        \Illuminate\Support\Facades\DB::table('subject_user')->insert([
            'user_id' => $user->id,
            'subject_id' => $subject->id,
            'role' => 'student',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $chat = Chat::create([
            'user_id' => $user->id,
            'subject_id' => $subject->id,
            'context_type' => 'uc',
            'title' => 'Subject Material Attachment Chat',
        ]);

        $material = SubjectMaterial::create([
            'subject_id' => $subject->id,
            'name' => 'Aula1.pdf',
            'disk' => 'r2',
            'path' => 'aula1.pdf',
        ]);

        $attachedRefs = [
            [
                'source' => 'subject',
                'material_id' => $material->id,
                'subject_id' => $subject->id,
            ]
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/chat/stream', [
                'texto' => 'Teste com anexo de UC',
                'chat_id' => $chat->id,
                'subject_id' => $subject->id,
                'context_type' => 'uc',
                'attachedMaterialRefs' => json_encode($attachedRefs),
            ]);

        $this->assertDatabaseHas('chat_material_contexts', [
            'chat_id' => $chat->id,
            'user_id' => $user->id,
            'source' => 'subject',
            'subject_material_id' => $material->id,
            'subject_id' => $subject->id,
            'active' => true,
        ]);
    }

    /**
     * Test attaching the same material twice reuses the existing active context row.
     */
    public function test_controller_attaching_same_material_twice_reuses_existing_active_context_row(): void
    {
        $user = User::factory()->create(['role' => 'aluno']);
        
        $chat = Chat::create([
            'user_id' => $user->id,
            'context_type' => 'temporary',
            'is_temporary' => true,
            'title' => 'Duplicate Attachment Chat',
        ]);

        $material = PersonalMaterial::create([
            'owner_id' => $user->id,
            'uploaded_by' => $user->id,
            'original_name' => 'my_doc.pdf',
            'storage_disk' => 'r2',
            'storage_key' => 'keys/my_doc.pdf',
        ]);

        $attachedRefs = [
            [
                'source' => 'personal',
                'material_id' => $material->id,
            ]
        ];

        // Send first attachment message
        $this->actingAs($user)
            ->postJson('/api/chat/stream', [
                'texto' => 'Primeira vez',
                'chat_id' => $chat->id,
                'context_type' => 'temporary',
                'attachedMaterialRefs' => json_encode($attachedRefs),
            ]);

        $firstContext = ChatMaterialContext::where('chat_id', $chat->id)
            ->where('personal_material_id', $material->id)
            ->first();
        
        $this->assertNotNull($firstContext);
        $this->assertTrue($firstContext->active);

        // Send second attachment message for same material
        $this->actingAs($user)
            ->postJson('/api/chat/stream', [
                'texto' => 'Segunda vez',
                'chat_id' => $chat->id,
                'context_type' => 'temporary',
                'attachedMaterialRefs' => json_encode($attachedRefs),
            ]);

        // Total row count in chat_material_contexts for this chat & material should still be 1
        $totalRows = ChatMaterialContext::where('chat_id', $chat->id)
            ->where('personal_material_id', $material->id)
            ->count();
        $this->assertEquals(1, $totalRows);
    }

    /**
     * Test unauthorized personal material is not activated.
     */
    public function test_controller_attaching_unauthorized_personal_material_fails(): void
    {
        $user = User::factory()->create(['role' => 'aluno']);
        $otherUser = User::factory()->create(['role' => 'aluno']);
        
        $chat = Chat::create([
            'user_id' => $user->id,
            'context_type' => 'temporary',
            'is_temporary' => true,
            'title' => 'Hack Chat',
        ]);

        // Material owned by otherUser
        $otherMaterial = PersonalMaterial::create([
            'owner_id' => $otherUser->id,
            'uploaded_by' => $otherUser->id,
            'original_name' => 'private_doc.pdf',
            'storage_disk' => 'r2',
            'storage_key' => 'keys/private_doc.pdf',
        ]);

        $attachedRefs = [
            [
                'source' => 'personal',
                'material_id' => $otherMaterial->id,
            ]
        ];

        // Send message with otherUser's material. Should result in 404/403 or ValidationException model resolving error
        $response = $this->actingAs($user)
            ->postJson('/api/chat/stream', [
                'texto' => 'Tentativa de roubo de anexo',
                'chat_id' => $chat->id,
                'context_type' => 'temporary',
                'attachedMaterialRefs' => json_encode($attachedRefs),
            ]);

        // Model not activated in context
        $this->assertDatabaseMissing('chat_material_contexts', [
            'chat_id' => $chat->id,
            'personal_material_id' => $otherMaterial->id,
        ]);
    }
}
