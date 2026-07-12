<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // Independent seeders (no dependencies)
            RoleSeeder::class,
            PermissionSeeder::class,
            CategorySeeder::class,
            FormatSeeder::class,
            VersionTypeSeeder::class,
            SeatTypeSeeder::class,
            UserSeeder::class,

            // Branch and Theater hierarchy
            BranchSeeder::class,
            TheaterSeeder::class,

            // Screens and Seats (depends on Theater and SeatType)
            SeatLayoutTemplateSeeder::class,
            ScreenSeeder::class,

            // Promotions and concessions
            PromotionSeeder::class,
            ProductSeeder::class,
            ComboSeeder::class,      // Phải chạy sau ProductSeeder
            ComboItemSeeder::class,  // Phải chạy sau ComboSeeder

            // Movies (independent)
            MovieSeeder::class,

            // Showtimes (depends on Movie and Screen)
            ShowtimeSeeder::class,
        ]);

        $this->command->info('All seeders completed successfully!');
    }
}
