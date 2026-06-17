<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    protected $guarded = [];

    // Uma cadeira pode pertencer a muitos cursos
    public function courses()
    {
        return $this->belongsToMany(Course::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(SubjectSection::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(SubjectMaterial::class);
    }
}
