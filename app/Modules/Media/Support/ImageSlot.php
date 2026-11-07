<?php

declare(strict_types=1);

namespace App\Modules\Media\Support;

use InvalidArgumentException;

/**
 * One slot from config/media-presets.php: where an image is shown, which generated widths it uses, and the
 * `sizes` attribute for <picture>.
 */
final readonly class ImageSlot
{
    /**
     * @param  list<int>  $widths
     */
    public function __construct(
        public string $name,
        public ImagePreset $preset,
        public array $widths,
        public string $sizes,
        public bool $thumbhashPlaceholder,
    ) {}

    public static function named(string $name): self
    {
        /** @var array{preset: string, widths: list<int>, sizes: string, placeholder?: string}|null $slot */
        $slot = config("media-presets.slots.{$name}");

        if ($slot === null) {
            throw new InvalidArgumentException("Unknown image slot [{$name}].");
        }

        return new self(
            name: $name,
            preset: ImagePreset::named($slot['preset']),
            widths: $slot['widths'],
            sizes: $slot['sizes'],
            thumbhashPlaceholder: ($slot['placeholder'] ?? 'color') === 'thumbhash',
        );
    }
}
