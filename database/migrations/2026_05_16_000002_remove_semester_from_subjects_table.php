<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subjects') || ! Schema::hasColumn('subjects', 'semester')) {
            return;
        }

        Schema::table('subjects', function (Blueprint $table): void {
            $table->dropColumn('semester');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('subjects') || Schema::hasColumn('subjects', 'semester')) {
            return;
        }

        Schema::table('subjects', function (Blueprint $table): void {
            $table->string('semester', 20)->default('first')->after('level');
        });
    }
};
