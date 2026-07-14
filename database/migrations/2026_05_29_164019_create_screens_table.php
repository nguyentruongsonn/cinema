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
            $table->unsignedBigInteger('seat_layout_template_id')->nullable();
            $table->integer('capacity')->nullable();
            $table->tinyInteger('status')->default(1)
                ->comment('0: Ngừng hoạt động | 1: Đang hoạt động | 2: Bảo trì');
            $table->timestamps();
            $table->softDeletes();

            // Note: FK to seat_layout_templates will be added in separate migration
            // after seat_layout_templates is created
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screens');
    }
};
