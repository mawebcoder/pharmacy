<?php

declare(strict_types=1);

namespace App\Modules\Media\Support;

use InvalidArgumentException;

/**
 * One preset from config/media-presets.php: the upload rules for an original and the files generated from it.
 */
final readonly class ImagePreset
{
    public const string SVG = 'image/svg+xml';

    /**
     * @param  list<string>  $mimes
     * @param  list<int>  $widths
     * @param  array<string, int>  $formats  format => quality, in the order <picture> offers them
     */
    public function __construct(
        public string $name,
        public int $minWidth,
        public int $minHeight,
        public ?float $ratio,
        public bool $padToSquare,
        public int $maxKilobytes,
        public array $mimes,
        public array $widths,
        public array $formats,
    ) {}

    public static function named(string $name): self
    {
        /** @var array{min_width: int, min_height: int, ratio?: float|int|null, pad_to_square?: bool, max_kb: int, mimes: list<string>, widths: list<int>, formats: array<string, int>}|null $preset */
        $preset = config("media-presets.presets.{$name}");

        if ($preset === null) {
            throw new InvalidArgumentException("Unknown image preset [{$name}].");
        }

        return new self(
            name: $name,
            minWidth: $preset['min_width'],
            minHeight: $preset['min_height'],
            ratio: isset($preset['ratio']) ? (float) $preset['ratio'] : null,
            padToSquare: $preset['pad_to_square'] ?? false,
            maxKilobytes: $preset['max_kb'],
            mimes: $preset['mimes'],
            widths: $preset['widths'],
            formats: $preset['formats'],
        );
    }

    public function acceptsSvg(): bool
    {
        return in_array(self::SVG, $this->mimes, true);
    }

    /**
     * Photos and banners (JPEG output) are opaque; logos and icons are transparent artwork.
     */
    public function isOpaque(): bool
    {
        return array_key_exists('jpg', $this->formats);
    }

    /**
     * The format of the <img> fallback: JPEG when generated, else the last format (WebP for transparent artwork).
     */
    public function fallbackFormat(): string
    {
        return array_key_exists('jpg', $this->formats) ? 'jpg' : (string) array_key_last($this->formats);
    }

    /**
     * Medialibrary conversion name, e.g. "brand_logo-160-webp".
     */
    public function conversionName(int $width, string $format): string
    {
        return "{$this->name}-{$width}-{$format}";
    }
}
