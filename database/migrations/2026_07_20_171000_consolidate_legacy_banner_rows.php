<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('banners')
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($banner) => hash('sha256', json_encode([
                $banner->title,
                $banner->description,
                $banner->link_url,
                (bool) $banner->is_active,
                $banner->start_date,
                $banner->end_date,
            ])))
            ->each(function (Collection $banners) {
                $targetBannerId = null;
                $targetImageCount = 0;

                foreach ($banners as $banner) {
                    $imageCount = DB::table('banner_images')
                        ->where('banner_id', $banner->id)
                        ->count();

                    if ($targetBannerId === null || $targetImageCount + $imageCount > 5) {
                        $targetBannerId = $banner->id;
                        $targetImageCount = $imageCount;
                        continue;
                    }

                    DB::table('banner_images')
                        ->where('banner_id', $banner->id)
                        ->update(['banner_id' => $targetBannerId]);
                    DB::table('banners')->where('id', $banner->id)->delete();
                    $targetImageCount += $imageCount;
                }
            });
    }

    public function down(): void
    {
    }
};
