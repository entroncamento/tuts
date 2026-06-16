<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudySpace extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'cover',
        'color',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chats(): HasMany
    {
        return $this->hasMany(Chat::class, 'study_space_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(SpaceMaterial::class, 'study_space_id');
    }

    public function folders(): HasMany
    {
        return $this->hasMany(SpaceFolder::class, 'study_space_id');
    }
}
