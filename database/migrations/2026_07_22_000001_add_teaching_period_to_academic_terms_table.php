<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_terms', function (Blueprint $table): void {
            $table->date('teaching_start_date')->nullable()->after('canonical_name');
            $table->date('teaching_end_date')->nullable()->after('teaching_start_date');
        });
    }

    public function down(): void
    {
        Schema::table('academic_terms', function (Blueprint $table): void {
            $table->dropColumn(['teaching_start_date', 'teaching_end_date']);
        });
    }
};
