<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
            $table->text('description')->nullable()->after('display_name');
        });

        // Update existing roles with slug based on name
        DB::statement("UPDATE roles SET slug = LOWER(REPLACE(name, ' ', '-')) WHERE slug IS NULL");

        // Make slug unique after populating
        Schema::table('roles', function (Blueprint $table) {
            $table->unique('slug');
        });

        // Insert default roles if not exist
        $roles = [
            ['name' => 'Customer', 'slug' => 'customer', 'display_name' => 'Khách hàng'],
            ['name' => 'Admin', 'slug' => 'admin', 'display_name' => 'Quản trị viên'],
            ['name' => 'Manager', 'slug' => 'manager', 'display_name' => 'Quản lý'],
            ['name' => 'Staff', 'slug' => 'staff', 'display_name' => 'Nhân viên'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['slug' => $role['slug']],
                [
                    'name' => $role['name'],
                    'display_name' => $role['display_name'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'description']);
        });
    }
};
