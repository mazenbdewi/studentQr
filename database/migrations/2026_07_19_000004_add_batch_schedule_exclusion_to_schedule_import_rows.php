<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_import_rows', function (Blueprint $table): void {
            $table->timestamp('excluded_from_weekly_schedule_at')->nullable()->after('resolution_updated_at')->index();
            $table->foreignId('excluded_from_weekly_schedule_by')
                ->nullable()
                ->after('excluded_from_weekly_schedule_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('exclusion_note')->nullable()->after('excluded_from_weekly_schedule_by');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_import_rows', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('excluded_from_weekly_schedule_by');
            $table->dropColumn(['excluded_from_weekly_schedule_at', 'exclusion_note']);
        });
    }
};
