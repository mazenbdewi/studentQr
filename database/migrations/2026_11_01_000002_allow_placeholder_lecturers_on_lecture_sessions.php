<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lecture_sessions', function (Blueprint $table): void {
            $table->foreignId('lecturer_identity_id')
                ->nullable()
                ->after('lecturer_id')
                ->constrained('lecturers')
                ->restrictOnDelete();
        });

        DB::table('lecture_sessions')
            ->whereNotNull('subject_section_schedule_slot_id')
            ->whereNull('lecturer_identity_id')
            ->select(['id', 'subject_section_schedule_slot_id'])
            ->orderBy('id')
            ->chunkById(500, function ($sessions): void {
                $identityIds = DB::table('subject_section_schedule_slots')
                    ->whereIn('id', $sessions->pluck('subject_section_schedule_slot_id')->filter()->all())
                    ->pluck('lecturer_id', 'id');

                foreach ($sessions as $session) {
                    $identityId = $identityIds->get($session->subject_section_schedule_slot_id);

                    if ($identityId !== null) {
                        DB::table('lecture_sessions')
                            ->where('id', $session->id)
                            ->update(['lecturer_identity_id' => $identityId]);
                    }
                }
            });

        Schema::table('lecture_sessions', function (Blueprint $table): void {
            $table->foreignId('lecturer_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('lecture_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('lecturer_identity_id');
            $table->foreignId('lecturer_id')->nullable(false)->change();
        });
    }
};
