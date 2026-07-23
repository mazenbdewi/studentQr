<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::create('lecturer_credential_batches', function (Blueprint $table): void {
        $table->id(); $table->uuid('uuid')->unique(); $table->foreignId('academic_term_id')->nullable()->constrained()->nullOnDelete();
        $table->string('batch_type', 32); $table->string('original_filename'); $table->string('encrypted_file_path')->nullable()->unique(); $table->string('sha256', 64); $table->string('encryption_key_version', 32)->default('v1');
        $table->unsignedInteger('record_count'); $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('generated_at');
        $table->unsignedInteger('downloaded_count')->default(0); $table->timestamp('last_downloaded_at')->nullable(); $table->string('status', 24)->default('available'); $table->timestamp('deleted_at')->nullable(); $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
    }); }
    public function down(): void { Schema::dropIfExists('lecturer_credential_batches'); }
};
