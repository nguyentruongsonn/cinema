<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            // Unique constraint on 'key' is handled in 2026_07_15_123000_add_phase1_payment_booking_constraints
            // to avoid migration order conflicts and to include duplicate data cleanup
            
            // Add request metadata for debugging and auditing
            $table->string('request_path', 255)->nullable()->after('key');
            $table->string('request_method', 10)->nullable()->after('request_path');
            
            // Add response HTTP status code
            $table->unsignedSmallInteger('response_status')->nullable()->after('response_data');
            
            // Add relationship to Payment model
            $table->foreignId('payment_id')->nullable()->after('user_id')->constrained('payments')->nullOnDelete();
            
            // Add indexes for common queries
            $table->index('expires_at');
            $table->index(['user_id', 'created_at']);
            $table->index('payment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            // Drop foreign key before the payment_id index it depends on
            $table->dropForeign(['payment_id']);

            // Drop indexes first
            $table->dropIndex(['expires_at']);
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['payment_id']);
            
            // Drop columns
            $table->dropColumn([
                'request_path',
                'request_method',
                'response_status',
                'payment_id',
            ]);
            
            // Unique constraint drop is handled in add_phase1_payment_booking_constraints migration
        });
    }
};
