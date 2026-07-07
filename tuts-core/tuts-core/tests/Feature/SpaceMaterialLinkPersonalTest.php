<?php

namespace Tests\Feature;

use App\Models\PersonalMaterial;
use App\Models\SpaceMaterialLink;
use App\Models\StudySpace;
use App\Models\User;
use App\Services\RagIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SpaceMaterialLinkPersonalTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_link_own_personal_material_to_space(): void
    {
        $user = User::factory()->create(['role' => 'aluno']);
        $space = $this->createSpace($user);
        $material = $this->createPersonalMaterial($user);

        $this->mockSuccessfulRagIngestion();

        $response = $this->actingAs($user)->postJson("/api/spaces/{$space->id}/materials/link-personal", [
            'personal_material_id' => $material->id,
            'notes' => 'Use in next session',
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'sucesso')
            ->assertJsonPath('material.id', 'link-' . SpaceMaterialLink::first()->id)
            ->assertJsonPath('material.personal_material_id', $material->id)
            ->assertJsonPath('material.canonical_material_type', SpaceMaterialLink::TYPE_PERSONAL)
            ->assertJsonPath('material.folder_id', null)
            ->assertJsonPath('material.notes', 'Use in next session')
            ->assertJsonPath('rag_ingestion.status', 'success');

        $this->assertDatabaseHas('space_material_links', [
            'study_space_id' => $space->id,
            'space_folder_id' => null,
            'material_type' => SpaceMaterialLink::TYPE_PERSONAL,
            'material_id' => $material->id,
            'added_by' => $user->id,
            'notes' => 'Use in next session',
        ]);
    }

    public function test_linked_personal_material_appears_in_space_material_index(): void
    {
        $user = User::factory()->create(['role' => 'aluno']);
        $space = $this->createSpace($user);
        $material = $this->createPersonalMaterial($user, ['original_name' => 'indexable.pdf']);

        $link = SpaceMaterialLink::create([
            'study_space_id' => $space->id,
            'space_folder_id' => null,
            'material_type' => SpaceMaterialLink::TYPE_PERSONAL,
            'material_id' => $material->id,
            'added_by' => $user->id,
            'notes' => 'Already linked',
        ]);

        $response = $this->actingAs($user)->getJson("/api/spaces/{$space->id}/materials");

        $response->assertOk()
            ->assertJsonPath('status', 'sucesso')
            ->assertJsonPath('materials.0.id', 'link-' . $link->id)
            ->assertJsonPath('materials.0.name', 'indexable.pdf')
            ->assertJsonPath('materials.0.personal_material_id', $material->id)
            ->assertJsonPath('materials.0.folder_id', null);
    }

    public function test_linking_same_personal_material_is_idempotent(): void
    {
        $user = User::factory()->create(['role' => 'aluno']);
        $space = $this->createSpace($user);
        $material = $this->createPersonalMaterial($user);

        $this->mockSuccessfulRagIngestion(2);

        $firstResponse = $this->actingAs($user)->postJson("/api/spaces/{$space->id}/materials/link-personal", [
            'personal_material_id' => $material->id,
            'notes' => 'First note',
        ]);

        $secondResponse = $this->actingAs($user)->postJson("/api/spaces/{$space->id}/materials/link-personal", [
            'personal_material_id' => $material->id,
            'notes' => 'Updated note',
        ]);

        $firstResponse->assertCreated();
        $secondResponse->assertOk()
            ->assertJsonPath('material.notes', 'Updated note');

        $this->assertSame(1, SpaceMaterialLink::query()
            ->where('study_space_id', $space->id)
            ->whereNull('space_folder_id')
            ->where('material_type', SpaceMaterialLink::TYPE_PERSONAL)
            ->where('material_id', $material->id)
            ->where('added_by', $user->id)
            ->count());
    }

    public function test_user_cannot_link_another_users_personal_material(): void
    {
        $owner = User::factory()->create(['role' => 'aluno']);
        $otherUser = User::factory()->create(['role' => 'aluno']);
        $space = $this->createSpace($otherUser);
        $material = $this->createPersonalMaterial($owner);

        $mockRag = Mockery::mock(RagIngestionService::class);
        $mockRag->shouldNotReceive('ingestSpaceMaterialLink');
        $this->app->instance(RagIngestionService::class, $mockRag);

        $response = $this->actingAs($otherUser)->postJson("/api/spaces/{$space->id}/materials/link-personal", [
            'personal_material_id' => $material->id,
        ]);

        $response->assertNotFound();
        $this->assertDatabaseCount('space_material_links', 0);
    }

    public function test_link_always_uses_null_space_folder_id(): void
    {
        $user = User::factory()->create(['role' => 'aluno']);
        $space = $this->createSpace($user);
        $material = $this->createPersonalMaterial($user);

        $this->mockSuccessfulRagIngestion();

        $this->actingAs($user)->postJson("/api/spaces/{$space->id}/materials/link-personal", [
            'personal_material_id' => $material->id,
            'notes' => null,
        ])->assertCreated();

        $this->assertNull(SpaceMaterialLink::first()->space_folder_id);
    }

    private function createSpace(User $user): StudySpace
    {
        return StudySpace::create([
            'user_id' => $user->id,
            'name' => 'Exam Prep',
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createPersonalMaterial(User $owner, array $overrides = []): PersonalMaterial
    {
        return PersonalMaterial::create($overrides + [
            'owner_id' => $owner->id,
            'uploaded_by' => $owner->id,
            'original_name' => 'material.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 9,
            'storage_disk' => 'r2',
            'storage_key' => 'personal/users/' . $owner->id . '/material.pdf',
        ]);
    }

    private function mockSuccessfulRagIngestion(int $times = 1): void
    {
        $mockRag = Mockery::mock(RagIngestionService::class);
        $mockRag->shouldReceive('ingestSpaceMaterialLink')
            ->times($times)
            ->with(Mockery::type(SpaceMaterialLink::class))
            ->andReturn([
                'status' => 'success',
                'message' => 'Material enviado para indexacao RAG.',
                'reason' => null,
            ]);

        $this->app->instance(RagIngestionService::class, $mockRag);
    }
}
