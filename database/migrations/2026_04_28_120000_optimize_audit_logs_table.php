<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('audit_logs', 'category')) {
                $table->string('category')->nullable()->after('user_id');
            }

            if (! Schema::hasColumn('audit_logs', 'model_type')) {
                $table->string('model_type')->nullable()->after('action');
            }

            if (! Schema::hasColumn('audit_logs', 'model_id')) {
                $table->unsignedBigInteger('model_id')->nullable()->after('model_type');
            }

            if (! Schema::hasColumn('audit_logs', 'old_values')) {
                $table->json('old_values')->nullable()->after('description');
            }

            if (! Schema::hasColumn('audit_logs', 'new_values')) {
                $table->json('new_values')->nullable()->after('old_values');
            }

            if (! Schema::hasColumn('audit_logs', 'context')) {
                $table->json('context')->nullable()->after('new_values');
            }

            if (! Schema::hasColumn('audit_logs', 'user_agent')) {
                $table->string('user_agent', 512)->nullable()->after('ip_address');
            }
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('action');
            $table->index('category');
            $table->index('model_type');
            $table->index('created_at');
            $table->index('ip_address');
            $table->index(['action', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['action']);
            $table->dropIndex(['category']);
            $table->dropIndex(['model_type']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['ip_address']);
            $table->dropIndex(['action', 'created_at']);
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['model_type', 'model_id']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            if (Schema::hasColumn('audit_logs', 'user_agent')) {
                $table->dropColumn('user_agent');
            }

            if (Schema::hasColumn('audit_logs', 'context')) {
                $table->dropColumn('context');
            }

            if (Schema::hasColumn('audit_logs', 'new_values')) {
                $table->dropColumn('new_values');
            }

            if (Schema::hasColumn('audit_logs', 'old_values')) {
                $table->dropColumn('old_values');
            }

            if (Schema::hasColumn('audit_logs', 'model_id')) {
                $table->dropColumn('model_id');
            }

            if (Schema::hasColumn('audit_logs', 'model_type')) {
                $table->dropColumn('model_type');
            }

            if (Schema::hasColumn('audit_logs', 'category')) {
                $table->dropColumn('category');
            }
        });
    }
};
