<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    /** @return BelongsTo<LectureSession, $this> */
    public function lectureSession(): BelongsTo
    {
        return $this->belongsTo(LectureSession::class)->withTrashed();
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    //     public function student()
    // {
    //     return $this->belongsTo(Student::class, 'student_id');
    // }
}
