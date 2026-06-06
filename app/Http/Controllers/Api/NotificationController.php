<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TutsNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
            'unread_only' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $limit = $validated['limit'] ?? 12;

        $query = TutsNotification::query()
            ->where('user_id', $user->id)
            ->visible()
            ->latest();

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
            'status' => 'sucesso',
            'unread_count' => $unreadCount,
            'notifications' => $notifications,
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
            'status' => 'sucesso',
            'unread_count' => $count,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'nullable|string|max:50',
            'title' => 'required|string|max:160',
            'body' => 'nullable|string|max:2000',
            'data' => 'nullable|array',
            'scheduled_for' => 'nullable|date',
        ]);

        $notification = TutsNotification::create([
            'user_id' => $request->user()->id,
            'type' => $validated['type'] ?? 'system',
            'title' => $validated['title'],
            'body' => $validated['body'] ?? null,
            'data' => $validated['data'] ?? null,
            'scheduled_for' => $validated['scheduled_for'] ?? null,
        ]);

        return response()->json([
            'status' => 'sucesso',
            'notification' => $this->formatNotification($notification),
        ], 201);
    }

    public function markAsRead(Request $request, TutsNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $notification->markAsRead();

        return response()->json([
            'status' => 'sucesso',
            'notification' => $this->formatNotification($notification->fresh()),
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
            'status' => 'sucesso',
        ]);
    }

    public function destroy(Request $request, TutsNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $notification->delete();

        return response()->json([
            'status' => 'sucesso',
        ]);
    }

    private function formatNotification(TutsNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'body' => $notification->body,
            'data' => $notification->data ?? [],
            'scheduled_for' => $notification->scheduled_for?->toISOString(),
            'read_at' => $notification->read_at?->toISOString(),
            'created_at' => $notification->created_at?->toISOString(),
            'is_read' => $notification->read_at !== null,
        ];
    }
}
