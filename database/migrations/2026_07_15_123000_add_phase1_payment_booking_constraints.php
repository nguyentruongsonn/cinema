<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Phase 1 remediation constraints for payment/order/seat booking correctness.
     *
     * IMPORTANT:
     * 1. This migration checks for existing duplicates before adding constraints
     * 2. If duplicates are found, migration will fail with detailed error message
     * 3. Resolve duplicates manually before re-running
     */
    public function up(): void
    {
        // Preflight duplicate checks
        $this->checkForDuplicates();

        // Add unique constraints and indexes to idempotency_keys
        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->unique('key', 'idempotency_keys_key_unique');
        });

        // Add unique constraints and indexes to orders
        Schema::table('orders', function (Blueprint $table) {
            $table->unique('code', 'orders_code_unique');
            $table->unique('gateway_order_code', 'orders_gateway_order_code_unique');
            $table->index(['status', 'payment_status', 'expired_at'], 'orders_status_payment_status_expired_at_index');
            $table->index(['user_id', 'status'], 'orders_user_id_status_index');
            $table->index(['showtime_id', 'status'], 'orders_showtime_id_status_index');
        });

        // Add unique constraints and indexes to payments
        Schema::table('payments', function (Blueprint $table) {
            $table->unique('order_id', 'payments_order_id_unique');
            $table->unique('gateway_order_code', 'payments_gateway_order_code_unique');
            $table->index(['user_id', 'status'], 'payments_user_id_status_index');
            $table->index(['status', 'paid_at'], 'payments_status_paid_at_index');
        });

        // Add indexes to seat_holds
        Schema::table('seat_holds', function (Blueprint $table) {
            $table->index(['user_id', 'showtime_id', 'held_until'], 'seat_holds_user_showtime_expires_index');
            $table->index(['showtime_id', 'held_until'], 'seat_holds_showtime_expires_index');
        });

        // Add indexes to order_items
        Schema::table('order_items', function (Blueprint $table) {
            $table->index(['order_id', 'item_type', 'item_id'], 'order_items_order_type_item_index');
            $table->index(['item_type', 'item_id'], 'order_items_type_item_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_type_item_index');
            $table->dropIndex('order_items_order_type_item_index');
        });

        Schema::table('seat_holds', function (Blueprint $table) {
            $table->dropIndex('seat_holds_showtime_expires_index');
            $table->dropIndex('seat_holds_user_showtime_expires_index');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_status_paid_at_index');
            $table->dropIndex('payments_user_id_status_index');
            $table->dropUnique('payments_gateway_order_code_unique');
            $table->dropUnique('payments_order_id_unique');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_showtime_id_status_index');
            $table->dropIndex('orders_user_id_status_index');
            $table->dropIndex('orders_status_payment_status_expired_at_index');
            $table->dropUnique('orders_gateway_order_code_unique');
            $table->dropUnique('orders_code_unique');
        });

        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->dropUnique('idempotency_keys_key_unique');
        });
    }

    /**
     * Check for duplicates that would violate unique constraints.
     *
     * @throws \RuntimeException if duplicates are found
     */
    private function checkForDuplicates(): void
    {
        $errors = [];

        // Check for duplicate idempotency keys
        $duplicateKeys = DB::table('idempotency_keys')
            ->select('key', DB::raw('COUNT(*) as count'))
            ->groupBy('key')
            ->having('count', '>', 1)
            ->get();

        if ($duplicateKeys->isNotEmpty()) {
            $errors[] = "Duplicate idempotency keys found: " . $duplicateKeys->pluck('key')->implode(', ');
        }

        // Check for duplicate order codes
        $duplicateOrderCodes = DB::table('orders')
            ->select('code', DB::raw('COUNT(*) as count'))
            ->whereNotNull('code')
            ->groupBy('code')
            ->having('count', '>', 1)
            ->get();

        if ($duplicateOrderCodes->isNotEmpty()) {
            $errors[] = "Duplicate order codes found: " . $duplicateOrderCodes->pluck('code')->implode(', ');
        }

        // Check for duplicate gateway_order_code on orders
        $duplicateOrderGatewayCodes = DB::table('orders')
            ->select('gateway_order_code', DB::raw('COUNT(*) as count'))
            ->whereNotNull('gateway_order_code')
            ->groupBy('gateway_order_code')
            ->having('count', '>', 1)
            ->get();

        if ($duplicateOrderGatewayCodes->isNotEmpty()) {
            $errors[] = "Duplicate gateway order codes on orders: " . $duplicateOrderGatewayCodes->pluck('gateway_order_code')->implode(', ');
        }

        // Check for duplicate gateway_order_code on payments
        $duplicatePaymentGatewayCodes = DB::table('payments')
            ->select('gateway_order_code', DB::raw('COUNT(*) as count'))
            ->whereNotNull('gateway_order_code')
            ->groupBy('gateway_order_code')
            ->having('count', '>', 1)
            ->get();

        if ($duplicatePaymentGatewayCodes->isNotEmpty()) {
            $errors[] = "Duplicate gateway order codes on payments: " . $duplicatePaymentGatewayCodes->pluck('gateway_order_code')->implode(', ');
        }

        // Check for multiple payments per order
        $duplicatePayments = DB::table('payments')
            ->select('order_id', DB::raw('COUNT(*) as count'))
            ->whereNotNull('order_id')
            ->groupBy('order_id')
            ->having('count', '>', 1)
            ->get();

        if ($duplicatePayments->isNotEmpty()) {
            $errors[] = "Multiple payments found for orders: " . $duplicatePayments->pluck('order_id')->implode(', ');
        }

        // Check for duplicate booked seats (same seat for same showtime in confirmed/pending orders)
        $duplicateSeats = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->select('o.showtime_id', 'oi.item_id as seat_id', DB::raw('COUNT(*) as count'))
            ->where('oi.item_type', 'App\\Models\\Seat')
            ->whereIn('o.status', [1, 2]) // Assuming 1=pending, 2=confirmed
            ->groupBy('o.showtime_id', 'oi.item_id')
            ->having('count', '>', 1)
            ->get();

        if ($duplicateSeats->isNotEmpty()) {
            $duplicateInfo = $duplicateSeats->map(function ($item) {
                return "showtime:{$item->showtime_id} seat:{$item->seat_id}";
            })->implode(', ');
            $errors[] = "Duplicate booked seats found: " . $duplicateInfo;
        }

        // If any errors found, throw exception with all details
        if (!empty($errors)) {
            $errorMessage = "Cannot apply constraints due to existing duplicates:\n\n" . implode("\n", $errors);
            $errorMessage .= "\n\nPlease resolve these duplicates before re-running the migration.";
            throw new \RuntimeException($errorMessage);
        }
    }
};
