<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'student_id')
            ->whereColumn('lecture_session_id', 'enrollments.subject_id');
    }

//    public function enrollments()
//    {
//        return $this->hasMany(Enrollment::class, 'student_id');
//    }
//
//    public function attendances()
//    {
//        return $this->hasMany(Attendance::class, 'student_id');
//    }
}
