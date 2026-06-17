<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_id',
        'section_id',
        'name',
        'type',
        'mime_type',
        'size_bytes',
        'path',
        'url',
        'source',
        'verified_by_teacher',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'verified_by_teacher' => 'boolean',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(SubjectSection::class, 'section_id');
    }
}
