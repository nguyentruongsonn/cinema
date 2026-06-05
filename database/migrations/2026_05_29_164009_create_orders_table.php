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
            $table->tinyInteger('status')->default(1);
            $table->string('payment_provider')->nullable();
            $table->string('payment_status')->default('created');
            $table->text('checkout_url')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
