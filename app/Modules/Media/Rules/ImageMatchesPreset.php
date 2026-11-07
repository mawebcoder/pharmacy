<?php

declare(strict_types=1);

namespace App\Modules\Media\Rules;

use App\Modules\Media\Support\ImagePreset;
use App\Modules\Shared\Support\PersianDigits;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Upload rules of a preset (roadmap D14.2): file type, file size, minimum dimensions and aspect ratio.
 * SVG (where the preset allows it) has no pixel size, so only its type and file size are checked.
 */
final readonly class ImageMatchesPreset implements ValidationRule
{
    public function __construct(private ImagePreset $preset) {}

    public static function for(string $preset): self
    {
        return new self(ImagePreset::named($preset));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('فایل تصویر بارگذاری نشد.');

            return;
        }

        $mime = (string) $value->getMimeType();

        if (! in_array($mime, $this->preset->mimes, true)) {
            $fail('قالب فایل باید یکی از این‌ها باشد: '.$this->formatNames().'.');

            return;
        }

        if ($value->getSize() > $this->preset->maxKilobytes * 1024) {
            $fail('حجم فایل نباید بیشتر از '.PersianDigits::toPersian($this->preset->maxKilobytes).' کیلوبایت باشد.');

            return;
        }

        if ($mime === ImagePreset::SVG) {
            return;
        }

        $size = @getimagesize($value->getRealPath());

        if ($size === false) {
            $fail('فایل، تصویر معتبری نیست.');

            return;
        }

        [$width, $height] = $size;

        $longest = max($width, $height);
        $tooSmall = $this->preset->padToSquare
            ? $longest < max($this->preset->minWidth, $this->preset->minHeight)
            : $width < $this->preset->minWidth || $height < $this->preset->minHeight;

        if ($tooSmall) {
            $fail('ابعاد تصویر باید دست‌کم '.$this->dimensions().' پیکسل باشد.');

            return;
        }

        $tolerance = (float) config('media-presets.ratio_tolerance', 0.02);

        if ($this->preset->ratio !== null && abs($width / $height - $this->preset->ratio) > $this->preset->ratio * $tolerance) {
            $fail('نسبت ابعاد تصویر باید مانند '.$this->dimensions().' باشد.');
        }
    }

    private function dimensions(): string
    {
        return PersianDigits::toPersian($this->preset->minWidth).'×'.PersianDigits::toPersian($this->preset->minHeight);
    }

    private function formatNames(): string
    {
        return collect($this->preset->mimes)
            ->map(fn (string $mime): string => match ($mime) {
                'image/jpeg' => 'JPEG',
                'image/png' => 'PNG',
                'image/webp' => 'WebP',
                ImagePreset::SVG => 'SVG',
                default => $mime,
            })
            ->implode('، ');
    }
}
