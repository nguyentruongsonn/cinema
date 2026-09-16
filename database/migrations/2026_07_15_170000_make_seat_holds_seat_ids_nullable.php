<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * PHASE 1 FIX: Make seat_ids nullable since the normalized seat_hold_items table
     * is now the source of truth for per-seat locking.
     */
    public function up(): void
    {
        Schema::table('seat_holds', function (Blueprint $table) {
            $table->longText('seat_ids')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seat_holds', function (Blueprint $table) {
            $table->longText('seat_ids')->nullable(false)->change();
        });
    }
};
