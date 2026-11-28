<?php

declare(strict_types=1);

namespace App\Modules\Content\Actions;

use App\Modules\Content\Data\PageData;
use App\Modules\Content\Jobs\ConvertPageImages;
use App\Modules\Content\Models\Page;
use App\Modules\Content\Support\PageImages;

/**
 * Creates or updates a static page (roadmap D3.6, S29). The body arrives cleaned (PageData → PageHtml) and is cleaned
 * again when shown (PageContent). Its images get their AVIF copies in the background (PageImages).
 */
final class SavePage
{
    #[\NoDiscard]
    public function __invoke(PageData $data, ?Page $page = null): Page
    {
        $page ??= new Page;
        $page->setTitle($data->title)
            ->setSlug($data->slug)
            ->setBody($data->body)
            ->setIsActive($data->isActive)
            ->setSeoTitle($data->seoTitle)
            ->setSeoDescription($data->seoDescription)
            ->save();

        if (PageImages::hasMissing($page->getBody())) {
            ConvertPageImages::dispatch($page->getId())->afterCommit();
        }

        return $page;
    }
}
