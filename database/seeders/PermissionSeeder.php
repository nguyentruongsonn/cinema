<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedPermissions();
            $this->syncRolePermissions();
        });

        $this->command->info('RBAC permissions seeded successfully.');
    }

    private function seedPermissions(): void
    {
        foreach (config('rbac.permissions', []) as $group => $permissions) {
            foreach ($permissions as $slug => $name) {
                Permission::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => $name,
                        'group' => $group,
                        'description' => null,
                    ]
                );
            }
        }

        foreach (config('rbac.permission_aliases', []) as $legacySlug => $canonicalSlug) {
            $canonical = Permission::where('slug', $canonicalSlug)->first();

            Permission::updateOrCreate(
                ['slug' => $legacySlug],
                [
                    'name' => $canonical?->name ?? str($legacySlug)->replace('_', ' ')->title()->toString(),
                    'group' => $canonical?->group,
                    'description' => "Legacy alias for {$canonicalSlug}.",
                ]
            );
        }
    }

    private function syncRolePermissions(): void
    {
        $allPermissionIds = Permission::query()->pluck('id');
        $aliases = config('rbac.permission_aliases', []);

        foreach (config('rbac.role_permissions', []) as $roleSlug => $permissionSlugs) {
            $role = Role::where('slug', $roleSlug)->first();

            if (! $role) {
                continue;
            }

            if ($permissionSlugs === ['*']) {
                $role->permissions()->sync($allPermissionIds);
                continue;
            }

            $expandedSlugs = collect($permissionSlugs)
                ->flatMap(function (string $slug) use ($aliases): array {
                    $slugs = [$slug];
                    $legacySlug = array_search($slug, $aliases, true);

                    if (is_string($legacySlug)) {
                        $slugs[] = $legacySlug;
                    }

                    return $slugs;
                })
                ->unique()
                ->values()
                ->all();

            $permissionIds = Permission::whereIn('slug', $expandedSlugs)->pluck('id');
            $role->permissions()->sync($permissionIds);
        }
    }
}
