<?php

declare(strict_types=1);

namespace App\Modules\Search\Livewire;

use App\Modules\Catalog\Data\ListingCriteria;
use App\Modules\Catalog\Enums\ListingSort;
use App\Modules\Catalog\Queries\CategoryTree;
use App\Modules\Catalog\Queries\ProductListing;
use App\Modules\Search\Actions\LogSearch;
use App\Modules\Shared\Support\SearchText;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Search results (roadmap B5 /search?q=, D21.2): the product list for the text with filters and sort, the search
 * logged once per visit (with its result count, for the insights page), and suggestions when nothing is found.
 */
final class SearchPage extends Component
{
    #[Url]
    public string $q = '';

    public ?int $searchLogId = null;

    public int $total = 0;

    public bool $available = true;

    public function mount(ProductListing $listing, LogSearch $logSearch): void
    {
        $this->q = mb_substr(trim($this->q), 0, 120);

        if (SearchText::normalize($this->q) === '') {
            return;
        }

        $result = $listing->get(new ListingCriteria(query: $this->q, sort: ListingSort::Relevant, perPage: 1));
        $this->total = $result->products->total();
        $this->available = ! $result->degraded;

        if ($this->available) {
            $this->searchLogId = $logSearch($this->q, $this->total)->getId();
        }
    }

    public function render(): View
    {
        return view('search::livewire.search-page', [
            'categories' => array_slice(app(CategoryTree::class)->roots(), 0, 8),
        ])->title($this->q === '' ? 'جستجو' : 'جستجوی «'.$this->q.'»');
    }
}
