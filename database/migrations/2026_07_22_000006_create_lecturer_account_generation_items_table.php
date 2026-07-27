<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lecturer_account_generation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('run_id');
            $table->foreignId('lecturer_id');
            $table->foreignId('user_id')->nullable();
            $table->string('login_username', 64)->nullable();
            $table->string('result', 40);
            $table->string('error_code', 80)->nullable();
            $table->text('message')->nullable();
            $table->timestamps();

            $table->foreign('run_id', 'lect_acct_items_run_fk')
                ->references('id')
                ->on('lecturer_account_generation_runs')
                ->cascadeOnDelete();
            $table->foreign('lecturer_id', 'lect_acct_items_lecturer_fk')
                ->references('id')
                ->on('lecturers')
                ->restrictOnDelete();
            $table->foreign('user_id', 'lect_acct_items_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->index(['run_id', 'result'], 'lect_acct_items_run_result_idx');
            $table->index(['lecturer_id', 'result'], 'lect_acct_items_lecturer_result_idx');
            $table->index('login_username', 'lect_acct_items_login_username_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lecturer_account_generation_items');
    }
};
