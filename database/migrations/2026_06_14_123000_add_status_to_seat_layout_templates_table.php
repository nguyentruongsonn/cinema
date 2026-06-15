<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seat_layout_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('seat_layout_templates', 'status')) {
                $table->boolean('status')->default(true)->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('seat_layout_templates', function (Blueprint $table) {
            if (Schema::hasColumn('seat_layout_templates', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
