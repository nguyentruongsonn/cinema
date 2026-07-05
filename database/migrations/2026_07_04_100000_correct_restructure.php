<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop sounds table
        Schema::dropIfExists('sounds');

        // 2. Rename subtitles table to version_types
        Schema::rename('subtitles', 'version_types');

        // 3. Add slug and description columns to version_types
        Schema::table('version_types', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
            $table->text('description')->nullable()->after('slug');
        });

        // 4. Update existing data to add slugs
        DB::table('version_types')->update(['slug' => DB::raw('LOWER(name)')]);

        // 5. Rename subtitle_id to version_type_id in showtimes
        Schema::table('showtimes', function (Blueprint $table) {
            $table->dropForeign(['subtitle_id']);
            $table->renameColumn('subtitle_id', 'version_type_id');
        });

        // 6. Re-add foreign key with new name
        Schema::table('showtimes', function (Blueprint $table) {
            $table->foreign('version_type_id')->references('id')->on('version_types')->onDelete('set null');
        });

        // 7. Drop price column from showtimes
        Schema::table('showtimes', function (Blueprint $table) {
            $table->dropColumn('price');
        });

        // 8. Drop sound_id column if it exists
        if (Schema::hasColumn('showtimes', 'sound_id')) {
            Schema::table('showtimes', function (Blueprint $table) {
                $table->dropForeign(['sound_id']);
                $table->dropColumn('sound_id');
            });
        }
    }

    public function down(): void
    {
        // Reverse: Add price column back
        Schema::table('showtimes', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->default(0)->after('scheduled_at');
        });

        // Reverse: Add sound_id column back
        Schema::table('showtimes', function (Blueprint $table) {
            $table->foreignId('sound_id')->nullable()->after('format_id')->constrained('sounds')->onDelete('set null');
        });

        // Reverse: Rename version_type_id back to subtitle_id
        Schema::table('showtimes', function (Blueprint $table) {
            $table->dropForeign(['version_type_id']);
            $table->renameColumn('version_type_id', 'subtitle_id');
        });

        Schema::table('showtimes', function (Blueprint $table) {
            $table->foreign('subtitle_id')->references('id')->on('subtitles')->onDelete('set null');
        });

        // Reverse: Rename version_types back to subtitles
        Schema::table('version_types', function (Blueprint $table) {
            $table->dropColumn('slug');
        });

        Schema::rename('version_types', 'subtitles');

        // Reverse: Recreate sounds table
        Schema::create('sounds', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }
};
