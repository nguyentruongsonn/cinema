<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banner_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('banner_id')->constrained()->cascadeOnDelete();
            $table->string('image_path');
            $table->timestamps();

            $table->index(['banner_id', 'id']);
        });

        DB::table('banners')
            ->select(['id', 'image_path', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->chunkById(100, function ($banners) {
                $rows = $banners->map(fn ($banner) => [
                    'banner_id' => $banner->id,
                    'image_path' => $banner->image_path,
                    'created_at' => $banner->created_at,
                    'updated_at' => $banner->updated_at,
                ])->all();

                DB::table('banner_images')->insert($rows);
            });

        Schema::table('banners', function (Blueprint $table) {
            $table->dropIndex('banners_admin_status_position_order_idx');
            $table->dropIndex(['position']);
            $table->dropIndex(['display_order']);
            $table->dropColumn(['image_path', 'position', 'display_order']);
            $table->index(['is_active', 'created_at'], 'banners_admin_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('description');
            $table->enum('position', ['home_slider', 'sidebar', 'popup', 'top_bar', 'footer'])
                ->default('home_slider')
                ->after('link_url');
            $table->integer('display_order')->default(0)->after('position');
            $table->dropIndex('banners_admin_status_created_idx');
            $table->index('position');
            $table->index('display_order');
            $table->index(['is_active', 'position', 'display_order'], 'banners_admin_status_position_order_idx');
        });

        DB::table('banner_images')
            ->orderBy('id')
            ->get()
            ->groupBy('banner_id')
            ->each(function ($images, $bannerId) {
                DB::table('banners')
                    ->where('id', $bannerId)
                    ->update(['image_path' => $images->first()->image_path]);
            });

        Schema::dropIfExists('banner_images');
    }
};
