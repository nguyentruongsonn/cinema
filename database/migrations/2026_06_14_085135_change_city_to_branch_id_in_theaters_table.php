<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('theaters', function (Blueprint $table) {
            if (Schema::hasColumn('theaters', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('theaters', 'city')) {
                $table->dropColumn('city');
            }
            if (!Schema::hasColumn('theaters', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('id')->constrained('branches')->onDelete('restrict')->onUpdate('cascade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('theaters', function (Blueprint $table) {
            if (Schema::hasColumn('theaters', 'branch_id')) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            }
            if (!Schema::hasColumn('theaters', 'city')) {
                $table->string('city')->nullable();
            }
            if (!Schema::hasColumn('theaters', 'description')) {
                $table->text('description')->nullable();
            }
        });
    }
};
