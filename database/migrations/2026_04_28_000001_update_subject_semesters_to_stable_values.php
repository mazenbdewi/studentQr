<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subjects')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE subjects MODIFY credit_hours TINYINT NULL");
            DB::statement("ALTER TABLE subjects MODIFY level TINYINT NULL");
            DB::statement("ALTER TABLE subjects MODIFY semester VARCHAR(20) NOT NULL DEFAULT 'first'");

            DB::table('subjects')->where('semester', '1')->update(['semester' => 'first']);
            DB::table('subjects')->where('semester', '2')->update(['semester' => 'second']);
            DB::table('subjects')->where('semester', '3')->update(['semester' => 'summer']);
        }

        if (Schema::hasTable('enrollments') && $driver === 'mysql') {
            DB::statement('ALTER TABLE enrollments MODIFY semester VARCHAR(20) NULL');

            DB::table('enrollments')->where('semester', '1')->update(['semester' => 'first']);
            DB::table('enrollments')->where('semester', '2')->update(['semester' => 'second']);
            DB::table('enrollments')->where('semester', '3')->update(['semester' => 'summer']);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('subjects')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::table('subjects')->where('semester', 'first')->update(['semester' => '1']);
            DB::table('subjects')->where('semester', 'second')->update(['semester' => '2']);
            DB::table('subjects')->where('semester', 'summer')->update(['semester' => '3']);

            DB::statement('ALTER TABLE subjects MODIFY credit_hours TINYINT NOT NULL');
            DB::statement('ALTER TABLE subjects MODIFY level TINYINT NOT NULL');
            DB::statement('ALTER TABLE subjects MODIFY semester TINYINT NOT NULL');
        }

        if (Schema::hasTable('enrollments') && $driver === 'mysql') {
            DB::table('enrollments')->where('semester', 'first')->update(['semester' => '1']);
            DB::table('enrollments')->where('semester', 'second')->update(['semester' => '2']);
            DB::table('enrollments')->where('semester', 'summer')->update(['semester' => '3']);

            DB::statement('ALTER TABLE enrollments MODIFY semester TINYINT NULL');
        }
    }
};
