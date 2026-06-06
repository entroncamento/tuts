<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $guarded = [];

    // Um curso tem muitas cadeiras (através da tabela pivot)
    public function subjects()
    {
        return $this->belongsToMany(Subject::class);
    }
}
