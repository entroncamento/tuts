<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageMaterialRef extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'source',
        'material_id',
        'subject_id',
        'section_id',
        'display_name',
        'mime_type',
        'size_bytes',
        'meta_data',
    ];

    protected $casts = [
        'material_id' => 'integer',
        'subject_id' => 'integer',
        'section_id' => 'integer',
        'size_bytes' => 'integer',
        'meta_data' => 'array',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
