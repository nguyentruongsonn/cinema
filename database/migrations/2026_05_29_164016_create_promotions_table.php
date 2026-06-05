<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('description')->nullable();
            $table->enum('discount_type', ['percentage', 'fixed_amount']);
            $table->decimal('discount_value', 15, 2);
            $table->decimal('min_order_value', 15, 2)->default(0.00);
            $table->decimal('max_discount_amount', 15, 2)->nullable();
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_count')->default(0);
            $table->integer('daily_usage_limit')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
