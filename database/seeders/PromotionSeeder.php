<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PromotionSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $promotions = [
            [
                'code' => 'WELCOME10',
                'name' => 'Chào mừng thành viên mới',
                'category' => 'member',
                'description' => 'Giảm 10% cho đơn hàng đầu tiên từ 100.000đ.',
                'discount_type' => 'percentage',
                'discount_value' => 10,
                'min_order_value' => 100000,
                'max_discount_amount' => 30000,
                'start_date' => $now->copy()->subDays(7),
                'end_date' => $now->copy()->addMonths(3),
                'usage_limit' => 1000,
                'usage_count' => 0,
                'daily_usage_limit' => 100,
                'status' => 1,
            ],
            [
                'code' => 'CINEMA20',
                'name' => 'Ưu đãi đặt vé online',
                'category' => 'ticket',
                'description' => 'Giảm 20% tối đa 50.000đ cho vé xem phim đặt online.',
                'discount_type' => 'percentage',
                'discount_value' => 20,
                'min_order_value' => 150000,
                'max_discount_amount' => 50000,
                'start_date' => $now->copy()->subDays(3),
                'end_date' => $now->copy()->addMonths(2),
                'usage_limit' => 500,
                'usage_count' => 0,
                'daily_usage_limit' => 50,
                'status' => 1,
            ],
            [
                'code' => 'COMBO30K',
                'name' => 'Giảm combo bắp nước',
                'category' => 'combo',
                'description' => 'Giảm trực tiếp 30.000đ cho đơn hàng có combo từ 200.000đ.',
                'discount_type' => 'fixed_amount',
                'discount_value' => 30000,
                'min_order_value' => 200000,
                'max_discount_amount' => null,
                'start_date' => $now->copy()->subDays(1),
                'end_date' => $now->copy()->addMonth(),
                'usage_limit' => 300,
                'usage_count' => 0,
                'daily_usage_limit' => 30,
                'status' => 1,
            ],
            [
                'code' => 'STUDENT15',
                'name' => 'Ưu đãi học sinh sinh viên',
                'category' => 'student',
                'description' => 'Giảm 15% tối đa 40.000đ khi xuất trình thẻ học sinh/sinh viên.',
                'discount_type' => 'percentage',
                'discount_value' => 15,
                'min_order_value' => 120000,
                'max_discount_amount' => 40000,
                'start_date' => $now->copy()->subMonth(),
                'end_date' => $now->copy()->addMonths(6),
                'usage_limit' => 800,
                'usage_count' => 0,
                'daily_usage_limit' => 80,
                'status' => 1,
            ],
            [
                'code' => 'WEEKDAY25K',
                'name' => 'Rạp vui ngày thường',
                'category' => 'weekday',
                'description' => 'Giảm 25.000đ cho đơn từ 2 vé vào thứ 2 đến thứ 5.',
                'discount_type' => 'fixed_amount',
                'discount_value' => 25000,
                'min_order_value' => 160000,
                'max_discount_amount' => null,
                'start_date' => $now->copy()->subDays(10),
                'end_date' => $now->copy()->addMonths(4),
                'usage_limit' => 600,
                'usage_count' => 0,
                'daily_usage_limit' => 60,
                'status' => 1,
            ],
            [
                'code' => 'EXPIRED50',
                'name' => 'Mã hết hạn dùng để test',
                'category' => 'test',
                'description' => 'Promotion đã hết hạn để kiểm tra logic validate mã giảm giá.',
                'discount_type' => 'percentage',
                'discount_value' => 50,
                'min_order_value' => 100000,
                'max_discount_amount' => 100000,
                'start_date' => $now->copy()->subMonths(3),
                'end_date' => $now->copy()->subMonth(),
                'usage_limit' => 100,
                'usage_count' => 0,
                'daily_usage_limit' => 10,
                'status' => 0,
            ],
        ];

        foreach ($promotions as $promotion) {
            DB::table('promotions')->updateOrInsert(
                ['code' => $promotion['code']],
                array_merge($promotion, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }

        $this->command->info('Promotions seeded successfully!');
    }
}
