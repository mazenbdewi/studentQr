<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'pin_changed_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('pin_changed_at')->nullable()->after('pin_enabled');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'pin_changed_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('pin_changed_at');
            });
        }
    }
};
