<?php

declare(strict_types=1);

namespace App\Modules\Content\Jobs;

use App\Modules\Content\Models\Page;
use App\Modules\Content\Support\PageImages;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Makes the AVIF copies of one page's images, on the "media" queue with the other image work (one at a time, low
 * priority). Safe to repeat: images that have their copy are skipped.
 */
final class ConvertPageImages implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public readonly int $pageId)
    {
        $this->onQueue('media');
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(): void
    {
        $page = Page::query()->find($this->pageId);

        foreach (PageImages::paths($page?->getBody()) as $path) {
            PageImages::convert($path);
        }
    }
}
