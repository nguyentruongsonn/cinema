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
        Schema::table('combos', function (Blueprint $table) {
            $table->decimal('original_price', 10, 2)->default(0)->after('price')->comment('Tổng giá gốc của các sản phẩm trong combo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('combos', function (Blueprint $table) {
            $table->dropColumn('original_price');
        });
    }
};