<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lecture_sessions', function (Blueprint $table) {
            $table->boolean('qr_expired')->default(false)->after('qr_refresh_rate');
        });
    }

    public function down(): void
    {
        Schema::table('lecture_sessions', function (Blueprint $table) {
            $table->dropColumn('qr_expired');
        });
    }
};