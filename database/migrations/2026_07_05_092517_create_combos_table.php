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
        Schema::create('combos', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('Tên combo');
            $table->decimal('price', 10, 2)->comment('Giá bán combo');
            $table->decimal('original_price', 10, 2)->default(0)->comment('Tổng giá gốc của các sản phẩm trong combo');
            $table->string('image_url')->nullable()->comment('URL hình ảnh combo');
            $table->text('description')->nullable()->comment('Mô tả combo');
            $table->boolean('status')->default(1)->comment('1: Đang bán, 0: Ngừng bán');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('status');
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('combos');
    }
};
