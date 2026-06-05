<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_name');
            $table->string('day_type');
            $table->string('time_slot');
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->decimal('price_multiplier', 10, 2);
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_rules');
    }
};
