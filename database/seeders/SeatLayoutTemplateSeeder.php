<?php

namespace Database\Seeders;

use App\Models\SeatLayoutTemplate;
use Illuminate\Database\Seeder;

class SeatLayoutTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'template_name' => '2D Standard',
                'seat_matrix' => 'A=11111, B=11111, C=11111, D=11111',
                'regular_seat_rows' => 4,
                'vip_seat_rows' => 0,
                'couple_seat_rows' => 0,
                'custom_matrix' => null,
                'description' => 'Mẫu sơ đồ ghế tiêu chuẩn cho phòng chiếu 2D.',
                'status' => true,
            ],
            [
                'template_name' => 'VIP Deluxe',
                'seat_matrix' => 'A=11111, B=11111, C=11111, D=11111, E=11111',
                'regular_seat_rows' => 3,
                'vip_seat_rows' => 2,
                'couple_seat_rows' => 0,
                'custom_matrix' => null,
                'description' => 'Mẫu sơ đồ ghế VIP với hàng ghế đặc biệt.',
                'status' => true,
            ],
            [
                'template_name' => 'Couple Premium',
                'seat_matrix' => 'A=11011, B=11011, C=11011, D=11011',
                'regular_seat_rows' => 2,
                'vip_seat_rows' => 0,
                'couple_seat_rows' => 2,
                'custom_matrix' => null,
                'description' => 'Mẫu sơ đồ ghế đôi dành cho phòng chiếu premium.',
                'status' => false,
            ],
        ];

        foreach ($templates as $template) {
            SeatLayoutTemplate::updateOrCreate(
                ['template_name' => $template['template_name']],
                $template
            );
        }

        $this->command->info('Seat layout templates seeded successfully!');
    }
}
