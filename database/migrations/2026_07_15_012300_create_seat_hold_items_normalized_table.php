<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration implements Phase 1.1 of the remediation plan:
     * - Normalizes seat_ids from JSON array to individual lockable rows
     * - Adds a nullable unique active_lock_key to prevent duplicate active seat holds
     * - Enables row-level database locking for atomic seat reservation
     * - Prevents race conditions and double-booking scenarios
     */
    public function up(): void
    {
        Schema::create('seat_hold_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seat_hold_id')
                ->constrained('seat_holds')
                ->onDelete('cascade');
            $table->foreignId('showtime_id')
                ->constrained('showtimes')
                ->onDelete('cascade');
            $table->foreignId('seat_id')
                ->constrained('seats')
                ->onDelete('cascade');
            $table->enum('status', ['active', 'expired', 'consumed'])
                ->default('active')
                ->comment('active=currently held, expired=auto-released, consumed=converted to order');
            $table->string('active_lock_key', 80)
                ->nullable()
                ->comment('Unique showtime:seat key while status is active; null after release/expire/consume');
            $table->timestamp('expires_at')
                ->comment('When this specific seat hold expires');
            $table->timestamps();

            // Critical: active_lock_key is only populated for active rows.
            // MySQL allows multiple NULL values in a unique index, so expired/consumed
            // rows remain historical while active rows are uniquely constrained.
            $table->unique('active_lock_key', 'seat_hold_items_active_lock_key_unique');

            // Performance indexes for common queries
            $table->index(['seat_hold_id'], 'seat_hold_items_hold_id_index');
            $table->index(['showtime_id', 'seat_id'], 'seat_hold_items_showtime_seat_index');
            $table->index(['showtime_id', 'status', 'expires_at'], 'seat_hold_items_showtime_status_expires_index');
            $table->index(['expires_at'], 'seat_hold_items_expires_at_index');
            $table->index(['status'], 'seat_hold_items_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seat_hold_items');
    }
};
