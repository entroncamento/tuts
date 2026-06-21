<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StudentMessageAnalysis extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_recurring' => 'boolean',
        'needs_teacher_attention' => 'boolean',
        'raw_analysis' => 'array',
        'processed_at' => 'datetime',
    ];

    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
