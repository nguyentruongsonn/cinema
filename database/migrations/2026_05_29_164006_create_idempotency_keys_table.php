<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->bigInteger('user_id')->unsigned()->nullable()->index();
            $table->longText('request_data')->nullable();
            $table->longText('response_data')->nullable();
            $table->string('status')->default('pending')
                ->comment('pending: Đang xử lý | completed: Hoàn thành | failed: Thất bại');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
