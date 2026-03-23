<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    public function lectureSession()
    {
        return $this->belongsTo(LectureSession::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }


//     protected static function booted()
// {
//     static::creating(function ($attendance) {
//         $attendance->recorded_at = $attendance->recorded_at ?? now();
//     });
// }

//     public function student()
// {
//     return $this->belongsTo(Student::class, 'student_id');
// }
}
