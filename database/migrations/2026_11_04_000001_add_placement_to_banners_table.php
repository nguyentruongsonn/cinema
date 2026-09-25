<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->string('placement', 50)->default('home')->after('link_url');
            $table->index(['is_active', 'placement', 'created_at'], 'banners_active_placement_idx');
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropIndex('banners_active_placement_idx');
            $table->dropColumn('placement');
        });
    }
};
