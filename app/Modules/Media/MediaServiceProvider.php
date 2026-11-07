<?php

declare(strict_types=1);

namespace App\Modules\Media;

use App\Modules\Media\Listeners\PrepareAddedImage;
use App\Modules\Shared\ModuleServiceProvider;
use Illuminate\Support\Facades\Event;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;

final class MediaServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        Event::listen(MediaHasBeenAddedEvent::class, PrepareAddedImage::class);
    }
}
