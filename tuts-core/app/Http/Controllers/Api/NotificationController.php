<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TutsNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
            'unread_only' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $limit = $validated['limit'] ?? 20;

        $query = TutsNotification::query()
            ->where('user_id', $user->id)
            ->visible()
            ->recentFirst();

        if ($request->boolean('unread_only')) {
            $query->unread();
        }

        $notifications = $query
            ->limit($limit)
            ->get()
            ->map(fn(TutsNotification $notification) => $this->formatNotification($notification));

        $unreadCount = TutsNotification::query()
            ->where('user_id', $user->id)
            ->visible()
            ->unread()
            ->count();

        return response()->json([
            'status' => 'success',
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = TutsNotification::query()
            ->where('user_id', $request->user()->id)
            ->visible()
            ->unread()
            ->count();

        return response()->json([
            'status' => 'success',
            'unread_count' => $count,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'string', 'max:50'],
            'title' => 'required|string|max:160',
            'body' => 'nullable|string|max:2000',
            'data' => 'nullable|array',
            'url' => 'nullable|string|max:2048',
            'icon' => 'nullable|string|max:80',
            'tone' => ['nullable', 'string', Rule::in(TutsNotification::TONES)],
            'scheduled_for' => 'nullable|date',
        ]);

        $data = $validated['data'] ?? [];

        foreach (['url', 'icon', 'tone'] as $key) {
            if (array_key_exists($key, $validated) && $validated[$key] !== null) {
                $data[$key] = $validated[$key];
            }
        }

        $notification = TutsNotification::create([
            'user_id' => $request->user()->id,
            'type' => TutsNotification::normalizeType($validated['type'] ?? 'system'),
            'title' => $validated['title'],
            'body' => $validated['body'] ?? null,
            'data' => $data ?: null,
            'scheduled_for' => $validated['scheduled_for'] ?? null,
        ]);

        return response()->json([
            'status' => 'success',
            'notification' => $this->formatNotification($notification),
            'unread_count' => $this->unreadCountFor($request->user()->id),
        ], 201);
    }

    public function markAsRead(Request $request, TutsNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $notification->markAsRead();

        return response()->json([
            'status' => 'success',
            'notification' => $this->formatNotification($notification->fresh()),
            'unread_count' => $this->unreadCountFor($request->user()->id),
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        TutsNotification::query()
            ->where('user_id', $request->user()->id)
            ->visible()
            ->unread()
            ->update([
                'read_at' => now(),
            ]);

        return response()->json([
            'status' => 'success',
            'unread_count' => 0,
        ]);
    }

    public function destroy(Request $request, TutsNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $notification->delete();

        return response()->json([
            'status' => 'success',
            'unread_count' => $this->unreadCountFor($request->user()->id),
        ]);
    }

    private function formatNotification(TutsNotification $notification): array
    {
        $data = $notification->data ?? [];
        $meta = TutsNotification::visualMetaFor($notification->type, $data);

        return [
            'id' => $notification->id,
            'type' => $meta['type'],
            'title' => $notification->title,
            'body' => $notification->body,
            'url' => $this->firstString($data, ['url', 'href', 'link']),
            'icon' => $meta['icon'],
            'tone' => $meta['tone'],
            'read_at' => $notification->read_at?->toISOString(),
            'created_at' => $notification->created_at?->toISOString(),
            'created_at_human' => $notification->created_at?->diffForHumans(),
            'data' => $data,
            'scheduled_for' => $notification->scheduled_for?->toISOString(),
            'is_read' => $notification->read_at !== null,
        ];
    }

    private function unreadCountFor(int $userId): int
    {
        return TutsNotification::query()
            ->where('user_id', $userId)
            ->visible()
            ->unread()
            ->count();
    }

    private function firstString(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $data[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }
}
