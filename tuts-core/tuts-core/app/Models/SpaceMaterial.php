<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpaceMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'study_space_id',
        'space_folder_id',
        'original_name',
        'stored_name',
        'mime_type',
        'extension',
        'size_bytes',
        'disk',
        'path',
        'notes',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function studySpace(): BelongsTo
    {
        return $this->belongsTo(StudySpace::class, 'study_space_id');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(SpaceFolder::class, 'space_folder_id');
    }
}
