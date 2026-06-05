<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('showtimes', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('movie_id')->unsigned()->index();
            $table->bigInteger('screen_id')->unsigned()->index();
            $table->bigInteger('format_id')->unsigned()->nullable()->index();
            $table->bigInteger('subtitle_id')->unsigned()->nullable()->index();
            $table->timestamp('scheduled_at');
            $table->decimal('price', 15, 2);
            $table->longText('pricing_snapshot')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('showtimes');
    }
};
