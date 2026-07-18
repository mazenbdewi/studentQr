<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lecturers', function (Blueprint $table): void {
            $table->string('lecturer_id')->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->string('canonical_name')->nullable()->after('name')->index();
            $table->foreignId('user_id')
                ->nullable()
                ->unique()
                ->after('id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('lecturers')->whereNull('lecturer_id')->orWhereNull('email')->exists()) {
            throw new RuntimeException(
                'Cannot restore non-null lecturer identity fields while imported identity-only lecturers exist.',
            );
        }

        Schema::table('lecturers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
            $table->dropIndex(['canonical_name']);
            $table->dropColumn('canonical_name');
            $table->string('lecturer_id')->nullable(false)->change();
            $table->string('email')->nullable(false)->change();
        });
    }
};
