<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Brand;
use App\Modules\Media\Actions\AttachImage;
use App\Modules\Media\Models\Media;
use App\Modules\Media\Support\MediaPathGenerator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Queue::fake();
    config(['filesystems.disks.legacy' => ['driver' => 'local', 'root' => storage_path('legacy')], 'filesystems.disks.objects' => ['driver' => 'local', 'root' => storage_path('objects')], 'filesystems.disks.edge' => ['driver' => 'local', 'root' => storage_path('edge')]]);
    Storage::fake('legacy');
    Storage::fake('objects');
    Storage::fake('edge');
    Storage::fake(variantsDisk());

    // Two brand logos stored on the old disk; the second one's file has gone missing.
    config(['media-library.disk_name' => 'legacy']);
    $this->kept = app(AttachImage::class)(Brand::factory()->create(), Brand::IMAGE_LOGO, testImage(800, 800));
    $this->lost = app(AttachImage::class)(Brand::factory()->create(), Brand::IMAGE_LOGO, testImage(800, 800));
    Storage::disk('legacy')->delete($this->lost->getPathRelativeToRoot());

    config(['media-library.disk_name' => 'objects']);
});

it('moves each original to the originals disk and logs the one it cannot move, with its record', function (): void {
    Log::spy();

    $this->artisan('media:migrate-to-s3')->assertExitCode(1);

    expect($this->kept->fresh()->getAttribute(Media::COLUMN_DISK))->toBe('objects')
        ->and($this->lost->fresh()->getAttribute(Media::COLUMN_DISK))->toBe('legacy');
    Storage::disk('objects')->assertExists($this->kept->getPathRelativeToRoot());
    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => $context['media_id'] === $this->lost->getId()
        && $context['entity'] === 'Brand #'.$this->lost->getAttribute('model_id')
        && $context['file'] === 'legacy:'.$this->lost->getPathRelativeToRoot());
});

it('moves nothing on a dry run and refuses an unknown model', function (): void {
    $this->artisan('media:migrate-to-s3 --dry-run')->assertSuccessful();
    $this->artisan('media:migrate-to-s3 --model=users')->assertExitCode(2);

    expect($this->kept->fresh()->getAttribute(Media::COLUMN_DISK))->toBe('legacy');
    Storage::disk('objects')->assertMissing($this->kept->getPathRelativeToRoot());
});

it('moves the generated sizes to the generated-sizes disk and points the image at it', function (): void {
    $folder = app(MediaPathGenerator::class)->getPathForConversions($this->kept);
    $from = variantsDisk();
    Storage::disk($from)->put($folder.'logo-160.webp', 'webp');
    Storage::disk($from)->put($folder.'responsive/logo-80.avif', 'avif');
    config(['media-library.conversions_disk_name' => 'edge']);

    $this->artisan('media:migrate-to-s3 --dry-run')->assertExitCode(0);
    expect($this->kept->fresh()->getAttribute(Media::COLUMN_CONVERSIONS_DISK))->toBe($from);

    $this->artisan('media:migrate-to-s3')->assertExitCode(1);

    Storage::disk('edge')->assertExists([$folder.'logo-160.webp', $folder.'responsive/logo-80.avif']);
    expect($this->kept->fresh()->getAttribute(Media::COLUMN_CONVERSIONS_DISK))->toBe('edge')
        ->and($this->kept->fresh()->conversionsDisk())->toBe('edge');
});

it('does not copy a file the target disk already has', function (): void {
    Storage::disk('objects')->put($this->kept->getPathRelativeToRoot(), 'already there');

    $this->artisan('media:migrate-to-s3')
        ->expectsOutputToContain('exists  objects:'.$this->kept->getPathRelativeToRoot())
        ->expectsOutputToContain('failed  legacy:'.$this->lost->getPathRelativeToRoot())
        ->expectsOutputToContain('1 file(s) were already there')
        ->assertExitCode(1);

    expect(Storage::disk('objects')->get($this->kept->getPathRelativeToRoot()))->toBe('already there')
        ->and($this->kept->fresh()->getAttribute(Media::COLUMN_DISK))->toBe('objects');
});
