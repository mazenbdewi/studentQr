<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            if (Schema::hasColumn('departments', 'total_students')) {
                $table->dropColumn('total_students');
            }
            if (Schema::hasColumn('departments', 'total_lectures')) {
                $table->dropColumn('total_lectures');
            }
        });
    }


    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->integer('total_students')->nullable();
            $table->integer('total_lectures')->nullable();
        });
    }
};
