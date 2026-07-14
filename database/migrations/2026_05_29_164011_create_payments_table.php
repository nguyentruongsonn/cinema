    <?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('order_id')->unsigned()->index();
            $table->bigInteger('user_id')->unsigned()->nullable()->index();
            $table->string('method')
                ->comment('payos: PayOS Gateway | cash: Tiền mặt | momo: MoMo | vnpay: VNPay');
            $table->string('transaction_code')->nullable();
            $table->string('gateway_order_code')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('status')->default('pending')
                ->comment('pending: Đang chờ | completed: Hoàn thành | failed: Thất bại | refunded: Đã hoàn tiền | cancelled: Đã hủy');
            $table->longText('payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
