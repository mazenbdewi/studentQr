<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('deduplication_key', 64)->unique();
            $table->string('import_type', 40)->index();
            $table->string('source_filename')->nullable();
            $table->string('source_fingerprint', 64)->nullable()->index();
            $table->foreignId('source_import_batch_id')
                ->nullable()
                ->constrained('import_batches')
                ->restrictOnDelete();
            $table->string('status', 40)->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('rejected_rows')->default(0);
            $table->json('summary')->nullable();
            $table->string('error_file_path')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(
                ['import_type', 'status', 'completed_at'],
                'import_batches_type_status_completed_index',
            );
            $table->index(
                ['source_import_batch_id', 'source_fingerprint'],
                'import_batches_source_batch_fingerprint_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
