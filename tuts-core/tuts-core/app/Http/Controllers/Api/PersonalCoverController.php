<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserSubjectPreference;
use App\Services\UnsplashService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PersonalCoverController extends Controller
{
    protected UnsplashService $unsplashService;

    public function __construct(UnsplashService $unsplashService)
    {
        $this->unsplashService = $unsplashService;
    }

    /**
     * Clean subject_id if it contains the "uc-" prefix.
     */
    private function cleanSubjectId($id): int
    {
        if (str_starts_with($id, 'uc-')) {
            return (int) substr($id, 3);
        }

        return (int) $id;
    }

    /**
     * Search cover photos on Unsplash.
     */
    public function searchPhotos(Request $request, $subject)
    {
        $subjectId = $this->cleanSubjectId($subject);

        Gate::authorize('manage-personal-cover', $subjectId);

        $validated = $request->validate([
            'query' => 'required|string|min:2',
            'page' => 'nullable|integer|min:1',
        ]);

        $query = $validated['query'];
        $page = (int) ($validated['page'] ?? 1);

        $results = $this->unsplashService->search($query, $page, 12);

        return response()->json($results);
    }

    /**
     * Apply a personal cover photo from Unsplash.
     */
    public function updateCover(Request $request, $subject)
    {
        $subjectId = $this->cleanSubjectId($subject);

        Gate::authorize('manage-personal-cover', $subjectId);

        $validated = $request->validate([
            'photo_id' => 'required|string',
        ]);

        $user = $request->user();
        $photoId = $validated['photo_id'];

        // 1. Fetch photo details from Unsplash directly by ID (never trust request parameters)
        $photo = $this->unsplashService->getPhoto($photoId);

        // 2. Normalize metadata from Unsplash
        $normalized = $this->unsplashService->normalizePhoto($photo);

        // 3. Persist the preference
        $preference = UserSubjectPreference::updateOrCreate(
            [
                'user_id' => $user->id,
                'subject_id' => $subjectId,
            ],
            [
                'cover_provider' => 'unsplash',
                'cover_external_id' => $normalized['id'],
                'cover_image_url' => $normalized['image_url'],
                'cover_thumbnail_url' => $normalized['thumbnail_url'],
                'cover_color' => $normalized['color'],
                'cover_blur_hash' => $normalized['blur_hash'],
                'cover_alt' => $normalized['alt'],
                'cover_photographer_name' => $normalized['photographer_name'],
                'cover_photographer_url' => $normalized['photographer_url'],
                'cover_source_url' => $normalized['source_url'],
            ]
        );

        // 4. Trigger download tracking endpoint (do not fail the request if it errors)
        $downloadLocation = $photo['links']['download_location'] ?? null;
        if ($downloadLocation) {
            $this->unsplashService->trackDownload($downloadLocation);
        }

        return response()->json([
            'status' => 'sucesso',
            'personal_cover' => [
                'provider' => $preference->cover_provider,
                'external_id' => $preference->cover_external_id,
                'image_url' => $preference->cover_image_url,
                'thumbnail_url' => $preference->cover_thumbnail_url,
                'color' => $preference->cover_color,
                'blur_hash' => $preference->cover_blur_hash,
                'alt' => $preference->cover_alt,
                'photographer_name' => $preference->cover_photographer_name,
                'photographer_url' => $preference->cover_photographer_url,
                'source_url' => $preference->cover_source_url,
            ],
        ]);
    }

    /**
     * Remove the personal cover photo.
     */
    public function deleteCover(Request $request, $subject)
    {
        $subjectId = $this->cleanSubjectId($subject);

        Gate::authorize('manage-personal-cover', $subjectId);

        $user = $request->user();

        UserSubjectPreference::where('user_id', $user->id)
            ->where('subject_id', $subjectId)
            ->delete();

        return response()->json([
            'status' => 'sucesso',
            'personal_cover' => null,
        ]);
    }
}
