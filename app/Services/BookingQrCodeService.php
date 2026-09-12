<?php

declare(strict_types=1);

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

final class BookingQrCodeService
{
    public function payload(string $bookingId): string
    {
        return json_encode(
            ['booking_id' => strtoupper(trim($bookingId))],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );
    }

    public function svg(string $bookingId, int $size = 220): string
    {
        return $this->svgForValue($this->payload($bookingId), $size);
    }

    public function svgForValue(string $value, int $size = 220): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 2),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString(trim($value));
    }

    public function dataUri(string $bookingId, int $size = 220): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($this->svg($bookingId, $size));
    }

    public function dataUriForValue(string $value, int $size = 220): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($this->svgForValue($value, $size));
    }
}
