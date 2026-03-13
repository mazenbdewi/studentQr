<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('halls', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
             $table->integer('floor');
            $table->integer('capacity');
            $table->boolean('has_projector')->default(false);
            $table->boolean('has_computer')->default(false);
            $table->string('network_ssid')->nullable();
            $table->string('ip_range_start')->nullable();
            $table->string('ip_range_end')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('halls');
    }
};
