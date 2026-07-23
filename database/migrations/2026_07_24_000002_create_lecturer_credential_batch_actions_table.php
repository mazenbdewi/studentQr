<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::create('lecturer_credential_batch_actions', function (Blueprint $t): void { $t->id(); $t->foreignId('lecturer_credential_batch_id')->constrained()->cascadeOnDelete(); $t->string('action',32); $t->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete(); $t->ipAddress('request_ip')->nullable(); $t->json('safe_metadata')->nullable(); $t->timestamp('performed_at'); $t->timestamps(); }); } public function down(): void { Schema::dropIfExists('lecturer_credential_batch_actions'); } };
