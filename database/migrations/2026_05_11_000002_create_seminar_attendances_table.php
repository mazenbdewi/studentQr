<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seminar_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seminar_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('specialization')->nullable();
            $table->string('professional_title')->nullable();
            $table->string('organization')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->timestamp('attended_at');
            $table->string('ip_address', 45)->nullable();
            $table->string('device_fingerprint', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['seminar_id', 'attended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seminar_attendances');
    }
};
