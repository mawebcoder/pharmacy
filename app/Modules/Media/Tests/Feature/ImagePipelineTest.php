<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Media\Actions\AttachImage;
use App\Modules\Media\Enums\MediaStatus;
use App\Modules\Media\Jobs\ProcessImage;
use App\Modules\Media\Models\Media;
use App\Modules\Media\Support\ImageNormalizer;
use App\Modules\Media\Support\ImagePreset;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Storage::fake(originalsDisk());
    Storage::fake(variantsDisk());
});

it('queues processing on the media queue with only the preset sizes', function (): void {
    Queue::fake();
    $brand = Brand::factory()->create();

    $media = app(AttachImage::class)($brand, Brand::IMAGE_LOGO, testImage(800, 800), 'لوگوی اوسرین');

    Queue::assertPushedOn('media', ProcessImage::class);
    expect($media->getStatus())->toBe(MediaStatus::Processing)
        ->and($media->getPresetName())->toBe('brand_logo')
        ->and($media->getAltFa())->toBe('لوگوی اوسرین');
});

it('refuses an image that does not match the preset', function (): void {
    $brand = Brand::factory()->create();

    expect(fn () => app(AttachImage::class)($brand, Brand::IMAGE_LOGO, testImage(300, 300)))->toThrow(ValidationException::class);

    expect($brand->getMedia(Brand::IMAGE_LOGO))->toBeEmpty();
});

it('processes an image into its sizes with its placeholder data, next to the original', function (): void {
    $brand = Brand::factory()->create();

    $media = app(AttachImage::class)($brand, Brand::IMAGE_LOGO, testImage(800, 800))->fresh();

    expect($media)
        ->getStatus()->toBe(MediaStatus::Ready)
        ->getWidth()->toBe(800)
        ->getHeight()->toBe(800)
        ->getThumbhash()->not->toBeNull()
        ->getDominantColor()->toMatch('/^#[0-9a-f]{6}$/');

    $conversionsPath = $media->uuid.'/'.$media->getContentHash().'/';
    Storage::disk(variantsDisk())->assertExists($conversionsPath.'image-brand_logo-160-webp.webp');
    Storage::disk(variantsDisk())->assertExists($conversionsPath.'image-brand_logo-320-webp.webp');
    Storage::disk(variantsDisk())->assertExists($conversionsPath.'image-brand_logo-320-avif.avif');
    Storage::disk(originalsDisk())->assertExists($media->uuid.'/image.png');
    expect($media->url(320, 'webp'))->toContain($conversionsPath.'image-brand_logo-320-webp.webp');
});

it('replaces the previous image of a single-image collection', function (): void {
    $brand = Brand::factory()->create();
    $first = app(AttachImage::class)($brand, Brand::IMAGE_LOGO, testImage(800, 800));

    $second = app(AttachImage::class)($brand, Brand::IMAGE_LOGO, testImage(600, 600));

    expect(Media::query()->whereKey($first->getKey())->exists())->toBeFalse()
        ->and($brand->fresh()->getLogo()?->url(160, 'webp'))->toBe($second->fresh()->url(160, 'webp'));
});

it('sanitizes an uploaded SVG and publishes it as-is', function (): void {
    $brand = Brand::factory()->create();

    $media = app(AttachImage::class)($brand, Brand::IMAGE_LOGO, testSvg('<rect width="40" height="20"/><script>alert(1)</script><circle r="5" onload="alert(2)"/>'))->fresh();

    $published = Storage::disk(variantsDisk())->get($media->svgPath());

    expect($media->isReady())->toBeTrue()
        ->and($published)->toContain('<rect')
        ->and($published)->not->toContain('script')
        ->and($published)->not->toContain('onload');
});

it('trims product photos and pads them to a white square', function (): void {
    $file = testImage(1600, 1200, 'jpg');

    $size = app(ImageNormalizer::class)->normalize($file->getRealPath(), ImagePreset::named('product'));

    expect($size['width'])->toBe($size['height']);
});

it('pads a wide brand logo to a square without stretching it', function (): void {
    $file = testImage(800, 400);

    $size = app(ImageNormalizer::class)->normalize($file->getRealPath(), ImagePreset::named('brand_logo'));

    expect($size)->toMatchArray(['width' => 800, 'height' => 800]);
});

it('keeps a category banner pair as separate desktop and mobile images', function (): void {
    $category = Category::factory()->create();

    $desktop = app(AttachImage::class)($category, Category::IMAGE_BANNER_DESKTOP, testImage(1920, 480, 'jpg'));
    $mobile = app(AttachImage::class)($category, Category::IMAGE_BANNER_MOBILE, testImage(800, 600, 'jpg'));

    expect($desktop->fresh()->url(1920, 'avif'))->toContain('category_banner_desktop-1920-avif.avif')
        ->and($mobile->fresh()->url(800, 'webp'))->toContain('category_banner_mobile-800-webp.webp');
});
