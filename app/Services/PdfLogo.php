<?php

namespace App\Services;

use App\Models\BusinessSetting;
use Illuminate\Support\Facades\Storage;

class PdfLogo
{
    /**
     * @return array{width:int,height:int,color_space:string,bits:int,filter:string,data:string,alpha?:string}|null
     */
    public function image(): ?array
    {
        $path = BusinessSetting::current()->logo_path;

        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $contents = Storage::disk('public')->get($path);
        $mime = Storage::disk('public')->mimeType($path);
        $cachePath = 'pdf-logos/'.hash('sha256', $mime."\0".$contents).'.bin';

        if (Storage::disk('local')->exists($cachePath)) {
            $cached = unserialize(Storage::disk('local')->get($cachePath), ['allowed_classes' => false]);

            if (is_array($cached)) {
                return $cached;
            }
        }

        $image = match ($mime) {
            'image/jpeg' => $this->jpeg($contents),
            'image/png' => $this->png($contents),
            default => null,
        };

        if ($image) {
            Storage::disk('local')->put($cachePath, serialize($image));
        }

        return $image;
    }

    /** @return array{width:int,height:int,color_space:string,bits:int,filter:string,data:string}|null */
    private function jpeg(string $contents): ?array
    {
        $size = getimagesizefromstring($contents);

        if (! $size) {
            return null;
        }

        return [
            'width' => $size[0],
            'height' => $size[1],
            'color_space' => ($size['channels'] ?? 3) === 1 ? 'DeviceGray' : 'DeviceRGB',
            'bits' => $size['bits'] ?? 8,
            'filter' => 'DCTDecode',
            'data' => $contents,
        ];
    }

    /** @return array{width:int,height:int,color_space:string,bits:int,filter:string,data:string,alpha?:string}|null */
    private function png(string $contents): ?array
    {
        if (! str_starts_with($contents, "\x89PNG\r\n\x1a\n") || strlen($contents) < 33) {
            return null;
        }

        $width = unpack('N', substr($contents, 16, 4))[1];
        $height = unpack('N', substr($contents, 20, 4))[1];
        $bits = ord($contents[24]);
        $colorType = ord($contents[25]);
        $interlace = ord($contents[28]);

        if ($bits !== 8 || $interlace !== 0 || ! in_array($colorType, [0, 2, 4, 6], true)) {
            return null;
        }

        $compressed = '';
        $offset = 8;

        while ($offset + 12 <= strlen($contents)) {
            $length = unpack('N', substr($contents, $offset, 4))[1];
            $type = substr($contents, $offset + 4, 4);

            if ($type === 'IDAT') {
                $compressed .= substr($contents, $offset + 8, $length);
            }

            $offset += 12 + $length;

            if ($type === 'IEND') {
                break;
            }
        }

        if ($compressed === '') {
            return null;
        }

        $channels = match ($colorType) {
            0 => 1,
            2 => 3,
            4 => 2,
            6 => 4,
        };
        $hasAlpha = in_array($colorType, [4, 6], true);
        $colorChannels = $hasAlpha ? $channels - 1 : $channels;
        $rowLength = ($width * $channels) + 1;
        $inflate = inflate_init(ZLIB_ENCODING_DEFLATE);
        $colorDeflate = deflate_init(ZLIB_ENCODING_DEFLATE, ['level' => 6]);
        $alphaDeflate = $hasAlpha ? deflate_init(ZLIB_ENCODING_DEFLATE, ['level' => 6]) : null;

        if ($inflate === false || $colorDeflate === false || ($hasAlpha && $alphaDeflate === false)) {
            return null;
        }

        $buffer = '';
        $previous = str_repeat("\0", $width * $channels);
        $colorData = '';
        $alphaData = '';
        $rows = 0;

        $chunks = str_split($compressed, 65536);
        $lastChunk = array_key_last($chunks);

        foreach ($chunks as $index => $chunk) {
            $mode = $index === $lastChunk ? ZLIB_FINISH : ZLIB_SYNC_FLUSH;
            $inflated = inflate_add($inflate, $chunk, $mode);

            if ($inflated === false) {
                return null;
            }

            $buffer .= $inflated;

            while (strlen($buffer) >= $rowLength && $rows < $height) {
                $filter = ord($buffer[0]);
                $scanline = substr($buffer, 1, $rowLength - 1);
                $buffer = substr($buffer, $rowLength);
                $row = $this->unfilter($scanline, $previous, $channels, $filter);

                if ($row === null) {
                    return null;
                }

                [$colors, $alpha] = $this->splitChannels($row, $channels, $colorChannels, $hasAlpha);
                $colorData .= deflate_add($colorDeflate, $colors, ZLIB_NO_FLUSH);

                if ($hasAlpha && $alphaDeflate) {
                    $alphaData .= deflate_add($alphaDeflate, $alpha, ZLIB_NO_FLUSH);
                }

                $previous = $row;
                $rows++;
            }
        }

        if ($rows !== $height) {
            return null;
        }

        $colorData .= deflate_add($colorDeflate, '', ZLIB_FINISH);

        if ($hasAlpha && $alphaDeflate) {
            $alphaData .= deflate_add($alphaDeflate, '', ZLIB_FINISH);
        }

        return array_filter([
            'width' => $width,
            'height' => $height,
            'color_space' => $colorChannels === 1 ? 'DeviceGray' : 'DeviceRGB',
            'bits' => 8,
            'filter' => 'FlateDecode',
            'data' => $colorData,
            'alpha' => $hasAlpha ? $alphaData : null,
        ], fn ($value): bool => $value !== null);
    }

    /**
     * @param  array{width:int,height:int}  $image
     * @return array{width:float,height:float}
     */
    public function dimensions(array $image, float $maximumWidth, float $maximumHeight): array
    {
        $scale = min($maximumWidth / $image['width'], $maximumHeight / $image['height']);

        return [
            'width' => round($image['width'] * $scale, 2),
            'height' => round($image['height'] * $scale, 2),
        ];
    }

    /**
     * @param  array{width:int,height:int,color_space:string,bits:int,filter:string,data:string,alpha?:string}  $image
     * @return array<int,string>
     */
    public function objects(array $image, int $imageObject, int $alphaObject): array
    {
        $objects = [];
        $softMask = '';

        if (isset($image['alpha'])) {
            $alpha = $image['alpha'];
            $objects[$alphaObject] = sprintf(
                "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length %d >>\nstream\n%sendstream",
                $image['width'],
                $image['height'],
                strlen($alpha),
                $alpha,
            );
            $softMask = " /SMask {$alphaObject} 0 R";
        }

        $data = $image['data'];
        $objects[$imageObject] = sprintf(
            "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /%s /BitsPerComponent %d /Filter /%s /Length %d%s >>\nstream\n%sendstream",
            $image['width'],
            $image['height'],
            $image['color_space'],
            $image['bits'],
            $image['filter'],
            strlen($data),
            $softMask,
            $data,
        );

        ksort($objects);

        return $objects;
    }

    private function unfilter(string $scanline, string $previous, int $bytesPerPixel, int $filter): ?string
    {
        if ($filter < 0 || $filter > 4) {
            return null;
        }

        $row = '';
        $length = strlen($scanline);

        for ($index = 0; $index < $length; $index++) {
            $raw = ord($scanline[$index]);
            $left = $index >= $bytesPerPixel ? ord($row[$index - $bytesPerPixel]) : 0;
            $up = ord($previous[$index]);
            $upperLeft = $index >= $bytesPerPixel ? ord($previous[$index - $bytesPerPixel]) : 0;
            $value = match ($filter) {
                0 => $raw,
                1 => $raw + $left,
                2 => $raw + $up,
                3 => $raw + intdiv($left + $up, 2),
                4 => $raw + $this->paeth($left, $up, $upperLeft),
            };
            $row .= chr($value & 0xFF);
        }

        return $row;
    }

    /** @return array{string,string} */
    private function splitChannels(string $row, int $channels, int $colorChannels, bool $hasAlpha): array
    {
        if (! $hasAlpha) {
            return [$row, ''];
        }

        $colors = '';
        $alpha = '';

        for ($offset = 0; $offset < strlen($row); $offset += $channels) {
            $colors .= substr($row, $offset, $colorChannels);
            $alpha .= $row[$offset + $colorChannels];
        }

        return [$colors, $alpha];
    }

    private function paeth(int $left, int $up, int $upperLeft): int
    {
        $estimate = $left + $up - $upperLeft;
        $leftDistance = abs($estimate - $left);
        $upDistance = abs($estimate - $up);
        $upperLeftDistance = abs($estimate - $upperLeft);

        if ($leftDistance <= $upDistance && $leftDistance <= $upperLeftDistance) {
            return $left;
        }

        return $upDistance <= $upperLeftDistance ? $up : $upperLeft;
    }
}
