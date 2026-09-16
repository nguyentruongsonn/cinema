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
            $table->bigInteger('version_type_id')->unsigned()->nullable()->index();
            $table->timestamp('scheduled_at');
            $table->longText('pricing_snapshot')->nullable();
            $table->tinyInteger('status')->default(1)
                ->comment('0: Đã hủy | 1: Sẵn sàng bán vé | 2: Hết vé | 3: Đã chiếu');
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('movie_id')
                ->references('id')->on('movies')
                ->onDelete('restrict')
                ->onUpdate('cascade');

            $table->foreign('screen_id')
                ->references('id')->on('screens')
                ->onDelete('restrict')
                ->onUpdate('cascade');

            // Note: FK to version_types needs version_types table to be created first
            // Will be added in a separate migration after version_types exists
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('showtimes');
    }
};
