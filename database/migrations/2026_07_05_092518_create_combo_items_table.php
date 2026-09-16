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
        Schema::create('combo_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('combo_id')
                ->constrained('combos')
                ->onDelete('cascade')
                ->comment('ID combo (từ bảng combos)');
            $table->foreignId('product_id')
                ->constrained('products')
                ->onDelete('restrict')
                ->comment('ID món đồ ăn/nước uống (từ bảng products)');
            $table->unsignedInteger('quantity')->default(1)->comment('Số lượng món này trong combo');
            $table->timestamps();

            // Indexes
            $table->index('combo_id');
            $table->index('product_id');

            // Unique constraint: một combo không chứa cùng 1 món 2 lần
            $table->unique(['combo_id', 'product_id'], 'uk_combo_product');
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('combo_items');
    }
};