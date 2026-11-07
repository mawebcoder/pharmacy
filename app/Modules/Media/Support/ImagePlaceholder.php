<?php

declare(strict_types=1);

namespace App\Modules\Media\Support;

use Imagick;
use Thumbhash\Thumbhash;

/**
 * ThumbHash and dominant colour of an image (roadmap D14.4), computed from a copy shrunk to at most 100 px.
 */
final class ImagePlaceholder
{
    /**
     * @return array{thumbhash: string, color: string} base64 ThumbHash and "#rrggbb"
     */
    public function for(string $path): array
    {
        $image = new Imagick($path);
        $image->setIteratorIndex(0);
        $image->thumbnailImage(100, 100, true);
        $image->setImageFormat('rgba');
        $image->setImageDepth(8);

        $width = $image->getImageWidth();
        $height = $image->getImageHeight();
        /** @var list<int> $rgba */
        $rgba = array_values(unpack('C*', $image->getImageBlob()) ?: []);
        $image->clear();

        $hash = Thumbhash::RGBAToHash($width, $height, $rgba);

        return [
            'thumbhash' => base64_encode(pack('C*', ...$hash)),
            'color' => $this->averageColor($rgba),
        ];
    }

    /**
     * Average of the opaque pixels, so a transparent logo is not averaged towards black.
     *
     * @param  list<int>  $rgba
     */
    private function averageColor(array $rgba): string
    {
        $sum = [0, 0, 0];
        $weight = 0;

        for ($index = 0, $count = count($rgba); $index + 3 < $count; $index += 4) {
            $alpha = $rgba[$index + 3];
            $sum[0] += $rgba[$index] * $alpha;
            $sum[1] += $rgba[$index + 1] * $alpha;
            $sum[2] += $rgba[$index + 2] * $alpha;
            $weight += $alpha;
        }

        if ($weight === 0) {
            return '#ffffff';
        }

        return sprintf('#%02x%02x%02x', intdiv($sum[0], $weight), intdiv($sum[1], $weight), intdiv($sum[2], $weight));
    }
}
