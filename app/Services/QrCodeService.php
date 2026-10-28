<?php

declare(strict_types=1);

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCodeService
{
    public function svg(string $value, int $size = 300): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(max(120, min($size, 600))),
            new SvgImageBackEnd()
        );

        return (new Writer($renderer))->writeString($value);
    }
}
