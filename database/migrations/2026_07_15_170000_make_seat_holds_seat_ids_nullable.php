<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seat_holds', function (Blueprint $table) {
            $table->longText('seat_ids')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('seat_holds', function (Blueprint $table) {
            $table->longText('seat_ids')->nullable(false)->change();
        });
    }
};
