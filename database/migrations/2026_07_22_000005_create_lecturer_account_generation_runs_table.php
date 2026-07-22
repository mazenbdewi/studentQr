<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lecturer_account_generation_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_term_id');
            $table->foreignId('started_by')->nullable();
            $table->string('status', 40)->default('pending');
            $table->unsignedInteger('lecturer_count')->default(0);
            $table->unsignedInteger('existing_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('role_added_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->json('summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('academic_term_id', 'lect_acct_runs_term_fk')
                ->references('id')
                ->on('academic_terms')
                ->restrictOnDelete();
            $table->foreign('started_by', 'lect_acct_runs_started_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->index('status', 'lect_acct_runs_status_idx');
            $table->index(['academic_term_id', 'started_at'], 'lect_acct_runs_term_started_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lecturer_account_generation_runs');
    }
};
