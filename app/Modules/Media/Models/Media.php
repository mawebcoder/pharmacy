<?php

declare(strict_types=1);

namespace App\Modules\Media\Models;

use App\Modules\Media\Contracts\StoredImage;
use App\Modules\Media\Enums\MediaStatus;
use App\Modules\Media\Support\ImagePreset;
use App\Modules\Media\Support\MediaPathGenerator;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;
use Thumbhash\Thumbhash;

/**
 * An uploaded image (roadmap D8): the original on a private disk, generated sizes on the public media/CDN disk.
 * Pipeline details live in custom properties: preset, status, size after processing, ThumbHash, dominant colour,
 * content hash (part of the public file paths) and Persian alt text.
 */
final class Media extends SpatieMedia implements StoredImage
{
    public const string COLUMN_ID = 'id';

    public const string COLUMN_UUID = 'uuid';

    public const string COLUMN_COLLECTION_NAME = 'collection_name';

    public const string COLUMN_FILE_NAME = 'file_name';

    public const string COLUMN_MIME_TYPE = 'mime_type';

    public const string COLUMN_DISK = 'disk';

    public const string COLUMN_CONVERSIONS_DISK = 'conversions_disk';

    public const string PROPERTY_PRESET = 'preset';

    public const string PROPERTY_STATUS = 'status';

    public const string PROPERTY_ERROR = 'error';

    public const string PROPERTY_WIDTH = 'width';

    public const string PROPERTY_HEIGHT = 'height';

    public const string PROPERTY_THUMBHASH = 'thumbhash';

    public const string PROPERTY_DOMINANT_COLOR = 'dominant_color';

    public const string PROPERTY_CONTENT_HASH = 'content_hash';

    public const string PROPERTY_ALT_FA = 'alt_fa';

    public function getId(): int
    {
        return (int) $this->getAttribute(self::COLUMN_ID);
    }

    public function getUuid(): string
    {
        return (string) $this->getAttribute(self::COLUMN_UUID);
    }

    public function getCollectionName(): string
    {
        return (string) $this->getAttribute(self::COLUMN_COLLECTION_NAME);
    }

    public function getFileName(): string
    {
        return (string) $this->getAttribute(self::COLUMN_FILE_NAME);
    }

    public function getMimeType(): ?string
    {
        $value = $this->getAttribute(self::COLUMN_MIME_TYPE);

        return $value === null ? null : (string) $value;
    }

    public function isSvg(): bool
    {
        return $this->getMimeType() === ImagePreset::SVG;
    }

    public function getPresetName(): ?string
    {
        $value = $this->getCustomProperty(self::PROPERTY_PRESET);

        return is_string($value) ? $value : null;
    }

    public function getPreset(): ?ImagePreset
    {
        $name = $this->getPresetName();

        return $name === null ? null : ImagePreset::named($name);
    }

    public function setPresetName(string $preset): static
    {
        return $this->setCustomProperty(self::PROPERTY_PRESET, $preset);
    }

    public function getStatus(): MediaStatus
    {
        return MediaStatus::tryFrom((string) $this->getCustomProperty(self::PROPERTY_STATUS)) ?? MediaStatus::Processing;
    }

    public function setStatus(MediaStatus $status, ?string $error = null): static
    {
        return $this->setCustomProperty(self::PROPERTY_STATUS, $status->value)->setCustomProperty(self::PROPERTY_ERROR, $error);
    }

    public function isReady(): bool
    {
        return $this->getStatus() === MediaStatus::Ready;
    }

    public function getError(): ?string
    {
        $value = $this->getCustomProperty(self::PROPERTY_ERROR);

        return is_string($value) ? $value : null;
    }

    /**
     * Width of the processed original in pixels (null for SVG or before processing).
     */
    public function getWidth(): ?int
    {
        $value = $this->getCustomProperty(self::PROPERTY_WIDTH);

        return is_numeric($value) ? (int) $value : null;
    }

    public function getHeight(): ?int
    {
        $value = $this->getCustomProperty(self::PROPERTY_HEIGHT);

        return is_numeric($value) ? (int) $value : null;
    }

    public function setDimensions(int $width, int $height): static
    {
        return $this->setCustomProperty(self::PROPERTY_WIDTH, $width)->setCustomProperty(self::PROPERTY_HEIGHT, $height);
    }

    /**
     * Height of a generated file of the given width, keeping the original's aspect ratio.
     */
    public function heightFor(int $width): ?int
    {
        $originalWidth = $this->getWidth();
        $originalHeight = $this->getHeight();

        return $originalWidth === null || $originalHeight === null || $originalWidth === 0
            ? null
            : (int) round($width * $originalHeight / $originalWidth);
    }

    /**
     * Base64 ThumbHash of the image (roadmap D14.4).
     */
    public function getThumbhash(): ?string
    {
        $value = $this->getCustomProperty(self::PROPERTY_THUMBHASH);

        return is_string($value) ? $value : null;
    }

    public function setThumbhash(?string $thumbhash): static
    {
        return $this->setCustomProperty(self::PROPERTY_THUMBHASH, $thumbhash);
    }

    /**
     * The ThumbHash decoded to a small PNG data URL, for a blurred placeholder.
     */
    public function thumbhashDataUrl(): ?string
    {
        $thumbhash = $this->getThumbhash();

        if ($thumbhash === null) {
            return null;
        }

        /** @var list<int> $hash */
        $hash = array_values(unpack('C*', (string) base64_decode($thumbhash, true)) ?: []);

        return $hash === [] ? null : Thumbhash::toDataURL($hash);
    }

    /**
     * Dominant colour as "#rrggbb".
     */
    public function getDominantColor(): ?string
    {
        $value = $this->getCustomProperty(self::PROPERTY_DOMINANT_COLOR);

        return is_string($value) ? $value : null;
    }

    public function setDominantColor(?string $color): static
    {
        return $this->setCustomProperty(self::PROPERTY_DOMINANT_COLOR, $color);
    }

    /**
     * First characters of the uploaded file's SHA-1; part of the public paths, so a new file gets new URLs.
     */
    public function getContentHash(): ?string
    {
        $value = $this->getCustomProperty(self::PROPERTY_CONTENT_HASH);

        return is_string($value) ? $value : null;
    }

    public function setContentHash(string $hash): static
    {
        return $this->setCustomProperty(self::PROPERTY_CONTENT_HASH, $hash);
    }

    public function getAltFa(): ?string
    {
        $value = $this->getCustomProperty(self::PROPERTY_ALT_FA);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function setAltFa(?string $alt): static
    {
        return $this->setCustomProperty(self::PROPERTY_ALT_FA, $alt);
    }

    /**
     * Public URL of a generated file, e.g. url(320, 'webp').
     */
    public function url(int $width, string $format): ?string
    {
        $preset = $this->getPreset();

        return $preset instanceof ImagePreset ? $this->getUrl($preset->conversionName($width, $format)) : null;
    }

    /**
     * Where the sanitized SVG is published on the conversions disk (relative path).
     */
    public function svgPath(): string
    {
        return app(MediaPathGenerator::class)->getPathForConversions($this).$this->getFileName();
    }

    public function svgUrl(): string
    {
        return Storage::disk($this->conversionsDisk())->url($this->svgPath());
    }

    public function conversionsDisk(): string
    {
        return (string) ($this->getAttribute(self::COLUMN_CONVERSIONS_DISK) ?: $this->getAttribute(self::COLUMN_DISK));
    }
}
