<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Brand;
use App\Modules\Media\Actions\AttachImage;
use App\Modules\Media\Models\Media;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Queue::fake();
    config(['filesystems.disks.legacy' => ['driver' => 'local', 'root' => storage_path('legacy')], 'filesystems.disks.objects' => ['driver' => 'local', 'root' => storage_path('objects')]]);
    Storage::fake('legacy');
    Storage::fake('objects');
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
