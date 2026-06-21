<?php

namespace Tests\Feature;

use App\Models\Subject;
use App\Models\User;
use App\Models\UserSubjectPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PersonalCoverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.unsplash.access_key' => 'test_access_key']);
        config(['services.unsplash.utm_source' => 'tuts']);
    }

    private function enrollStudent(User $user, Subject $subject, string $status = 'active', string $role = 'student'): void
    {
        DB::table('subject_user')->insert([
            'user_id' => $user->id,
            'subject_id' => $subject->id,
            'role' => $role,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_active_student_can_search_photos(): void
    {
        Http::fake([
            'api.unsplash.com/search/photos*' => Http::response([
                'total' => 1,
                'total_pages' => 1,
                'results' => [
                    [
                        'id' => 'photo123',
                        'urls' => [
                            'small' => 'https://images.unsplash.com/small',
                            'regular' => 'https://images.unsplash.com/regular',
                        ],
                        'color' => '#123456',
                        'blur_hash' => 'hash',
                        'alt_description' => 'Alt Description',
                        'user' => [
                            'name' => 'John Doe',
                            'links' => [
                                'html' => 'https://unsplash.com/@johndoe',
                            ],
                        ],
                        'links' => [
                            'html' => 'https://unsplash.com/photos/photo123',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create(['role' => 'aluno']);
        $subject = Subject::create(['name' => 'Linguagens de Programação', 'url' => 'https://example.com']);
        $this->enrollStudent($user, $subject, 'active', 'student');

        $response = $this->actingAs($user)
            ->getJson("/api/me/subjects/uc-{$subject->id}/cover/photos?query=database&page=1");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'thumbnail_url',
                    'image_url',
                    'color',
                    'blur_hash',
                    'alt',
                    'photographer_name',
                    'photographer_url',
                    'source_url',
                ],
            ],
            'meta' => [
                'page',
                'per_page',
                'has_more',
            ],
        ]);
    }

    public function test_active_student_can_apply_cover(): void
    {
        Http::fake([
            'api.unsplash.com/photos/photo123' => Http::response([
                'id' => 'photo123',
                'urls' => [
                    'small' => 'https://images.unsplash.com/small',
                    'regular' => 'https://images.unsplash.com/regular',
                ],
                'color' => '#123456',
                'blur_hash' => 'hash',
                'alt_description' => 'Alt Description',
                'user' => [
                    'name' => 'John Doe',
                    'links' => [
                        'html' => 'https://unsplash.com/@johndoe',
                    ],
                ],
                'links' => [
                    'html' => 'https://unsplash.com/photos/photo123',
                    'download_location' => 'https://api.unsplash.com/photos/photo123/download',
                ],
            ], 200),
            'api.unsplash.com/photos/photo123/download' => Http::response([], 200),
        ]);

        $user = User::factory()->create(['role' => 'aluno']);
        $subject = Subject::create(['name' => 'Linguagens de Programação', 'url' => 'https://example.com']);
        $this->enrollStudent($user, $subject, 'active', 'student');

        $response = $this->actingAs($user)
            ->putJson("/api/me/subjects/uc-{$subject->id}/cover", [
                'photo_id' => 'photo123',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'sucesso',
            'personal_cover' => [
                'provider' => 'unsplash',
                'external_id' => 'photo123',
                'image_url' => 'https://images.unsplash.com/regular',
                'thumbnail_url' => 'https://images.unsplash.com/small',
                'color' => '#123456',
                'blur_hash' => 'hash',
                'alt' => 'Alt Description',
                'photographer_name' => 'John Doe',
                'photographer_url' => 'https://unsplash.com/@johndoe?utm_source=tuts&utm_medium=referral',
                'source_url' => 'https://unsplash.com/photos/photo123?utm_source=tuts&utm_medium=referral',
            ],
        ]);

        $this->assertDatabaseHas('user_subject_preferences', [
            'user_id' => $user->id,
            'subject_id' => $subject->id,
            'cover_external_id' => 'photo123',
        ]);
    }

    public function test_active_student_can_remove_cover(): void
    {
        $user = User::factory()->create(['role' => 'aluno']);
        $subject = Subject::create(['name' => 'Linguagens de Programação', 'url' => 'https://example.com']);
        $this->enrollStudent($user, $subject, 'active', 'student');

        UserSubjectPreference::create([
            'user_id' => $user->id,
            'subject_id' => $subject->id,
            'cover_provider' => 'unsplash',
            'cover_external_id' => 'photo123',
        ]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/me/subjects/uc-{$subject->id}/cover");

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'sucesso',
            'personal_cover' => null,
        ]);

        $this->assertDatabaseMissing('user_subject_preferences', [
            'user_id' => $user->id,
            'subject_id' => $subject->id,
        ]);
    }

    public function test_professor_cannot_manage_cover(): void
    {
        $user = User::factory()->create(['role' => 'professor']);
        $subject = Subject::create(['name' => 'Linguagens de Programação', 'url' => 'https://example.com']);

        $response = $this->actingAs($user)
            ->putJson("/api/me/subjects/uc-{$subject->id}/cover", [
                'photo_id' => 'photo123',
            ]);

        $response->assertStatus(403);
    }

    public function test_inactive_student_cannot_manage_cover(): void
    {
        $user = User::factory()->create(['role' => 'aluno']);
        $subject = Subject::create(['name' => 'Linguagens de Programação', 'url' => 'https://example.com']);
        $this->enrollStudent($user, $subject, 'inactive', 'student');

        $response = $this->actingAs($user)
            ->putJson("/api/me/subjects/uc-{$subject->id}/cover", [
                'photo_id' => 'photo123',
            ]);

        $response->assertStatus(403);
    }

    public function test_creator_of_uc_cannot_manage_cover_just_because_creator(): void
    {
        $user = User::factory()->create(['role' => 'professor']);
        $subject = Subject::create(['name' => 'Linguagens de Programação', 'url' => 'https://example.com']);

        $response = $this->actingAs($user)
            ->putJson("/api/me/subjects/uc-{$subject->id}/cover", [
                'photo_id' => 'photo123',
            ]);

        $response->assertStatus(403);
    }

    public function test_frontend_cannot_pass_user_id(): void
    {
        Http::fake([
            'api.unsplash.com/photos/photo123' => Http::response([
                'id' => 'photo123',
                'urls' => [
                    'small' => 'https://images.unsplash.com/small',
                    'regular' => 'https://images.unsplash.com/regular',
                ],
                'color' => '#123456',
                'blur_hash' => 'hash',
                'alt_description' => 'Alt Description',
                'user' => [
                    'name' => 'John Doe',
                    'links' => [
                        'html' => 'https://unsplash.com/@johndoe',
                    ],
                ],
                'links' => [
                    'html' => 'https://unsplash.com/photos/photo123',
                    'download_location' => 'https://api.unsplash.com/photos/photo123/download',
                ],
            ], 200),
            'api.unsplash.com/photos/photo123/download' => Http::response([], 200),
        ]);

        $user = User::factory()->create(['role' => 'aluno']);
        $otherUser = User::factory()->create(['role' => 'aluno']);
        $subject = Subject::create(['name' => 'Linguagens de Programação', 'url' => 'https://example.com']);
        $this->enrollStudent($user, $subject, 'active', 'student');

        // Try to pass a spoofed user_id
        $response = $this->actingAs($user)
            ->putJson("/api/me/subjects/uc-{$subject->id}/cover", [
                'photo_id' => 'photo123',
                'user_id' => $otherUser->id,
            ]);

        $response->assertStatus(200);

        // Assert the preference was created for the authenticated user and NOT the spoofed one
        $this->assertDatabaseHas('user_subject_preferences', [
            'user_id' => $user->id,
            'subject_id' => $subject->id,
        ]);
        $this->assertDatabaseMissing('user_subject_preferences', [
            'user_id' => $otherUser->id,
            'subject_id' => $subject->id,
        ]);
    }

    public function test_cover_of_another_student_never_appears_in_subject_response(): void
    {
        $userA = User::factory()->create(['role' => 'aluno']);
        $userB = User::factory()->create(['role' => 'aluno']);
        $subject = Subject::create(['name' => 'Linguagens de Programação', 'url' => 'https://example.com']);

        $this->enrollStudent($userA, $subject, 'active', 'student');
        $this->enrollStudent($userB, $subject, 'active', 'student');

        UserSubjectPreference::create([
            'user_id' => $userA->id,
            'subject_id' => $subject->id,
            'cover_provider' => 'unsplash',
            'cover_external_id' => 'photoA',
            'cover_image_url' => 'https://images.unsplash.com/regularA',
        ]);

        // User B fetches the details
        $response = $this->actingAs($userB)
            ->getJson("/api/subjects/uc-{$subject->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'sucesso',
            'subject' => [
                'personal_cover' => null,
                'can_manage_personal_cover' => true,
            ],
        ]);
    }

    public function test_download_location_tracking_failure_does_not_rollback(): void
    {
        Http::fake([
            'api.unsplash.com/photos/photo123' => Http::response([
                'id' => 'photo123',
                'urls' => [
                    'small' => 'https://images.unsplash.com/small',
                    'regular' => 'https://images.unsplash.com/regular',
                ],
                'color' => '#123456',
                'blur_hash' => 'hash',
                'alt_description' => 'Alt Description',
                'user' => [
                    'name' => 'John Doe',
                    'links' => [
                        'html' => 'https://unsplash.com/@johndoe',
                    ],
                ],
                'links' => [
                    'html' => 'https://unsplash.com/photos/photo123',
                    'download_location' => 'https://api.unsplash.com/photos/photo123/download',
                ],
            ], 200),
            // Simulates tracking endpoint failure
            'api.unsplash.com/photos/photo123/download' => Http::response([], 500),
        ]);

        $user = User::factory()->create(['role' => 'aluno']);
        $subject = Subject::create(['name' => 'Linguagens de Programação', 'url' => 'https://example.com']);
        $this->enrollStudent($user, $subject, 'active', 'student');

        $response = $this->actingAs($user)
            ->putJson("/api/me/subjects/uc-{$subject->id}/cover", [
                'photo_id' => 'photo123',
            ]);

        // Request should still succeed and preference should be saved
        $response->assertStatus(200);
        $this->assertDatabaseHas('user_subject_preferences', [
            'user_id' => $user->id,
            'subject_id' => $subject->id,
            'cover_external_id' => 'photo123',
        ]);
    }

    public function test_subject_details_contains_personal_cover_and_capability(): void
    {
        $user = User::factory()->create(['role' => 'aluno']);
        $subject = Subject::create(['name' => 'Linguagens de Programação', 'url' => 'https://example.com']);
        $this->enrollStudent($user, $subject, 'active', 'student');

        UserSubjectPreference::create([
            'user_id' => $user->id,
            'subject_id' => $subject->id,
            'cover_provider' => 'unsplash',
            'cover_external_id' => 'photo123',
            'cover_image_url' => 'https://images.unsplash.com/regular',
            'cover_thumbnail_url' => 'https://images.unsplash.com/small',
            'cover_color' => '#123456',
            'blur_hash' => 'hash',
            'cover_alt' => 'Alt Description',
            'cover_photographer_name' => 'John Doe',
            'cover_photographer_url' => 'https://unsplash.com/@johndoe',
            'cover_source_url' => 'https://unsplash.com/photos/photo123',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/subjects/uc-{$subject->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'sucesso',
            'subject' => [
                'personal_cover' => [
                    'provider' => 'unsplash',
                    'external_id' => 'photo123',
                ],
                'can_manage_personal_cover' => true,
            ],
        ]);
    }
}
