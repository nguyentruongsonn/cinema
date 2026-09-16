<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('order_id')->unsigned()->index();
            $table->string('item_type')
                ->comment('App\\Models\\Ticket: Vé xem phim | App\\Models\\Product: Sản phẩm | App\\Models\\Combo: Combo');
            $table->bigInteger('item_id')->unsigned()->index();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('total_price', 15, 2);
            $table->longText('metadata')->nullable();
            $table->timestamps();

            // Performance indexes
            $table->index('order_id', 'idx_order_items_order_id');
            $table->index(['item_type', 'item_id'], 'idx_order_items_polymorphic');

            // Foreign key
            $table->foreign('order_id')
                ->references('id')->on('orders')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
