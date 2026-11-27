<?php

declare(strict_types=1);

namespace App\Modules\Seo\Listeners;

use App\Modules\Seo\Actions\SaveRedirect;
use App\Modules\Seo\Data\SeoRedirectData;
use App\Modules\Seo\Enums\RedirectSource;
use App\Modules\Seo\Models\SeoRedirect;
use App\Modules\Seo\Support\SeoPath;
use App\Modules\Shared\Contracts\HasSlugAddresses;
use Illuminate\Database\Eloquent\Model;

/**
 * When a model with slug addresses (HasSlugAddresses: products, categories, subcategories, brands, tags, collections,
 * pages) gets a new slug, each old address is sent to the new one with a 301 (owner, 2026-11-27), so shared links and
 * search results keep working; a category's slug moves its subcategories' addresses too. A redirect from a new address
 * (an old one in use again) is removed.
 */
final readonly class RecordSlugRedirects
{
    public function __construct(private SaveRedirect $saveRedirect) {}

    /**
     * Listens to every "eloquent.updated: *" event.
     *
     * @param  array<int, mixed>  $payload
     */
    public function handle(string $event, array $payload): void
    {
        $model = $payload[0] ?? null;

        if (! $model instanceof Model || ! $model instanceof HasSlugAddresses || ! $model->wasChanged('slug') || blank($model->getOriginal('slug'))) {
            return;
        }

        $old = $model->addressesForSlug((string) $model->getOriginal('slug'));
        $new = $model->addressesForSlug((string) $model->getAttribute('slug'));

        foreach ($old as $position => $from) {
            $to = $new[$position] ?? null;

            if ($to === null || $to === $from) {
                continue;
            }

            SeoRedirect::query()->where(SeoRedirect::COLUMN_FROM_PATH, SeoPath::normalize($to))->delete();
            ($this->saveRedirect)(new SeoRedirectData($from, $to, 301), RedirectSource::Slug);
        }
    }
}
