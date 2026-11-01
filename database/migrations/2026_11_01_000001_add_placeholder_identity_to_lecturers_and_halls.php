<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lecturers', function (Blueprint $table): void {
            $table->string('name_en')->nullable()->after('name');
            $table->enum('placeholder_type', ['theoretical', 'practical'])
                ->nullable()
                ->after('canonical_name');
            $table->unique('placeholder_type', 'lecturers_placeholder_type_unique');
        });

        Schema::table('halls', function (Blueprint $table): void {
            $table->string('name_en')->nullable()->after('name');
            $table->enum('placeholder_type', ['unassigned'])
                ->nullable()
                ->after('name_en');
            $table->unique('placeholder_type', 'halls_placeholder_type_unique');
        });
    }

    public function down(): void
    {
        Schema::table('halls', function (Blueprint $table): void {
            $table->dropUnique('halls_placeholder_type_unique');
            $table->dropColumn(['placeholder_type', 'name_en']);
        });

        Schema::table('lecturers', function (Blueprint $table): void {
            $table->dropUnique('lecturers_placeholder_type_unique');
            $table->dropColumn(['placeholder_type', 'name_en']);
        });
    }
};
