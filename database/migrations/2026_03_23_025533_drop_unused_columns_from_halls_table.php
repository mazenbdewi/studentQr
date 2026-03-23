<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('halls', function (Blueprint $table) {
            $table->dropColumn([
                'capacity',
                'has_projector',
                'has_computer',
                'network_ssid',
                'ip_range_start',
                'ip_range_end',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('halls', function (Blueprint $table) {
            $table->integer('capacity')->nullable();
            $table->boolean('has_projector')->default(false);
            $table->boolean('has_computer')->default(false);
            $table->string('network_ssid')->nullable();
            $table->string('ip_range_start')->nullable();
            $table->string('ip_range_end')->nullable();
        });
    }
};