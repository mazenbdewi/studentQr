<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * These indexes are critical for handling 5000+ concurrent requests.
     */
    public function up(): void
    {
        // Index on students table for fast lookups by student_number
        Schema::table('students', function (Blueprint $table) {
            $table->index('student_number', 'idx_students_student_number');
        });

        // Index on attendances table for fast lookups
        Schema::table('attendances', function (Blueprint $table) {
            $table->index(['lecture_session_id', 'student_id'], 'idx_attendances_session_student');
            $table->index('student_id', 'idx_attendances_student_id');
            $table->index('attendance_time', 'idx_attendances_time');
        });

        // Index on lecture_sessions table
        Schema::table('lecture_sessions', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'idx_sessions_status_created');
            $table->index('session_otp', 'idx_sessions_otp');
        });

        // Index on enrollments table
        Schema::table('enrollments', function (Blueprint $table) {
            $table->index(['student_id', 'subject_id'], 'idx_enrollments_student_subject');
        });

        // Index on attendance_tokens table
        Schema::table('attendance_tokens', function (Blueprint $table) {
            $table->index(['token_value', 'expires_at', 'is_used'], 'idx_tokens_valid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('idx_students_student_number');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('idx_attendances_session_student');
            $table->dropIndex('idx_attendances_student_id');
            $table->dropIndex('idx_attendances_time');
        });

        Schema::table('lecture_sessions', function (Blueprint $table) {
            $table->dropIndex('idx_sessions_status_created');
            $table->dropIndex('idx_sessions_otp');
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex('idx_enrollments_student_subject');
        });

        Schema::table('attendance_tokens', function (Blueprint $table) {
            $table->dropIndex('idx_tokens_valid');
        });
    }
};