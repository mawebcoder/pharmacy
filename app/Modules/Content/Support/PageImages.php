<?php

declare(strict_types=1);

namespace App\Modules\Content\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Imagick;
use Throwable;

/**
 * Images in a static page's body (the editor's attachments, owner 2026-11-28): every image gets an AVIF copy beside it
 * ("pages/x.jpg" → "pages/x.jpg.avif"), made in the background (ConvertPageImages on the "media" queue). The page then
 * offers the copy in a <picture> once it exists, the uploaded file as the fallback, and every image loads lazily.
 */
final class PageImages
{
    private const int AVIF_QUALITY = 60;

    /**
     * The attachment paths in a body, once each: «<img data-id="pages/x.jpg">» → ["pages/x.jpg"].
     *
     * @return list<string>
     */
    public static function paths(?string $body): array
    {
        preg_match_all('#data-id="([^"]+\.(?:jpe?g|png|webp))"#i', (string) $body, $matches);

        return array_values(array_unique($matches[1]));
    }

    public static function avifPath(string $path): string
    {
        return $path.'.avif';
    }

    /**
     * Whether a body has an image still without its AVIF copy.
     */
    public static function hasMissing(?string $body): bool
    {
        foreach (self::paths($body) as $path) {
            if (! self::disk()->exists(self::avifPath($path)) && self::disk()->exists($path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Writes the AVIF copy of one attachment; nothing when it exists already or the file is gone.
     */
    public static function convert(string $path): void
    {
        $disk = self::disk();

        if ($disk->exists(self::avifPath($path)) || ! $disk->exists($path)) {
            return;
        }

        $image = new Imagick;
        $image->readImageBlob((string) $disk->get($path));
        $image->autoOrient();
        $image->stripImage();
        $image->setImageFormat('avif');
        $image->setImageCompressionQuality(self::AVIF_QUALITY);
        $disk->put(self::avifPath($path), $image->getImageBlob(), 'private');
        $image->clear();
    }

    /**
     * Cleaned page HTML with each image lazy, and in a <picture> with its AVIF copy when that exists. Runs after
     * PageHtml::clean, so the markup added here is the only <picture> and <source> on the page.
     */
    public static function withAvif(string $html): string
    {
        return (string) preg_replace_callback('#<img\b([^>]*)>#i', function (array $match): string {
            $img = '<img'.$match[1].' loading="lazy" decoding="async">';

            if (preg_match('#data-id="([^"]+)"#', $match[1], $id) !== 1 || ! self::disk()->exists(self::avifPath(html_entity_decode($id[1])))) {
                return $img;
            }

            $url = self::url(self::avifPath(html_entity_decode($id[1])));

            return '<picture><source type="image/avif" srcset="'.e($url).'">'.$img.'</picture>';
        }, $html);
    }

    /**
     * Like the editor's own links (Filament's RichContentRenderer): plain on a public disk, signed otherwise.
     */
    private static function url(string $path): string
    {
        if (PageContent::visibility() === 'public') {
            return self::disk()->url($path);
        }

        try {
            return self::disk()->temporaryUrl($path, now()->addMinutes((int) config('filament.temporary_file_url_expiry_minutes', 30))->endOfHour());
        } catch (Throwable) {
            return self::disk()->url($path);
        }
    }

    private static function disk(): Filesystem
    {
        return Storage::disk(PageContent::disk());
    }
}
