<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('order_id')->unsigned()->index();
            $table->bigInteger('user_id')->unsigned()->index();
            $table->bigInteger('showtime_id')->unsigned()->index();
            $table->bigInteger('seat_id')->unsigned()->index();
            $table->string('ticket_code', 50)->unique();
            $table->text('qr_code')->nullable();
            $table->string('status', 20)->default('valid')->index()
                ->comment('valid: Có hiệu lực | used: Đã sử dụng | expired: Hết hạn | cancelled: Đã hủy | refunded: Đã hoàn tiền');
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            if (!app()->environment('testing')) {
                $table->foreign('order_id')
                    ->references('id')->on('orders')
                    ->onDelete('restrict')
                    ->onUpdate('cascade');

                $table->foreign('user_id')
                    ->references('id')->on('users')
                    ->onDelete('restrict')
                    ->onUpdate('cascade');

                $table->foreign('showtime_id')
                    ->references('id')->on('showtimes')
                    ->onDelete('restrict')
                    ->onUpdate('cascade');

                $table->foreign('seat_id')
                    ->references('id')->on('seats')
                    ->onDelete('restrict')
                    ->onUpdate('cascade');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
