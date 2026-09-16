<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add request tracking, proper indexes, and align schema with model expectations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('audit_logs', 'model_type') && !Schema::hasColumn('audit_logs', 'auditable_type')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->renameColumn('model_type', 'auditable_type');
            });
        }

        if (Schema::hasColumn('audit_logs', 'model_id') && !Schema::hasColumn('audit_logs', 'auditable_id')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->renameColumn('model_id', 'auditable_id');
            });
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('audit_logs', 'old_values')) {
                $table->json('old_values')->nullable()->after('action');
            }

            if (!Schema::hasColumn('audit_logs', 'new_values')) {
                $table->json('new_values')->nullable()->after('old_values');
            }

            if (!Schema::hasColumn('audit_logs', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('user_id');
            }

            if (!Schema::hasColumn('audit_logs', 'user_agent')) {
                $table->string('user_agent', 500)->nullable()->after('ip_address');
            }

            if (!Schema::hasColumn('audit_logs', 'request_id')) {
                $table->string('request_id', 36)->nullable()->after('user_agent');
            }
        });

        // Add performance indexes for common audit queries.
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('action', 'audit_logs_action_index');
            $table->index(['auditable_type', 'auditable_id'], 'audit_logs_auditable_index');
            $table->index(['user_id', 'created_at'], 'audit_logs_user_created_index');
            $table->index(['auditable_type', 'auditable_id', 'created_at'], 'audit_logs_auditable_created_index');
            $table->index('request_id', 'audit_logs_request_id_index');
        });
    }

    /**
     * Reverse the audit log enhancements.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_action_index');
            $table->dropIndex('audit_logs_auditable_index');
            $table->dropIndex('audit_logs_user_created_index');
            $table->dropIndex('audit_logs_auditable_created_index');

            if (Schema::hasColumn('audit_logs', 'request_id')) {
                $table->dropIndex('audit_logs_request_id_index');
                $table->dropColumn('request_id');
            }
        });
        
        // Note: Not reversing column renames or additions to preserve data
        // Manual intervention required if full rollback needed
    }
};