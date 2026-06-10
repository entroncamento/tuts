<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $guarded = [];

    // Uma cadeira pode pertencer a muitos cursos
    public function courses()
    {
        return $this->belongsToMany(Course::class);
    }
}
