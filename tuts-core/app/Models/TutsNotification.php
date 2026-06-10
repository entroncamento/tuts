<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TutsNotification extends Model
{
    public const TYPES = [
        'reminder',
        'system',
        'study',
        'chat',
        'rag',
        'success',
        'warning',
        'error',
    ];

    public const TONES = [
        'neutral',
        'info',
        'primary',
        'success',
        'warning',
        'danger',
    ];

    private const LEGACY_TYPE_MAP = [
        'calendar' => 'reminder',
        'study_plan' => 'study',
        'material' => 'study',
        'teacher_message' => 'chat',
        'ai' => 'rag',
    ];

    private const TYPE_META = [
        'reminder' => ['icon' => 'clock', 'tone' => 'warning'],
        'system' => ['icon' => 'bell', 'tone' => 'neutral'],
        'study' => ['icon' => 'book-open', 'tone' => 'primary'],
        'chat' => ['icon' => 'message-circle', 'tone' => 'info'],
        'rag' => ['icon' => 'brain', 'tone' => 'info'],
        'success' => ['icon' => 'check-circle', 'tone' => 'success'],
        'warning' => ['icon' => 'alert-triangle', 'tone' => 'warning'],
        'error' => ['icon' => 'alert-circle', 'tone' => 'danger'],
    ];

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'data',
        'scheduled_for',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'scheduled_for' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('scheduled_for')
                ->orWhere('scheduled_for', '<=', now());
        });
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function scopeRecentFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->forceFill([
                'read_at' => now(),
            ])->save();
        }
    }

    public static function normalizeType(?string $type): string
    {
        $candidate = strtolower(trim((string) $type));

        if ($candidate === '') {
            return 'system';
        }

        if (isset(self::LEGACY_TYPE_MAP[$candidate])) {
            return self::LEGACY_TYPE_MAP[$candidate];
        }

        return in_array($candidate, self::TYPES, true) ? $candidate : 'system';
    }

    public static function visualMetaFor(?string $type, array $data = []): array
    {
        $normalizedType = self::normalizeType($type);
        $meta = self::TYPE_META[$normalizedType] ?? self::TYPE_META['system'];

        $icon = $data['icon'] ?? $meta['icon'];
        $tone = $data['tone'] ?? $meta['tone'];

        return [
            'type' => $normalizedType,
            'icon' => is_string($icon) && trim($icon) !== '' ? trim($icon) : $meta['icon'],
            'tone' => is_string($tone) && in_array($tone, self::TONES, true)
                ? $tone
                : $meta['tone'],
        ];
    }
}
