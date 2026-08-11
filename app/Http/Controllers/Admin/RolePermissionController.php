<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\RolePermissionRoleResource;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RolePermissionController extends Controller
{
    public function roles(): JsonResponse
    {
        $this->authorizeAccess('roles.view');

        $roles = Role::query()
            ->whereIn('slug', array_keys(config('rbac.roles', [])))
            ->withCount('permissions')
            ->orderByRaw("CASE WHEN slug = 'admin' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => RolePermissionRoleResource::collection($roles),
        ]);
    }

    public function permissions(): JsonResponse
    {
        $this->authorizeAccess('roles.view');

        $groups = collect(config('rbac.permissions', []))
            ->map(fn(array $permissions, string $group) => [
                'group' => $group,
                'label' => str($group)->replace('_', ' ')->headline()->toString(),
                'permissions' => collect($permissions)->map(fn(string $name, string $slug) => [
                    'slug' => $slug,
                    'name' => $name,
                ])->values(),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $groups,
        ]);
    }

    public function show(Role $role): JsonResponse
    {
        $this->authorizeAccess('roles.view');
        $this->ensureCatalogRole($role);

        $canonicalSlugs = $this->canonicalPermissionSlugs($role);

        return response()->json([
            'success' => true,
            'data' => [
                'role' => new RolePermissionRoleResource($role->loadCount('permissions')),
                'permissions' => $canonicalSlugs,
            ],
        ]);
    }

    public function update(Request $request, Role $role, AuditLogService $auditLogService): JsonResponse
    {
        $this->authorizeAccess('roles.update');
        $this->authorizeAccess('permissions.assign');
        $this->ensureCatalogRole($role);

        $canonicalSlugs = $this->allCanonicalPermissionSlugs();

        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in($canonicalSlugs)],
        ]);

        $oldPermissions = $this->canonicalPermissionSlugs($role);
        $requestedSlugs = collect($validated['permissions'])->unique()->values()->all();
        $syncSlugs = $this->expandWithLegacyAliases($requestedSlugs);

        DB::transaction(function () use ($role, $syncSlugs, $oldPermissions, $requestedSlugs, $request, $auditLogService): void {
            $permissionIds = Permission::whereIn('slug', $syncSlugs)->pluck('id');
            $role->permissions()->sync($permissionIds);

            $auditLogService->record(
                $request->user(),
                'role.permissions.updated',
                $role,
                [
                    'role_slug' => $role->slug,
                    'permissions' => $oldPermissions,
                ],
                [
                    'role_slug' => $role->slug,
                    'permissions' => $requestedSlugs,
                ]
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'Role permissions updated successfully.',
            'data' => [
                'role' => new RolePermissionRoleResource($role->fresh()->loadCount('permissions')),
                'permissions' => $this->canonicalPermissionSlugs($role),
            ],
        ]);
    }

    private function authorizeAccess(string $permission): void
    {
        abort_unless(auth()->user()?->hasPermission($permission), 403, 'Forbidden: insufficient permissions.');
    }

    private function ensureCatalogRole(Role $role): void
    {
        abort_unless(array_key_exists($role->slug, config('rbac.roles', [])), 404);
    }

    /**
     * @return array<int, string>
     */
    private function allCanonicalPermissionSlugs(): array
    {
        return collect(config('rbac.permissions', []))
            ->flatMap(fn(array $permissions) => array_keys($permissions))
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function canonicalPermissionSlugs(Role $role): array
    {
        $aliases = config('rbac.permission_aliases', []);

        return $role->permissions()
            ->pluck('slug')
            ->map(fn(string $slug) => $aliases[$slug] ?? $slug)
            ->intersect($this->allCanonicalPermissionSlugs())
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $slugs
     * @return array<int, string>
     */
    private function expandWithLegacyAliases(array $slugs): array
    {
        $aliases = config('rbac.permission_aliases', []);

        return collect($slugs)
            ->flatMap(function (string $slug) use ($aliases): array {
                $expanded = array_merge([$slug], array_keys($aliases, $slug, true));

                return $expanded;
            })
            ->unique()
            ->values()
            ->all();
    }
}
