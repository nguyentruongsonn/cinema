<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'manager' => 'theater_manager',
            'staff' => 'ticket_seller',
            'user' => 'customer',
        ] as $legacySlug => $targetSlug) {
            $legacyRoleId = DB::table('roles')->where('slug', $legacySlug)->value('id');
            $targetRoleId = DB::table('roles')->where('slug', $targetSlug)->value('id');

            if ($legacyRoleId && $targetRoleId) {
                DB::table('users')
                    ->where('role_id', $legacyRoleId)
                    ->update(['role_id' => $targetRoleId]);
            }
        }
    }

    public function down(): void
    {
        //
    }
};
