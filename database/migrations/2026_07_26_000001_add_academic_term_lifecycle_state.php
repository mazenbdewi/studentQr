<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_terms', function (Blueprint $table): void {
            $table->boolean('is_archived')->default(false)->index()->after('teaching_end_date');
        });
    }

    public function down(): void
    {
        Schema::table('academic_terms', function (Blueprint $table): void {
            $table->dropIndex(['is_archived']);
            $table->dropColumn('is_archived');
        });
    }
};
