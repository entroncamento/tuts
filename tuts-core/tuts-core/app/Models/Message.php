<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    use HasFactory;

    protected $fillable = ['chat_id', 'role', 'content', 'meta_data'];

    protected $casts = [
        'meta_data' => 'array',
    ];

    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }

    public function materialRefs(): HasMany
    {
        return $this->hasMany(MessageMaterialRef::class);
    }
}
