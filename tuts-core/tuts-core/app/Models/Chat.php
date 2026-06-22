<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class Chat extends Model
{
    use HasFactory;

    public const TEMPORARY_RETENTION_DAYS = [7, 15, 30];
    public const DEFAULT_TEMPORARY_RETENTION_DAYS = 7;
    public const MAX_TEMPORARY_RETENTION_DAYS = 30;
    public const MAX_ACTIVE_TEMPORARY_CHATS = 10;

    protected $fillable = [
        'user_id',
        'subject_id',
        'section_id',
        'study_space_id',
        'space_folder_id',
        'context_type',
        'is_temporary',
        'retention_days',
        'expires_at',
        'title',
    ];

    protected $casts = [
        'section_id' => 'integer',
        'is_temporary' => 'boolean',
        'retention_days' => 'integer',
        'expires_at' => 'datetime',
    ];

    public function isTemporary(): bool
    {
        return $this->context_type === 'temporary' || (bool) $this->is_temporary;
    }

    public function isExpired(): bool
    {
        return $this->isTemporary()
            && $this->expires_at !== null
            && $this->expires_at->lte(now());
    }

    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->where(function (Builder $query) {
                $query->where(function (Builder $query) {
                    $query->where('context_type', 'temporary')
                        ->orWhere('is_temporary', true);
                })
                    ->where(function (Builder $query) {
                        $query->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    });
            })->orWhere(function (Builder $query) {
                $query->where(function (Builder $query) {
                    $query->whereNull('context_type')
                        ->orWhere('context_type', '!=', 'temporary');
                })->where(function (Builder $query) {
                    $query->whereNull('is_temporary')
                        ->orWhere('is_temporary', false);
                });
            });
        });
    }

    public function scopeActiveTemporaryForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId)
            ->where(function (Builder $query) {
                $query->where('context_type', 'temporary')
                    ->orWhere('is_temporary', true);
            })
            ->where(function (Builder $query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public static function oldestActiveTemporaryForUser(int $userId): ?self
    {
        return static::query()
            ->activeTemporaryForUser($userId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();
    }

    public function applyTemporaryRetention(int $days, ?Carbon $from = null): self
    {
        if (!in_array($days, self::TEMPORARY_RETENTION_DAYS, true)) {
            throw new InvalidArgumentException('Invalid temporary chat retention days.');
        }

        $from ??= now();

        $this->retention_days = $days;
        $this->expires_at = $from->copy()->addDays($days);

        return $this;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(SubjectSection::class, 'section_id');
    }

    public function studySpace(): BelongsTo
    {
        return $this->belongsTo(StudySpace::class, 'study_space_id');
    }

    public function spaceFolder(): BelongsTo
    {
        return $this->belongsTo(SpaceFolder::class, 'space_folder_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function materialContexts(): HasMany
    {
        return $this->hasMany(ChatMaterialContext::class);
    }

    public function activeMaterialContexts(): HasMany
    {
        return $this->materialContexts()->active()->notExpired();
    }
}
