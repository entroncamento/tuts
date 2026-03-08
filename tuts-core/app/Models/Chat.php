<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Chat extends Model
{
    use HasFactory;

    // Quais os campos que podemos preencher em massa
    protected $fillable = ['user_id', 'title'];

    // Este chat pertence a quem?
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Este chat tem que mensagens?
    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
