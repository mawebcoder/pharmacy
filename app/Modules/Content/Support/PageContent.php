<?php

declare(strict_types=1);

namespace App\Modules\Content\Support;

use App\Modules\Content\Models\Page;
use App\Modules\Shared\Support\DiskVisibility;
use Filament\Forms\Components\RichEditor\RichContentRenderer;

/**
 * Turns a page body into safe HTML for the storefront (roadmap D16): Filament's renderer resolves the editor's images
 * on the default filesystem disk (plain links on a public disk such as the s3 bucket, signed ones otherwise) and sanitises; PageHtml then
 * cleans it once more with the page allow-list, so scripts, event handlers and unsafe URLs never reach the page.
 * Images then load lazily, from their AVIF copy where it exists (PageImages).
 */
final class PageContent
{
    public static function disk(): string
    {
        return (string) config('filesystems.default');
    }

    public static function visibility(): string
    {
        return DiskVisibility::of(self::disk());
    }

    public static function toHtml(Page $page): string
    {
        $body = $page->getBody();

        if ($body === null) {
            return '';
        }

        return PageImages::withAvif((string) PageHtml::clean(RichContentRenderer::make($body)
            ->fileAttachmentsDisk(self::disk())
            ->fileAttachmentsVisibility(self::visibility())
            ->toHtml()));
    }
}
