<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AvailabilityBlock extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'start_time',
        'end_time',
        'repeat_type',
        'repeat_days',
        'starts_on',
        'ends_on',
        'is_active',
        'color',
    ];

    protected $casts = [
        'repeat_days' => 'array',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
