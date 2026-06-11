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
        // Skip in test environment due to schema mismatches with SQLite
        if (app()->environment('testing')) {
            return;
        }

        // Orders table indexes
        Schema::table('orders', function (Blueprint $table) {
            $table->index('user_id', 'idx_orders_user_id');
            $table->index('gateway_order_code', 'idx_orders_gateway_order_code');
            $table->index('status', 'idx_orders_status');
            $table->index('payment_status', 'idx_orders_payment_status');
            $table->index('expired_at', 'idx_orders_expired_at');
            $table->index(['showtime_id', 'status'], 'idx_orders_showtime_status');
        });

        // Order items table indexes
        Schema::table('order_items', function (Blueprint $table) {
            $table->index('order_id', 'idx_order_items_order_id');
            $table->index(['item_type', 'item_id'], 'idx_order_items_polymorphic');
        });

        // Seat holds table indexes
        Schema::table('seat_holds', function (Blueprint $table) {
            $table->index('user_id', 'idx_seat_holds_user_id');
            $table->index('held_until', 'idx_seat_holds_held_until');
            $table->index(['showtime_id', 'user_id'], 'idx_seat_holds_showtime_user');
        });

        // Payments table indexes
        Schema::table('payments', function (Blueprint $table) {
            $table->index('order_id', 'idx_payments_order_id');
            $table->index('transaction_code', 'idx_payments_transaction_code');
            $table->index('status', 'idx_payments_status');
        });

        // Showtimes table indexes
        // TODO: Re-enable after verifying column names match table schema
        // Schema::table('showtimes', function (Blueprint $table) {
        //     $table->index('movie_id', 'idx_showtimes_movie_id');
        //     $table->index('screen_id', 'idx_showtimes_screen_id');
        //     $table->index('start_time', 'idx_showtimes_start_time');
        //     $table->index(['movie_id', 'start_time'], 'idx_showtimes_movie_start');
        // });

        // Refresh tokens table indexes
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->index('user_id', 'idx_refresh_tokens_user_id');
            $table->index('expires_at', 'idx_refresh_tokens_expires_at');
            $table->index('revoked_at', 'idx_refresh_tokens_revoked_at');
        });

        // Promotions table indexes
        Schema::table('promotions', function (Blueprint $table) {
            $table->index('code', 'idx_promotions_code');
            $table->index('status', 'idx_promotions_status');
            $table->index(['status', 'start_date', 'end_date'], 'idx_promotions_active');
        });

        // Seats table indexes
        Schema::table('seats', function (Blueprint $table) {
            $table->index('screen_id', 'idx_seats_screen_id');
            $table->index('seat_type_id', 'idx_seats_seat_type_id');
            $table->index(['screen_id', 'row', 'number'], 'idx_seats_location');
        });

        // Login histories table indexes
        Schema::table('login_histories', function (Blueprint $table) {
            $table->index('user_id', 'idx_login_histories_user_id');
            $table->index('created_at', 'idx_login_histories_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_user_id');
            $table->dropIndex('idx_orders_gateway_order_code');
            $table->dropIndex('idx_orders_status');
            $table->dropIndex('idx_orders_payment_status');
            $table->dropIndex('idx_orders_expired_at');
            $table->dropIndex('idx_orders_showtime_status');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('idx_order_items_order_id');
            $table->dropIndex('idx_order_items_polymorphic');
        });

        Schema::table('seat_holds', function (Blueprint $table) {
            $table->dropIndex('idx_seat_holds_user_id');
            $table->dropIndex('idx_seat_holds_held_until');
            $table->dropIndex('idx_seat_holds_showtime_user');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('idx_payments_order_id');
            $table->dropIndex('idx_payments_transaction_code');
            $table->dropIndex('idx_payments_status');
        });

        // Schema::table('showtimes', function (Blueprint $table) {
        //     $table->dropIndex('idx_showtimes_movie_id');
        //     $table->dropIndex('idx_showtimes_screen_id');
        //     $table->dropIndex('idx_showtimes_start_time');
        //     $table->dropIndex('idx_showtimes_movie_start');
        // });

        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->dropIndex('idx_refresh_tokens_user_id');
            $table->dropIndex('idx_refresh_tokens_expires_at');
            $table->dropIndex('idx_refresh_tokens_revoked_at');
        });

        Schema::table('promotions', function (Blueprint $table) {
            $table->dropIndex('idx_promotions_code');
            $table->dropIndex('idx_promotions_status');
            $table->dropIndex('idx_promotions_active');
        });

        Schema::table('seats', function (Blueprint $table) {
            $table->dropIndex('idx_seats_screen_id');
            $table->dropIndex('idx_seats_seat_type_id');
            $table->dropIndex('idx_seats_location');
        });

        Schema::table('login_histories', function (Blueprint $table) {
            $table->dropIndex('idx_login_histories_user_id');
            $table->dropIndex('idx_login_histories_created_at');
        });
    }
};
