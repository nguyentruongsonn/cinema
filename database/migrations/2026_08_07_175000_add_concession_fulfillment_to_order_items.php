<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->string('fulfillment_status', 24)->default('pending')->after('metadata');
            $table->foreignId('fulfilled_by_user_id')->nullable()->after('fulfillment_status')->constrained('users')->nullOnDelete();
            $table->timestamp('fulfilled_at')->nullable()->after('fulfilled_by_user_id');
            $table->index(['fulfillment_status', 'created_at'], 'order_items_fulfillment_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropIndex('order_items_fulfillment_created_idx');
            $table->dropConstrainedForeignId('fulfilled_by_user_id');
            $table->dropColumn(['fulfillment_status', 'fulfilled_at']);
        });
    }
};
