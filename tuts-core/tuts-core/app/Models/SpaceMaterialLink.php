<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpaceMaterialLink extends Model
{
    use HasFactory;

    public const TYPE_PERSONAL = 'personal';
    public const TYPE_SUBJECT = 'subject';
    public const RAG_MATERIAL_ID_OFFSET = 1000000000000;

    protected $fillable = [
        'study_space_id',
        'space_folder_id',
        'material_type',
        'material_id',
        'added_by',
        'notes',
    ];

    protected $casts = [
        'study_space_id' => 'integer',
        'space_folder_id' => 'integer',
        'material_id' => 'integer',
        'added_by' => 'integer',
    ];

    public function studySpace(): BelongsTo
    {
        return $this->belongsTo(StudySpace::class, 'study_space_id');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(SpaceFolder::class, 'space_folder_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function ragMaterialId(): int
    {
        return self::RAG_MATERIAL_ID_OFFSET + (int) $this->id;
    }
}
