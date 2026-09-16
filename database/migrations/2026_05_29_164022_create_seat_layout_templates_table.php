<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seat_layout_templates', function (Blueprint $table) {
            $table->id();
            $table->string('template_name');
            $table->string('seat_matrix')->nullable();
            $table->integer('regular_seat_rows')->default(0);
            $table->integer('vip_seat_rows')->default(0);
            $table->integer('couple_seat_rows')->default(0);
            $table->longText('custom_matrix')->nullable();
            $table->text('description')->nullable();
            $table->boolean('status')->default(true)
                ->comment('false: Vô hiệu hóa | true: Sử dụng được');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seat_layout_templates');
    }
};
