<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chat extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subject_id',
        'study_space_id',
        'space_folder_id',
        'context_type',
        'is_temporary',
        'title',
    ];

    protected $casts = [
        'is_temporary' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
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
}
