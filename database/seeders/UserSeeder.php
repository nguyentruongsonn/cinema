<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use RuntimeException;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('UserSeeder contains development credentials and must not run in production.');
        }

        $roleIds = Role::query()
            ->whereIn('slug', ['admin', 'customer'])
            ->pluck('id', 'slug');

        if (! $roleIds->has('admin') || ! $roleIds->has('customer')) {
            throw new RuntimeException('RoleSeeder must run before UserSeeder.');
        }

        $users = [
            [
                'name' => 'Admin User',
                'username' => 'admin',
                'email' => 'admin@example.com',
                'password' => bcrypt('password'),
                'phone' => '0123456789',
                'status' => 1,
                'role_id' => (int) $roleIds['admin'],
            ],
            [
                'name' => 'Test User',
                'username' => 'test',
                'email' => 'test@example.com',
                'password' => bcrypt('password'),
                'phone' => '0987654321',
                'status' => 1,
                'role_id' => (int) $roleIds['customer'],
            ],
        ];

        foreach ($users as $attributes) {
            $roleId = $attributes['role_id'];
            unset($attributes['role_id']);

            $user = User::updateOrCreate(
                ['email' => $attributes['email']],
                $attributes
            );
            $user->assignRole($roleId);
        }

        $this->command->info('Users seeded successfully!');
    }
}
