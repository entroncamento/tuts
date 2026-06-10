<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpaceFolder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'study_space_id',
        'parent_id',
        'name',
        'type',
        'color',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function studySpace(): BelongsTo
    {
        return $this->belongsTo(StudySpace::class, 'study_space_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(SpaceFolder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(SpaceFolder::class, 'parent_id');
    }

    public function chats(): HasMany
    {
        return $this->hasMany(Chat::class, 'space_folder_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(SpaceMaterial::class, 'space_folder_id');
    }
}
