<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            CategorySeeder::class,
            FormatSeeder::class,
            VersionTypeSeeder::class,
            SeatTypeSeeder::class,
            UserSeeder::class,

            BranchSeeder::class,
            TheaterSeeder::class,

            SeatLayoutTemplateSeeder::class,
            ScreenSeeder::class,

            PromotionSeeder::class,
            ProductSeeder::class,
            ComboSeeder::class,      // Phải chạy sau ProductSeeder
            ComboItemSeeder::class,  // Phải chạy sau ComboSeeder

            MovieSeeder::class,

            ShowtimeSeeder::class,

            HolidaySeeder::class,
            DayRuleSeeder::class,
            TimeSlotSeeder::class,
        ]);

        $this->command->info('All seeders completed successfully!');
    }
}
