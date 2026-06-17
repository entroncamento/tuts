<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'uploaded_by',
        'original_name',
        'mime_type',
        'extension',
        'size_bytes',
        'storage_disk',
        'storage_key',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
