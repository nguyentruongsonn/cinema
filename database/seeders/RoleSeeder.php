<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('rbac.roles', []) as $slug => $role) {
            Role::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $role['name'],
                    'display_name' => $role['display_name'] ?? $role['name'],
                    'description' => $role['description'] ?? null,
                ]
            );
        }

        $this->migrateLegacyUserRoles();

        $this->command->info('RBAC roles seeded successfully.');
    }

    private function migrateLegacyUserRoles(): void
    {
        foreach (config('rbac.legacy_role_map', []) as $legacySlug => $targetSlug) {
            $legacyRoleId = Role::query()->where('slug', $legacySlug)->value('id');
            $targetRoleId = Role::query()->where('slug', $targetSlug)->value('id');

            if ($legacyRoleId && $targetRoleId) {
                DB::table('users')
                    ->where('role_id', $legacyRoleId)
                    ->update(['role_id' => $targetRoleId]);
            }
        }
    }
}
