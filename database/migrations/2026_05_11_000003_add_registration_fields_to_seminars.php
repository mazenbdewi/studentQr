<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seminars', function (Blueprint $table) {
            $table->boolean('collect_specialization')->default(true)->after('description');
            $table->boolean('collect_profession')->default(true)->after('collect_specialization');
            $table->boolean('collect_academic_rank')->default(true)->after('collect_profession');
            $table->boolean('collect_age')->default(false)->after('collect_academic_rank');
            $table->boolean('collect_organization')->default(false)->after('collect_age');
            $table->boolean('collect_phone')->default(false)->after('collect_organization');
            $table->boolean('collect_email')->default(false)->after('collect_phone');
            $table->boolean('collect_notes')->default(false)->after('collect_email');
        });

        Schema::table('seminar_attendances', function (Blueprint $table) {
            $table->string('profession')->nullable()->after('specialization');
            $table->string('academic_rank')->nullable()->after('profession');
            $table->unsignedTinyInteger('age')->nullable()->after('academic_rank');
        });
    }

    public function down(): void
    {
        Schema::table('seminar_attendances', function (Blueprint $table) {
            $table->dropColumn(['profession', 'academic_rank', 'age']);
        });

        Schema::table('seminars', function (Blueprint $table) {
            $table->dropColumn([
                'collect_specialization',
                'collect_profession',
                'collect_academic_rank',
                'collect_age',
                'collect_organization',
                'collect_phone',
                'collect_email',
                'collect_notes',
            ]);
        });
    }
};
