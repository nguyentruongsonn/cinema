<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Add comments to fixed-value columns
     */
    public function up(): void
    {
        // Priority 1: Critical tables

        // 1. users table
        Schema::table('users', function (Blueprint $table) {
            $table->tinyInteger('status')->default(1)
                ->comment('0: Vô hiệu hóa | 1: Hoạt động | 2: Đang chờ xác thực email')->change();
            $table->enum('gender', ['male', 'female', 'other'])->nullable()
                ->comment('male: Nam | female: Nữ | other: Khác')->change();
        });

        // 2. orders table
        Schema::table('orders', function (Blueprint $table) {
            $table->tinyInteger('status')->default(1)
                ->comment('0: Đã hủy | 1: Chờ thanh toán | 2: Đã thanh toán | 3: Đã hoàn thành | 4: Đã hết hạn')->change();
            $table->string('payment_status')->default('created')
                ->comment('created: Mới tạo | pending: Chờ thanh toán | processing: Đang xử lý | paid: Đã thanh toán | failed: Thất bại | cancelled: Đã hủy | refunded: Đã hoàn tiền')->change();
        });

        // 3. payments table
        Schema::table('payments', function (Blueprint $table) {
            $table->string('status')->default('pending')
                ->comment('pending: Chờ xử lý | completed: Hoàn thành | failed: Thất bại | refunded: Đã hoàn tiền | cancelled: Đã hủy')->change();
            $table->string('method')
                ->comment('payos: PayOS Gateway | vnpay: VNPay | momo: MoMo | cash: Tiền mặt | bank_transfer: Chuyển khoản')->change();
        });

        // 4. products table
        Schema::table('products', function (Blueprint $table) {
            $table->tinyInteger('status')->default(1)
                ->comment('0: Ngừng bán | 1: Đang bán | 2: Hết hàng tạm thời')->change();
            $table->string('type')->nullable()
                ->comment('food: Đồ ăn | drink: Nước uống | snack: Snack | merchandise: Hàng lưu niệm')->change();
        });

        // 5. promotions table
        Schema::table('promotions', function (Blueprint $table) {
            $table->tinyInteger('status')->default(1)
                ->comment('0: Vô hiệu hóa | 1: Đang hoạt động | 2: Đã hết hạn | 3: Đã hết lượt sử dụng')->change();
            $table->enum('discount_type', ['percentage', 'fixed_amount'])
                ->comment('percentage: Giảm theo % | fixed_amount: Giảm số tiền cố định')->change();
        });

        // 6. movies table
        Schema::table('movies', function (Blueprint $table) {
            $table->tinyInteger('status')->default(1)
                ->comment('0: Ngừng chiếu | 1: Đang chiếu | 2: Sắp chiếu | 3: Đã kết thúc')->change();
            $table->tinyInteger('is_hidden')->default(0)
                ->comment('0: Hiển thị công khai | 1: Ẩn khỏi danh sách')->change();
        });

        // 7. tickets table
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('status', 20)->default('valid')
                ->comment('valid: Có hiệu lực | used: Đã sử dụng | expired: Hết hạn | cancelled: Đã hủy | refunded: Đã hoàn tiền')->change();
        });

        // Priority 2: Supporting tables

        // 8. categories table
        Schema::table('categories', function (Blueprint $table) {
            $table->tinyInteger('status')->default(1)
                ->comment('0: Vô hiệu hóa | 1: Hoạt động')->change();
        });

        // 9. screens table
        Schema::table('screens', function (Blueprint $table) {
            $table->tinyInteger('status')->default(1)
                ->comment('0: Ngừng hoạt động | 1: Đang hoạt động | 2: Bảo trì')->change();
        });

        // 10. seats table
        Schema::table('seats', function (Blueprint $table) {
            $table->tinyInteger('status')->default(1)
                ->comment('0: Hỏng/không sử dụng | 1: Sẵn sàng | 2: Bảo trì')->change();
        });

        // 11. showtimes table
        Schema::table('showtimes', function (Blueprint $table) {
            $table->tinyInteger('status')->default(1)
                ->comment('0: Đã hủy | 1: Sẵn sàng bán vé | 2: Hết vé | 3: Đã chiếu')->change();
        });

        // 12. theaters table
        Schema::table('theaters', function (Blueprint $table) {
            $table->tinyInteger('status')->default(1)
                ->comment('0: Đóng cửa | 1: Đang hoạt động | 2: Bảo trì')->change();
        });

        // 13. branches table
        Schema::table('branches', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)
                ->comment('false: Không hoạt động | true: Đang hoạt động')->change();
        });

        // 14. banners table
        Schema::table('banners', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)
                ->comment('false: Ẩn | true: Hiển thị')->change();
        });

        // 15. seat_layout_templates table
        Schema::table('seat_layout_templates', function (Blueprint $table) {
            $table->boolean('status')->default(true)
                ->comment('false: Vô hiệu hóa | true: Sử dụng được')->change();
        });

        // 16. price_rules table
        Schema::table('price_rules', function (Blueprint $table) {
            $table->tinyInteger('status')->default(1)
                ->comment('0: Vô hiệu hóa | 1: Đang áp dụng')->change();
            $table->string('day_type')
                ->comment('weekday: Ngày thường | weekend: Cuối tuần | holiday: Ngày lễ | special: Ngày đặc biệt')->change();
        });

        // 17. login_histories table
        Schema::table('login_histories', function (Blueprint $table) {
            $table->string('login_method', 30)->default('email')
                ->comment('email: Đăng nhập email | username: Đăng nhập username | google: Google OAuth | facebook: Facebook OAuth')->change();
            $table->boolean('success')->default(true)
                ->comment('false: Đăng nhập thất bại | true: Đăng nhập thành công')->change();
        });

        // 18. order_items table (polymorphic)
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('item_type')
                ->comment('App\\Models\\Ticket: Vé xem phim | App\\Models\\Product: Sản phẩm | App\\Models\\Combo: Combo')->change();
        });

        // 19. idempotency_keys table
        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->string('status')->default('pending')
                ->comment('pending: Đang xử lý | completed: Hoàn thành | failed: Thất bại')->change();
        });
    }

    /**
     * Reverse the migrations - Remove comments
     */
    public function down(): void
    {
        // Priority 1: Critical tables

        Schema::table('users', function (Blueprint $table) {
            $table->tinyInteger('status')->default(1)->change();
            $table->enum('gender', ['male', 'female', 'other'])->nullable()->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->tinyInteger('status')->default(1)->change();
            $table->string('payment_status')->default('created')->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
            $table->string('method')->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->tinyInteger('status')->default(1)->change();
            $table->string('type')->nullable()->change();
        });

        Schema::table('promotions', function (Blueprint $table) {
            $table->tinyInteger('status')->default(1)->change();
            $table->enum('discount_type', ['percentage', 'fixed_amount'])->change();
        });

        Schema::table('movies', function (Blueprint $table) {
            $table->tinyInteger('status')->default(1)->change();
            $table->tinyInteger('is_hidden')->default(0)->change();
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->string('status', 20)->default('valid')->change();
        });

        // Priority 2: Supporting tables

        Schema::table('categories', function (Blueprint $table) {
            $table->tinyInteger('status')->default(1)->change();
        });

        Schema::table('screens', function (Blueprint $table) {
            $table->tinyInteger('status')->default(1)->change();
        });

        Schema::table('seats', function (Blueprint $table) {
            $table->tinyInteger('status')->default(1)->change();
        });

        Schema::table('showtimes', function (Blueprint $table) {
            $table->tinyInteger('status')->default(1)->change();
        });

        Schema::table('theaters', function (Blueprint $table) {
            $table->tinyInteger('status')->default(1)->change();
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->change();
        });

        Schema::table('banners', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->change();
        });

        Schema::table('seat_layout_templates', function (Blueprint $table) {
            $table->boolean('status')->default(true)->change();
        });

        Schema::table('price_rules', function (Blueprint $table) {
            $table->tinyInteger('status')->default(1)->change();
            $table->string('day_type')->change();
        });

        Schema::table('login_histories', function (Blueprint $table) {
            $table->string('login_method', 30)->default('email')->change();
            $table->boolean('success')->default(true)->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('item_type')->change();
        });

        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });
    }
};
