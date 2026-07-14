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
            $table->tinyInteger('status')->default(1)
                ->comment('0: Hỏng/không sử dụng | 1: Sẵn sàng | 2: Bảo trì');
            $table->timestamps();

            // Performance indexes
            $table->index('screen_id', 'idx_seats_screen_id');
            $table->index('seat_type_id', 'idx_seats_seat_type_id');
            $table->index(['screen_id', 'row', 'number'], 'idx_seats_location');

            // Foreign keys
            $table->foreign('screen_id')
                ->references('id')->on('screens')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            // Note: FK to seat_types will be added in separate migration after seat_types is created
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seats');
    }
};
