<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("
            ALTER TABLE users
            MODIFY role ENUM('super_admin', 'admin', 'manager', 'attendance_monitor', 'course_lecturer', 'student') NOT NULL
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('users')
            ->where('role', 'admin')
            ->update(['role' => 'attendance_monitor']);

        DB::statement("
            ALTER TABLE users
            MODIFY role ENUM('super_admin', 'attendance_monitor', 'course_lecturer') NOT NULL
        ");
    }
};
