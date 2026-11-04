<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('temporary_password_encrypted')->nullable()->after('must_change_password');
            $table->timestamp('password_changed_at')->nullable()->after('temporary_password_encrypted');
            $table->timestamp('password_reset_at')->nullable()->after('password_changed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('temporary_password_encrypted');
            $table->dropColumn(['password_changed_at', 'password_reset_at']);
        });
    }
};
