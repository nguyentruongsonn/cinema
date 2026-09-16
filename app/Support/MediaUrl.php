<?php

declare(strict_types=1);

namespace App\Support;

final class MediaUrl
{
    public static function storage(?string $path): ?string
    {
        $path = trim((string) $path);

        return $path === '' ? null : '/storage/'.ltrim($path, '/');
    }
}
