<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subject extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'url',
        'acronym',
        'enrollment_code',
        'created_by',
        'degree',
        'level',
        'year',
        'semester',
        'academic_year',
        'color',
        'status',
        'source',
    ];

    protected $casts = [
        'created_by' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->creator();
    }

    // Uma cadeira pode pertencer a muitos cursos
    public function courses()
    {
        return $this->belongsToMany(Course::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'subject_user')
            ->withPivot(['role', 'status', 'source'])
            ->withTimestamps();
    }

    public function students(): BelongsToMany
    {
        return $this->users()
            ->wherePivot('role', 'student')
            ->wherePivot('status', 'active');
    }

    public function teachers(): BelongsToMany
    {
        return $this->users()
            ->wherePivot('role', 'teacher')
            ->wherePivot('status', 'active');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(SubjectSection::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(SubjectMaterial::class);
    }

    public function calendarItems(): HasMany
    {
        return $this->hasMany(CalendarItem::class);
    }

    public function teacherEvents(): HasMany
    {
        return $this->hasMany(TeacherEvent::class);
    }
}
