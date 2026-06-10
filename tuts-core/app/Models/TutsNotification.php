<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TutsNotification extends Model
{
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

    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->forceFill([
                'read_at' => now(),
            ])->save();
        }
    }
}
