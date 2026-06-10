<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Message extends Model
{
    use HasFactory;

    // 🔥 ADICIONA O 'meta_data' AQUI À LISTA VIP!
    protected $fillable = ['chat_id', 'role', 'content', 'meta_data'];

    protected $casts = [
        'meta_data' => 'array',
    ];

    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }
}
