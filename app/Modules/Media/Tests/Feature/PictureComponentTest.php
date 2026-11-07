<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Media\Actions\AttachImage;
use App\Modules\Media\Contracts\StoredImage;
use App\Modules\Media\Enums\MediaStatus;
use App\Modules\Media\Support\ImageSlot;
use App\Modules\Media\Support\Picture;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('media');
});

it('renders a lazy responsive picture with srcset, sizes and intrinsic size, without a placeholder behind transparent logos', function (): void {
    $brand = Brand::factory()->create();
    $logo = app(AttachImage::class)($brand, Brand::IMAGE_LOGO, testImage(800, 400))->fresh();

    $html = Blade::render('<x-media::picture :media="$logo" slot-name="brand-logo" alt="اوسرین" />', ['logo' => $logo]);

    expect($html)
        ->toContain('srcset="'.$logo->url(160, 'webp').' 160w, '.$logo->url(320, 'webp').' 320w"')
        ->toContain('sizes="(min-width: 1280px) 160px, (min-width: 768px) 120px, 96px"')
        ->toContain('width="320"')
        ->toContain('height="160"')
        ->toContain('alt="اوسرین"')
        ->toContain('loading="lazy"')
        ->not->toContain('background-color');
});

it('loads a priority image eagerly with high fetch priority', function (): void {
    $logo = app(AttachImage::class)(Brand::factory()->create(), Brand::IMAGE_LOGO, testImage(800, 400))->fresh();

    $html = Blade::render('<x-media::picture :media="$logo" slot-name="brand-logo" :priority="true" />', ['logo' => $logo]);

    expect($html)->toContain('loading="eager"')->toContain('fetchpriority="high"')->not->toContain('loading="lazy"');
});

it('shows only the placeholder while an image is still processing', function (): void {
    $logo = app(AttachImage::class)(Brand::factory()->create(), Brand::IMAGE_LOGO, testImage(800, 400))->fresh();
    $logo->setStatus(MediaStatus::Processing)->save();

    $html = Blade::render('<x-media::picture :media="$logo" slot-name="brand-logo" alt="اوسرین" />', ['logo' => $logo]);

    expect($html)->toContain('role="img"')->toContain('aria-label="اوسرین"')->not->toContain('<img');
});

it('switches between desktop and mobile banner art at 768px', function (): void {
    $category = Category::factory()->create();
    $desktop = app(AttachImage::class)($category, Category::IMAGE_BANNER_DESKTOP, testImage(1920, 480, 'jpg'))->fresh();
    $mobile = app(AttachImage::class)($category, Category::IMAGE_BANNER_MOBILE, testImage(800, 600, 'jpg'))->fresh();

    $html = Blade::render(
        '<x-media::art-directed :desktop="$d" :mobile="$m" desktop-slot="category-banner-desktop" mobile-slot="category-banner-mobile" alt="بنر" />',
        ['d' => $desktop, 'm' => $mobile],
    );

    expect($html)
        ->toContain('media="(min-width: 768px)" type="image/avif" srcset="'.$desktop->url(768, 'avif'))
        ->toContain('src="'.$mobile->url(800, 'jpg').'"')
        ->toContain('background-image:url(data:image/png;base64,');
});

it('never offers widths larger than the original, because copies are not enlarged', function (?int $originalWidth, array $expected): void {
    $image = new readonly class($originalWidth) implements StoredImage
    {
        public function __construct(private ?int $width) {}

        public function isReady(): bool
        {
            return true;
        }

        public function isSvg(): bool
        {
            return false;
        }

        public function url(int $width, string $format): string
        {
            return "/p-{$width}.{$format}";
        }

        public function svgUrl(): string
        {
            return '';
        }

        public function getWidth(): ?int
        {
            return $this->width;
        }

        public function heightFor(int $width): int
        {
            return $width;
        }

        public function getAltFa(): ?string
        {
            return null;
        }

        public function getDominantColor(): ?string
        {
            return null;
        }

        public function thumbhashDataUrl(): ?string
        {
            return null;
        }
    };

    expect(Picture::availableWidths($image, ImageSlot::named('pdp-main')))->toBe($expected);
})->with([
    'large original' => [3000, [400, 800, 1200]],
    'original between sizes' => [1000, [400, 800]],
    'original smaller than every size' => [300, [400]],
    'size not known yet' => [null, [400, 800, 1200]],
]);
