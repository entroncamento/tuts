<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UnsplashService
{
    protected string $accessKey;

    protected string $utmSource;

    public function __construct()
    {
        $this->accessKey = config('services.unsplash.access_key') ?: '';
        $this->utmSource = config('services.unsplash.utm_source') ?: 'tuts';
    }

    /**
     * Search photos on Unsplash.
     */
    public function search(string $query, int $page = 1, int $perPage = 12): array
    {
        $normalizedQuery = strtolower(trim($query));
        $cacheKey = 'unsplash_search_'.md5($normalizedQuery.'_'.$page.'_'.$perPage);

        return Cache::remember($cacheKey, now()->addHours(1), function () use ($normalizedQuery, $page, $perPage) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Client-ID '.$this->accessKey,
                    'Accept-Version' => 'v1',
                ])
                    ->timeout(10)
                    ->get('https://api.unsplash.com/search/photos', [
                        'query' => $normalizedQuery,
                        'page' => $page,
                        'per_page' => $perPage,
                        'orientation' => 'landscape',
                        'content_filter' => 'high',
                    ]);

                if ($response->failed()) {
                    Log::error('Unsplash API search failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    throw new \Exception('Erro na pesquisa de imagens do Unsplash.');
                }

                $data = $response->json();
                $results = $data['results'] ?? [];
                $totalPages = $data['total_pages'] ?? 0;

                $formatted = array_map(fn ($photo) => $this->normalizePhoto($photo), $results);

                return [
                    'data' => $formatted,
                    'meta' => [
                        'page' => $page,
                        'per_page' => $perPage,
                        'has_more' => $page < $totalPages,
                    ],
                ];
            } catch (\Exception $e) {
                Log::error('Unsplash API exception', ['message' => $e->getMessage()]);
                throw new \Exception('Não foi possível ligar ao serviço de imagens: '.$e->getMessage());
            }
        });
    }

    /**
     * Get photo by ID on Unsplash.
     */
    public function getPhoto(string $photoId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Client-ID '.$this->accessKey,
                'Accept-Version' => 'v1',
            ])
                ->timeout(10)
                ->get("https://api.unsplash.com/photos/{$photoId}");

            if ($response->failed()) {
                Log::error('Unsplash API get photo failed', [
                    'photo_id' => $photoId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('Erro ao obter imagem do Unsplash.');
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Unsplash API get photo exception', ['photo_id' => $photoId, 'message' => $e->getMessage()]);
            throw new \Exception('Não foi possível obter a imagem: '.$e->getMessage());
        }
    }

    /**
     * Track photo download.
     */
    public function trackDownload(string $downloadLocationUrl): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Client-ID '.$this->accessKey,
                'Accept-Version' => 'v1',
            ])
                ->timeout(5)
                ->get($downloadLocationUrl);

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('Unsplash download tracking failed', [
                'url' => $downloadLocationUrl,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Normalize Unsplash photo response.
     */
    public function normalizePhoto(array $photo): array
    {
        $photographerUrl = ($photo['user']['links']['html'] ?? '')
            ? $photo['user']['links']['html']."?utm_source={$this->utmSource}&utm_medium=referral"
            : null;

        $sourceUrl = ($photo['links']['html'] ?? '')
            ? $photo['links']['html']."?utm_source={$this->utmSource}&utm_medium=referral"
            : null;

        return [
            'id' => $photo['id'] ?? '',
            'thumbnail_url' => $photo['urls']['small'] ?? '',
            'image_url' => $photo['urls']['regular'] ?? '',
            'color' => $photo['color'] ?? null,
            'blur_hash' => $photo['blur_hash'] ?? null,
            'alt' => $photo['alt_description'] ?? $photo['description'] ?? '',
            'photographer_name' => $photo['user']['name'] ?? '',
            'photographer_url' => $photographerUrl,
            'source_url' => $sourceUrl,
        ];
    }
}
