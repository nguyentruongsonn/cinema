<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->bigInteger('gateway_order_code');
            $table->bigInteger('user_id')->unsigned()->index();
            $table->bigInteger('showtime_id')->unsigned()->index();
            $table->decimal('total_amount', 15, 2);
            $table->longText('payload')->nullable();
            $table->tinyInteger('status')->default(1)
                ->comment('0: Đã hủy | 1: Chờ thanh toán | 2: Đã thanh toán | 3: Đã hoàn thành | 4: Đã hết hạn');
            $table->string('payment_provider')->nullable();
            $table->string('payment_status')->default('created')
                ->comment('created: Đã tạo | pending: Đang xử lý | completed: Hoàn thành | failed: Thất bại | cancelled: Đã hủy');
            $table->text('checkout_url')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Performance indexes
            $table->index('gateway_order_code', 'idx_orders_gateway_order_code');
            $table->index('status', 'idx_orders_status');
            $table->index('payment_status', 'idx_orders_payment_status');
            $table->index('expired_at', 'idx_orders_expired_at');
            $table->index(['showtime_id', 'status'], 'idx_orders_showtime_status');

            // Note: Foreign keys to users and showtimes will be added in a separate migration
            // after those tables are created (2026_06_09_000000_add_foreign_key_constraints.php)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
