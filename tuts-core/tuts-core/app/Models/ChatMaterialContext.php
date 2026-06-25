<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMaterialContext extends Model
{
    use HasFactory;

    public const SOURCE_PERSONAL = 'personal';
    public const SOURCE_SUBJECT = 'subject';

    protected $table = 'chat_material_contexts';

    protected $fillable = [
        'chat_id',
        'user_id',
        'source',
        'personal_material_id',
        'subject_material_id',
        'subject_id',
        'added_from_message_id',
        'active',
        'expires_at',
    ];

    protected $casts = [
        'chat_id' => 'integer',
        'user_id' => 'integer',
        'personal_material_id' => 'integer',
        'subject_material_id' => 'integer',
        'subject_id' => 'integer',
        'added_from_message_id' => 'integer',
        'active' => 'boolean',
        'expires_at' => 'datetime',
    ];

    /**
     * Relationship: chat
     */
    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    /**
     * Relationship: user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: addedFromMessage
     */
    public function addedFromMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'added_from_message_id');
    }

    /**
     * Relationship: personalMaterial
     */
    public function personalMaterial(): BelongsTo
    {
        return $this->belongsTo(PersonalMaterial::class, 'personal_material_id');
    }

    /**
     * Relationship: subjectMaterial
     */
    public function subjectMaterial(): BelongsTo
    {
        return $this->belongsTo(SubjectMaterial::class, 'subject_material_id');
    }

    /**
     * Relationship: subject
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Helper to check if context is personal source.
     */
    public function isPersonal(): bool
    {
        return $this->source === self::SOURCE_PERSONAL;
    }

    /**
     * Helper to check if context is subject source.
     */
    public function isSubject(): bool
    {
        return $this->source === self::SOURCE_SUBJECT;
    }

    /**
     * Helper to check if context is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->lte(now());
    }

    /**
     * Scope: active contexts.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * Scope: non-expired contexts.
     */
    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope: contexts for a specific chat.
     */
    public function scopeForChat(Builder $query, Chat $chat): Builder
    {
        return $query->where('chat_id', $chat->id);
    }
}
