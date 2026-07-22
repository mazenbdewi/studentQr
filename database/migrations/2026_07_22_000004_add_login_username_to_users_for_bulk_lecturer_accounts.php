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
            if (! Schema::hasColumn('users', 'login_username')) {
                $table->string('login_username', 64)->nullable()->after('email');
                $table->unique('login_username', 'users_login_username_unique');
            }

            if (! Schema::hasColumn('users', 'must_change_password')) {
                $table->boolean('must_change_password')->default(false)->after('password');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NULL');

            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'email')
            && DB::table('users')->whereNull('email')->exists()) {
            throw new RuntimeException('Cannot roll back lecturer login schema: users with null email depend on nullable email/login_username. Assign real emails or remove those accounts before rollback.');
        }

        if (Schema::hasColumn('users', 'login_username')
            && DB::table('users')
                ->whereNotNull('login_username')
                ->where(function ($query): void {
                    $query->whereNull('email')->orWhere('email', '');
                })
                ->exists()) {
            throw new RuntimeException('Cannot drop login_username: username-only lecturer accounts depend on it for login.');
        }

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'login_username')) {
                $table->dropUnique('users_login_username_unique');
                $table->dropColumn('login_username');
            }

            if (Schema::hasColumn('users', 'must_change_password')) {
                $table->dropColumn('must_change_password');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NOT NULL');

            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable(false)->change();
        });
    }
};
