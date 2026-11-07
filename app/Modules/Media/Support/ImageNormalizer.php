<?php

declare(strict_types=1);

namespace App\Modules\Media\Support;

use Imagick;
use ImagickPixel;

/**
 * Prepares an uploaded original before sizes are generated (roadmap D8, D14.4): turns it upright, converts it to
 * sRGB, strips EXIF/GPS and other metadata, and for product photos trims the border and pads to a white square.
 * The file is rewritten in place.
 */
final class ImageNormalizer
{
    /**
     * Colour distance treated as "the same as the border" when trimming product photos (0–1).
     */
    private const float TRIM_FUZZ = 0.08;

    /**
     * @return array{width: int, height: int}
     */
    public function normalize(string $path, ImagePreset $preset): array
    {
        $image = new Imagick($path);
        $image->setIteratorIndex(0);
        $image->autoOrient();
        $this->toSrgb($image);

        if ($preset->padToSquare) {
            $this->trimAndPadToWhiteSquare($image);
        }

        $image->stripImage();
        $image->writeImage($path);

        $size = ['width' => $image->getImageWidth(), 'height' => $image->getImageHeight()];
        $image->clear();

        return $size;
    }

    private function toSrgb(Imagick $image): void
    {
        $profiles = $image->getImageProfiles('icc', false);
        $srgb = $this->srgbProfile();

        if ($profiles !== [] && $srgb !== null) {
            $image->profileImage('icc', $srgb);
        } elseif ($image->getImageColorspace() !== Imagick::COLORSPACE_SRGB) {
            $image->transformImageColorspace(Imagick::COLORSPACE_SRGB);
        }
    }

    private function trimAndPadToWhiteSquare(Imagick $image): void
    {
        $image->setImageBackgroundColor(new ImagickPixel('white'));
        $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
        $image->trimImage(self::TRIM_FUZZ * $image->getQuantum());
        $image->setImagePage(0, 0, 0, 0);

        $side = max($image->getImageWidth(), $image->getImageHeight());
        $image->extentImage(
            $side,
            $side,
            -intdiv($side - $image->getImageWidth(), 2),
            -intdiv($side - $image->getImageHeight(), 2),
        );
    }

    /**
     * The sRGB ICC profile shipped with the system, if any (Alpine: /usr/share/color/icc).
     */
    private function srgbProfile(): ?string
    {
        foreach (['/usr/share/color/icc/sRGB.icc', '/usr/share/color/icc/colord/sRGB.icc'] as $candidate) {
            if (is_readable($candidate)) {
                return (string) file_get_contents($candidate);
            }
        }

        return null;
    }
}
