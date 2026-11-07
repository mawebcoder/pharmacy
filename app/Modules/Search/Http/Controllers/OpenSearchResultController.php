<?php

declare(strict_types=1);

namespace App\Modules\Search\Http\Controllers;

use App\Modules\Search\Actions\LogSearchClick;
use Illuminate\Http\RedirectResponse;

/**
 * A product opened from search results: records the click on the search log, then shows the product page.
 * The product link stays a normal link, so it also works in a new tab.
 */
final class OpenSearchResultController
{
    public function __invoke(int $searchLog, int $product, string $slug, LogSearchClick $logClick): RedirectResponse
    {
        $logClick($searchLog, $product);

        return redirect()->route('products.show', $slug);
    }
}
