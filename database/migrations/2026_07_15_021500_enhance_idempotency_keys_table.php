<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            
            $table->string('request_path', 255)->nullable()->after('key');
            $table->string('request_method', 10)->nullable()->after('request_path');
            
            $table->unsignedSmallInteger('response_status')->nullable()->after('response_data');
            
            $table->foreignId('payment_id')->nullable()->after('user_id')->constrained('payments')->nullOnDelete();
            
            $table->index('expires_at');
            $table->index(['user_id', 'created_at']);
            $table->index('payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);

            $table->dropIndex(['expires_at']);
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['payment_id']);
            
            $table->dropColumn([
                'request_path',
                'request_method',
                'response_status',
                'payment_id',
            ]);
            
        });
    }
};
