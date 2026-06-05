<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screens', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('theater_id')->unsigned()->index();
            $table->string('name');
            $table->string('code');
            $table->bigInteger('format_id')->unsigned()->nullable()->index();
            $table->bigInteger('sound_id')->unsigned()->nullable()->index();
            $table->integer('capacity')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screens');
    }
};
