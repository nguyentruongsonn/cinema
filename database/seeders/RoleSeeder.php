<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'description' => 'Administrator with full access',
            ],
            [
                'name' => 'Manager',
                'slug' => 'manager',
                'description' => 'Manager with limited admin access',
            ],
            [
                'name' => 'Staff',
                'slug' => 'staff',
                'description' => 'Staff member',
            ],
            [
                'name' => 'User',
                'slug' => 'user',
                'description' => 'Regular user',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['slug' => $role['slug']],
                $role
            );
        }

        $this->command->info('Roles seeded successfully!');
    }
}
