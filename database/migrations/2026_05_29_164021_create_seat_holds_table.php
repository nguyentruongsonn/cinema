<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seat_holds', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('showtime_id')->unsigned()->index();
            $table->bigInteger('user_id')->unsigned()->index();
            $table->longText('seat_ids');
            $table->timestamp('held_until');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seat_holds');
    }
};
