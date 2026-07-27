<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lecture_session_generation_runs', function (Blueprint $table): void {
            $table->index(
                ['academic_term_id', 'completed_at', 'id'],
                'generation_runs_term_completed_id_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('lecture_session_generation_runs', function (Blueprint $table): void {
            $table->dropIndex('generation_runs_term_completed_id_index');
        });
    }
};
