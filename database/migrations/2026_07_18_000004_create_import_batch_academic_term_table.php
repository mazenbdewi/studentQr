<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batch_academic_term', function (Blueprint $table): void {
            $table->foreignId('import_batch_id')
                ->constrained('import_batches')
                ->cascadeOnDelete();
            $table->foreignId('academic_term_id')
                ->constrained('academic_terms')
                ->restrictOnDelete();
            $table->unsignedInteger('row_count')->default(0);

            $table->unique(
                ['import_batch_id', 'academic_term_id'],
                'import_batch_term_unique',
            );
            $table->index(
                ['academic_term_id', 'import_batch_id'],
                'import_batch_term_lookup_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batch_academic_term');
    }
};
