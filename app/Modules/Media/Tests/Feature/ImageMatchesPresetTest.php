<?php

declare(strict_types=1);

use App\Modules\Media\Rules\ImageMatchesPreset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

function passesPreset(UploadedFile $file, string $preset): bool
{
    return Validator::make(['image' => $file], ['image' => [ImageMatchesPreset::for($preset)]])->passes();
}

it('accepts an image that matches the preset', function (): void {
    expect(passesPreset(testImage(800, 800), 'brand_logo'))->toBeTrue()
        // Logos of any shape: padded to a square afterwards, so nobody has to stretch one (owner, 2026-11-27).
        ->and(passesPreset(testImage(800, 400), 'brand_logo'))->toBeTrue()
        ->and(passesPreset(testImage(1920, 480, 'jpg'), 'category_banner_desktop'))->toBeTrue();
});

it('rejects an image that breaks the preset rules', function (Closure $file, string $preset): void {
    expect(passesPreset($file(), $preset))->toBeFalse();
})->with([
    'too small' => [fn (): UploadedFile => testImage(300, 300), 'brand_logo'],
    'wrong ratio' => [fn (): UploadedFile => testImage(1920, 1920, 'jpg'), 'category_banner_desktop'],
    'wrong format' => [fn (): UploadedFile => testImage(1920, 480, 'gif'), 'category_banner_desktop'],
    'svg where only photos are allowed' => [fn (): UploadedFile => testSvg(), 'category_banner_desktop'],
    'too large' => [fn (): UploadedFile => UploadedFile::fake()->image('logo.png', 800, 800)->size(2048), 'brand_logo'],
]);

it('accepts SVG artwork without a pixel size where the preset allows it', function (): void {
    expect(passesPreset(testSvg(), 'brand_logo'))->toBeTrue();
});

it('checks only the longest side of product photos, which are padded to a square', function (): void {
    expect(passesPreset(testImage(1200, 900, 'jpg'), 'product'))->toBeTrue()
        ->and(passesPreset(testImage(1000, 900, 'jpg'), 'product'))->toBeFalse();
});
