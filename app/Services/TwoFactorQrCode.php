<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class TwoFactorQrCode
{
    public function dataUri(string $provisioningUri, int $size = 280): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 4),
            new SvgImageBackEnd,
        );

        $svg = (new Writer($renderer))->writeString($provisioningUri);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
