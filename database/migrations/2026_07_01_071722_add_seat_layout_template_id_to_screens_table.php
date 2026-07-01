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
        Schema::table('screens', function (Blueprint $table) {
            $table->unsignedBigInteger('seat_layout_template_id')->nullable()->after('sound_id');
            
            // Add constraint if exists or define it safely
            $table->foreign('seat_layout_template_id')
                  ->references('id')
                  ->on('seat_layout_templates')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('screens', function (Blueprint $table) {
            $table->dropForeign(['seat_layout_template_id']);
            $table->dropColumn('seat_layout_template_id');
        });
    }
};
