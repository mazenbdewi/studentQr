<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasCapacity = Schema::hasColumn('halls', 'capacity');
        $hasHallType = Schema::hasColumn('halls', 'hall_type');
        $hasBuildingName = Schema::hasColumn('halls', 'building_name');
        $hasFacultyId = Schema::hasColumn('halls', 'faculty_id');
        $hasNotes = Schema::hasColumn('halls', 'notes');

        Schema::table('halls', function (Blueprint $table) use ($hasBuildingName, $hasCapacity, $hasFacultyId, $hasHallType, $hasNotes): void {
            if (! $hasCapacity) {
                $table->unsignedInteger('capacity')->nullable()->after('name');
            }

            if (! $hasHallType) {
                $table->string('hall_type', 40)->nullable()->after('capacity');
            }

            if (! $hasBuildingName) {
                $table->string('building_name')->nullable()->after('hall_type');
            }

            if (! $hasFacultyId) {
                $table->foreignId('faculty_id')
                    ->nullable()
                    ->after('building_name');
                $table->foreign('faculty_id', 'halls_faculty_id_fk')
                    ->references('id')
                    ->on('faculties')
                    ->nullOnDelete();
            }

            if (! $hasNotes) {
                $table->text('notes')->nullable()->after(! $hasFacultyId ? 'faculty_id' : 'building_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('halls', function (Blueprint $table): void {
            if (Schema::hasColumn('halls', 'faculty_id')) {
                $table->dropForeign('halls_faculty_id_fk');
                $table->dropColumn('faculty_id');
            }

            foreach (['notes', 'building_name', 'hall_type', 'capacity'] as $column) {
                if (Schema::hasColumn('halls', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
