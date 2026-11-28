<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

use App\Modules\Settings\Queries\StoreSettings;
use App\Modules\Shared\Support\SearchText;

/**
 * What a shopper typed, ready for the search engine (roadmap S39): normalised like the index (SearchText), without the
 * words the admin set to ignore («قیمت»، «خرید» …; «تنظیمات فروشگاه» › «جستجو»). A query made only of such words is
 * kept as typed, so «خرید» alone still searches. Only queries change, so editing the list needs no re-index.
 */
final readonly class SearchQueryText
{
    public function __construct(private StoreSettings $settings) {}

    public function clean(string $text): string
    {
        $normalized = SearchText::normalize($text);
        $ignored = SearchText::words(implode(' ', $this->settings->searchIgnoredWords()));
        $kept = array_values(array_filter(explode(' ', $normalized), fn (string $word): bool => $word !== '' && ! in_array($word, $ignored, true)));

        return $kept === [] ? $normalized : implode(' ', $kept);
    }
}
