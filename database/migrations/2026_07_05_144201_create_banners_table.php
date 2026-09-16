<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image_path');
            $table->string('link_url')->nullable();
            $table->enum('position', ['home_slider', 'sidebar', 'popup', 'top_bar', 'footer'])->default('home_slider');
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true)
                ->comment('false: Ẩn | true: Hiển thị');
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->integer('click_count')->default(0);
            $table->timestamps();

            $table->index('position');
            $table->index('is_active');
            $table->index('display_order');
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
