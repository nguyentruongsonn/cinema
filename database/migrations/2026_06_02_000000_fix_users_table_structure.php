<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Rename full_name to name
            $table->renameColumn('full_name', 'name');

            // Add missing columns that the code expects
            $table->string('username')->nullable()->unique()->after('email');
            $table->string('avatar_url')->nullable()->after('phone');
            $table->timestamp('last_login_at')->nullable()->after('email_verified_at');
            $table->string('last_login_ip')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Reverse the changes
            $table->renameColumn('name', 'full_name');
            $table->dropColumn(['username', 'avatar_url', 'last_login_at', 'last_login_ip']);
        });
    }
};
