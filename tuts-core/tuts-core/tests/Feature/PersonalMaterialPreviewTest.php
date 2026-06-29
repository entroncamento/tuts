<?php

namespace Tests\Feature;

use App\Models\PersonalMaterial;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PersonalMaterialPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_load_pdf_before_response_starts(): void
    {
        Storage::fake('r2');
        Storage::disk('r2')->put('personal/users/1/material.pdf', '%PDF-test');

        $user = User::factory()->create(['role' => 'aluno']);
        $material = $this->createMaterial($user, 'personal/users/1/material.pdf');

        $response = $this->actingAs($user)->get(
            "/api/me/materials/{$material->id}/view",
            $this->pdfHeaders(),
        );

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition', 'inline; filename="material.pdf"');
        $this->assertSame('%PDF-test', $response->getContent());
    }

    public function test_storage_public_url_is_normalized_before_reading(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('mock/material.pdf', '%PDF-normalized');

        $user = User::factory()->create(['role' => 'aluno']);
        $material = $this->createMaterial(
            $user,
            'http://localhost:8000/storage/mock/material.pdf',
            'public',
        );

        $response = $this->actingAs($user)->get(
            "/api/me/materials/{$material->id}/view",
            $this->pdfHeaders(),
        );

        $response->assertOk();
        $this->assertSame('%PDF-normalized', $response->getContent());
    }

    public function test_non_owner_receives_json_forbidden_response(): void
    {
        Storage::fake('r2');

        $owner = User::factory()->create(['role' => 'aluno']);
        $otherUser = User::factory()->create(['role' => 'aluno']);
        $material = $this->createMaterial($owner, 'personal/users/1/material.pdf');

        $response = $this->actingAs($otherUser)->getJson(
            "/api/me/materials/{$material->id}/view",
        );

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'You do not have permission to view this material.',
        ]);
    }

    public function test_missing_file_returns_json_not_found_response(): void
    {
        Storage::fake('r2');

        $user = User::factory()->create(['role' => 'aluno']);
        $material = $this->createMaterial($user, 'personal/users/1/missing.pdf');

        $response = $this->actingAs($user)->getJson(
            "/api/me/materials/{$material->id}/view",
        );

        $response->assertStatus(404);
        $response->assertJson([
            'message' => 'PDF file not found.',
            'material_id' => $material->id,
            'path' => 'personal/users/1/missing.pdf',
            'disk' => 'r2',
        ]);
    }

    public function test_storage_exception_returns_json_server_error_response(): void
    {
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->once()->andReturnTrue();
        $disk->shouldReceive('get')->once()->andThrow(new RuntimeException('R2 read failed'));
        Storage::shouldReceive('disk')->with('r2')->andReturn($disk);

        $user = User::factory()->create(['role' => 'aluno']);
        $material = $this->createMaterial($user, 'personal/users/1/material.pdf');

        $response = $this->actingAs($user)->getJson(
            "/api/me/materials/{$material->id}/view",
        );

        $response->assertStatus(500);
        $response->assertJson([
            'message' => 'Failed to load PDF from storage.',
        ]);
    }

    private function createMaterial(
        User $owner,
        string $storageKey,
        string $storageDisk = 'r2',
    ): PersonalMaterial {
        return PersonalMaterial::create([
            'owner_id' => $owner->id,
            'uploaded_by' => $owner->id,
            'original_name' => 'material.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 9,
            'storage_disk' => $storageDisk,
            'storage_key' => $storageKey,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function pdfHeaders(): array
    {
        return [
            'Accept' => 'application/pdf',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }
}
