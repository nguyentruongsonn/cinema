<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movies', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->nullable();
            $table->string('original_title')->nullable();
            $table->decimal('surcharge', 15, 2)->default(0.00);
            $table->text('description')->nullable();
            $table->integer('duration')->nullable();
            $table->date('release_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('age_rating')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->tinyInteger('is_hidden')->default(0);
            $table->tinyInteger('manual_override_status')->unsigned()->nullable();
            $table->string('director')->nullable();
            $table->string('cast')->nullable();
            $table->string('poster_url')->nullable();
            $table->string('trailer_url')->nullable();
            $table->longText('backdrops')->nullable();
            $table->tinyInteger('is_hot')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movies');
    }
};
