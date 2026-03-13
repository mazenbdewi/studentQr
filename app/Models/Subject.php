<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $fillable =['name'];
    public function lecturer()
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function lectureSessions()
    {
        return $this->hasMany(LectureSession::class);
    }


    public function students()
    {
        return $this->belongsToMany(Student::class, 'enrollments')
            ->withPivot(['semester', 'year', 'status'])
            ->withTimestamps();
    }
}
