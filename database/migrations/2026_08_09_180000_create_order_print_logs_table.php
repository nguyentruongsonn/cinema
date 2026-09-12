<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_print_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('printed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('print_type', 32);
            $table->string('status', 24)->default('requested');
            $table->unsignedSmallInteger('copy_number')->default(1);
            $table->boolean('is_reprint')->default(false);
            $table->string('reason', 255)->nullable();
            $table->string('printer_name', 120)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('printed_at');
            $table->timestamps();

            $table->index(['order_id', 'print_type', 'printed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_print_logs');
    }
};
