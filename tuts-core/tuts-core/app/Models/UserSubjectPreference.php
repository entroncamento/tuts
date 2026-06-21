<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSubjectPreference extends Model
{
    protected $fillable = [
        'user_id',
        'subject_id',
        'cover_provider',
        'cover_external_id',
        'cover_image_url',
        'cover_thumbnail_url',
        'cover_color',
        'cover_blur_hash',
        'cover_alt',
        'cover_photographer_name',
        'cover_photographer_url',
        'cover_source_url',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
