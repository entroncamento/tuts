<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Message extends Model
{
    use HasFactory;

    // Permitir guardar o ID do chat, quem falou (role) e o texto
    protected $fillable = ['chat_id', 'role', 'content'];

    // Esta mensagem pertence a que chat?
    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }
}
