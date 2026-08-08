<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'system_key')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('system_key')->nullable()->unique()->after('account_status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'system_key')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropUnique(['system_key']);
                $table->dropColumn('system_key');
            });
        }
    }
};
