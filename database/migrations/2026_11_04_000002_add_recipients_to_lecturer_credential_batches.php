<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lecturer_credential_batches', function (Blueprint $table): void {
            $table->json('recipient_user_ids')->nullable()->after('record_count');
        });
    }

    public function down(): void
    {
        Schema::table('lecturer_credential_batches', function (Blueprint $table): void {
            $table->dropColumn('recipient_user_ids');
        });
    }
};
