<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('description')->nullable();
            $table->enum('discount_type', ['percentage', 'fixed_amount']);
            $table->decimal('discount_value', 15, 2);
            $table->decimal('min_order_value', 15, 2)->default(0.00);
            $table->decimal('max_discount_amount', 15, 2)->nullable();
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_count')->default(0);
            $table->integer('daily_usage_limit')->nullable();
            $table->tinyInteger('status')->default(1)
                ->comment('0: Vô hiệu hóa | 1: Đang hoạt động | 2: Hết hạn');
            $table->timestamps();

            // Performance indexes
            $table->index('code', 'idx_promotions_code');
            $table->index('status', 'idx_promotions_status');
            $table->index(['status', 'start_date', 'end_date'], 'idx_promotions_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
