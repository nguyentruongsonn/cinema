<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            // Thêm cột lưu đường dẫn file nội bộ
            $table->string('poster_path')->nullable()->after('poster_url');
            $table->string('banner_path')->nullable()->after('poster_path');
        });
    }

    public function down(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->dropColumn(['poster_path', 'banner_path']);
        });
    }
};
