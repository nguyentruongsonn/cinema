<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('slug', 'ticket_seller')->value('id');
        if (! $roleId) {
            return;
        }

        $permissions = [
            'customers.lookup' => ['name' => 'Tra cứu khách hàng tại quầy', 'group' => 'booking'],
            'customers.create_walk_in' => ['name' => 'Tạo khách vãng lai tại quầy', 'group' => 'booking'],
            'payments.process_cash' => ['name' => 'Xác nhận thanh toán tiền mặt', 'group' => 'payments'],
        ];

        foreach ($permissions as $slug => $attributes) {
            $permissionId = DB::table('permissions')->where('slug', $slug)->value('id');
            if (! $permissionId) {
                $permissionId = DB::table('permissions')->insertGetId([
                    'name' => $attributes['name'],
                    'slug' => $slug,
                    'group' => $attributes['group'],
                    'description' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('permission_role')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('slug', 'ticket_seller')->value('id');
        if (! $roleId) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', ['customers.lookup', 'customers.create_walk_in', 'payments.process_cash'])
            ->pluck('id');

        DB::table('permission_role')
            ->where('role_id', $roleId)
            ->whereIn('permission_id', $permissionIds)
            ->delete();
    }
};
