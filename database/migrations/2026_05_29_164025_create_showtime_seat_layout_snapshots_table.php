<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('showtime_seat_layout_snapshots', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('showtime_id')->unsigned()->index();
            $table->longText('seat_layout_snapshot');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('showtime_seat_layout_snapshots');
    }
};
