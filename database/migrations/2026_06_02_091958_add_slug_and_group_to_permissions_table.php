<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->string('slug')->unique()->after('name');
            $table->text('description')->nullable()->after('slug');
            $table->string('group')->nullable()->after('description');
            $table->dropColumn('display_name');
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn(['slug', 'description', 'group']);
            $table->string('display_name')->nullable()->after('name');
        });
    }
};
