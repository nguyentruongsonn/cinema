<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seats', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('screen_id')->unsigned()->index();
            $table->bigInteger('seat_type_id')->unsigned()->index();
            $table->string('row');
            $table->string('number');
            $table->integer('row_index')->default(0);
            $table->integer('column_index')->default(0);
            $table->string('label')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seats');
    }
};
