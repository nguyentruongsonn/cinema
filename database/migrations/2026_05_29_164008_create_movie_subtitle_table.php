<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movie_subtitle', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('movie_id')->unsigned()->index();
            $table->bigInteger('subtitle_id')->unsigned()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movie_subtitle');
    }
};
