<?php

declare(strict_types=1);

namespace App\Modules\Content\Console;

use App\Modules\Content\Jobs\ConvertPageImages;
use App\Modules\Content\Models\Page;
use App\Modules\Content\Support\PageImages;
use Illuminate\Console\Command;

/**
 * Queues the AVIF copies that static pages' images are missing (pages saved before AVIF, or a failed job). Run by
 * every deploy; it only queues jobs for Horizon.
 *
 *   php artisan content:page-images
 */
final class ConvertPageImagesCommand extends Command
{
    protected $signature = 'content:page-images';

    protected $description = 'Queue AVIF copies of static page images that do not have one yet';

    public function handle(): int
    {
        $queued = 0;

        Page::query()->whereNotNull(Page::COLUMN_BODY)->each(function (Page $page) use (&$queued): void {
            if (PageImages::hasMissing($page->getBody())) {
                ConvertPageImages::dispatch($page->getId());
                $queued++;
            }
        });

        $this->components->info("{$queued} pages queued.");

        return self::SUCCESS;
    }
}
