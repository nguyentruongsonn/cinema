<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Admin User',
                'username' => 'admin',
                'email' => 'admin@example.com',
                'password' => bcrypt('password'),
                'phone' => '0123456789',
                'status' => 1,
            ],
            [
                'name' => 'Test User',
                'username' => 'test',
                'email' => 'test@example.com',
                'password' => bcrypt('password'),
                'phone' => '0987654321',
                'status' => 1,
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                $user
            );
        }

        $this->command->info('Users seeded successfully!');
    }
}
