<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('pin_code')->nullable()->after('password');
            $table->boolean('pin_enabled')->default(true)->after('pin_code');
            $table->timestamp('pin_changed_at')->nullable()->after('pin_enabled');
        });

        DB::table('app_settings')->updateOrInsert(
            ['key' => 'enable_pin_login'],
            [
                'value' => '0',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('app_settings')->where('key', 'enable_pin_login')->delete();

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['pin_code', 'pin_enabled', 'pin_changed_at']);
        });
    }
};
