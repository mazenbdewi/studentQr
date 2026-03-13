<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    public function enrollments()
    {
        return $this->hasMany(Enrollment::class, 'student_id');
    }
    
    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'student_id');
    }
}
