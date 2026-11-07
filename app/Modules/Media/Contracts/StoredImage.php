<?php

declare(strict_types=1);

namespace App\Modules\Media\Contracts;

/**
 * An uploaded image as other modules see it (roadmap C3 rule 1): enough to render it with <x-media::picture>.
 */
interface StoredImage
{
    public function isReady(): bool;

    public function isSvg(): bool;

    /**
     * Public URL of a generated file, e.g. url(320, 'webp').
     */
    public function url(int $width, string $format): ?string;

    public function svgUrl(): string;

    /**
     * Width of the processed original in pixels (null for SVG or before processing).
     */
    public function getWidth(): ?int;

    public function heightFor(int $width): ?int;

    public function getAltFa(): ?string;

    public function getDominantColor(): ?string;

    public function thumbhashDataUrl(): ?string;
}
