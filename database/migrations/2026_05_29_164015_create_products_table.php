<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->nullable()
                ->comment('food: Đồ ăn | drink: Nước uống | snack: Snack | merchandise: Hàng lưu niệm');
            $table->decimal('price', 15, 2);
            $table->integer('stock')->default(0);
            $table->string('image_url')->nullable();
            $table->text('description')->nullable();
            $table->tinyInteger('status')->default(1)
                ->comment('0: Ngừng bán | 1: Đang bán | 2: Hết hàng tạm thời');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
