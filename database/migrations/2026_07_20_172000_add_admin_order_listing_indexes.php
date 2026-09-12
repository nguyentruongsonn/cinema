<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['created_at', 'id'], 'orders_admin_created_id_idx');
            $table->index(['payment_status', 'created_at', 'id'], 'orders_admin_payment_created_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_admin_payment_created_id_idx');
            $table->dropIndex('orders_admin_created_id_idx');
        });
    }
};
