<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds missing columns to payments table that are expected by Payment model.
 * These columns support the enhanced payment audit trail and gateway integration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Add user_id for direct user reference (in addition to order->user relationship)
            $table->bigInteger('user_id')->unsigned()->nullable()->after('order_id')->index();
            
            // Add gateway_order_code for PayOS integration tracking
            $table->string('gateway_order_code')->nullable()->after('transaction_code');
            
            // Add failed_at timestamp for payment failure audit trail
            $table->timestamp('failed_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'gateway_order_code', 'failed_at']);
        });
    }
};