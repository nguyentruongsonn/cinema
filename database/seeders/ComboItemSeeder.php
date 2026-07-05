<?php

namespace Database\Seeders;

use App\Models\Combo;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ComboItemSeeder extends Seeder
{
    public function run(): void
    {
        // Lấy ID các sản phẩm
        $bapM = Product::where('name', 'Bắp rang bơ size M')->first();
        $bapL = Product::where('name', 'Bắp rang bơ size L')->first();
        $bapPhomaiL = Product::where('name', 'Bắp phô mai size L')->first();
        $cocaM = Product::where('name', 'Coca Cola size M')->first();
        $cocaL = Product::where('name', 'Coca Cola size L')->first();
        $snack = Product::where('name', 'Snack khoai tây')->first();

        // Lấy ID các combo
        $comboSolo = Combo::where('name', 'Combo Solo')->first();
        $comboCouple = Combo::where('name', 'Combo Couple')->first();
        $comboFamily = Combo::where('name', 'Combo Family')->first();
        $comboVIP = Combo::where('name', 'Combo VIP')->first();

        if (!$bapM || !$bapL || !$bapPhomaiL || !$cocaM || !$cocaL || !$snack) {
            $this->command->error('Không tìm thấy đủ sản phẩm cần thiết. Hãy chạy ProductSeeder trước.');
            return;
        }

        if (!$comboSolo || !$comboCouple || !$comboFamily || !$comboVIP) {
            $this->command->error('Không tìm thấy đủ combo. Hãy chạy ComboSeeder trước.');
            return;
        }

        $now = now();
        $comboItems = [];

        // Combo Solo: 1 bắp M + 1 nước M
        $comboItems[] = [
            'combo_id' => $comboSolo->id,
            'product_id' => $bapM->id,
            'quantity' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $comboItems[] = [
            'combo_id' => $comboSolo->id,
            'product_id' => $cocaM->id,
            'quantity' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        // Combo Couple: 1 bắp L + 2 nước M
        $comboItems[] = [
            'combo_id' => $comboCouple->id,
            'product_id' => $bapL->id,
            'quantity' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $comboItems[] = [
            'combo_id' => $comboCouple->id,
            'product_id' => $cocaM->id,
            'quantity' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        // Combo Family: 2 bắp L + 4 nước M
        $comboItems[] = [
            'combo_id' => $comboFamily->id,
            'product_id' => $bapL->id,
            'quantity' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $comboItems[] = [
            'combo_id' => $comboFamily->id,
            'product_id' => $cocaM->id,
            'quantity' => 4,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        // Combo VIP: 2 bắp phô mai L + 2 nước L + 1 snack
        $comboItems[] = [
            'combo_id' => $comboVIP->id,
            'product_id' => $bapPhomaiL->id,
            'quantity' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $comboItems[] = [
            'combo_id' => $comboVIP->id,
            'product_id' => $cocaL->id,
            'quantity' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $comboItems[] = [
            'combo_id' => $comboVIP->id,
            'product_id' => $snack->id,
            'quantity' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        // Xóa dữ liệu cũ trước khi insert
        DB::table('combo_items')->whereIn('combo_id', [
            $comboSolo->id,
            $comboCouple->id,
            $comboFamily->id,
            $comboVIP->id,
        ])->delete();

        // Insert dữ liệu mới
        DB::table('combo_items')->insert($comboItems);

        // Tính và cập nhật original_price cho mỗi combo
        $combos = [$comboSolo, $comboCouple, $comboFamily, $comboVIP];
        foreach ($combos as $combo) {
            $originalPrice = DB::table('combo_items')
                ->join('products', 'combo_items.product_id', '=', 'products.id')
                ->where('combo_items.combo_id', $combo->id)
                ->selectRaw('SUM(products.price * combo_items.quantity) as total')
                ->value('total');

            DB::table('combos')
                ->where('id', $combo->id)
                ->update(['original_price' => $originalPrice ?? 0]);

            $this->command->info("Combo {$combo->name}: Original price = " . number_format($originalPrice ?? 0, 0, ',', '.') . ' VNĐ');
        }

        $this->command->info('Combo items seeded successfully!');
        $this->command->info('- Combo Solo: 1 bắp M + 1 coca M');
        $this->command->info('- Combo Couple: 1 bắp L + 2 coca M');
        $this->command->info('- Combo Family: 2 bắp L + 4 coca M');
        $this->command->info('- Combo VIP: 2 bắp phô mai L + 2 coca L + 1 snack');
    }
}
