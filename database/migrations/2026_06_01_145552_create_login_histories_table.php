<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_histories', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->unsigned()->index();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('device_type', 50)->nullable(); // mobile, tablet, desktop
            $table->string('platform', 100)->nullable();   // Windows, iOS, Android
            $table->string('browser', 100)->nullable();    // Chrome, Safari
            $table->string('login_method', 30)->default('email')
                ->comment('email: Đăng nhập email | username: Đăng nhập username | google: Google OAuth | facebook: Facebook OAuth');
            $table->boolean('success')->default(true)
                ->comment('false: Đăng nhập thất bại | true: Đăng nhập thành công');
            $table->string('failure_reason', 255)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('session_token', 100)->nullable();
            $table->timestamp('logged_in_at')->useCurrent();
            $table->timestamp('logged_out_at')->nullable();
            $table->timestamps();

            // Performance indexes
            $table->index('user_id', 'idx_login_histories_user_id');
            $table->index('created_at', 'idx_login_histories_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_histories');
    }
};
