<?php

declare(strict_types=1);

namespace App\Modules\Search\Livewire;

use App\Modules\Catalog\Data\ListingCriteria;
use App\Modules\Catalog\Enums\ListingSort;
use App\Modules\Catalog\Queries\CategoryTree;
use App\Modules\Catalog\Queries\ProductListing;
use App\Modules\Catalog\Queries\SearchVocabulary;
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
            $customerId = auth('customer')->id();
            $this->searchLogId = $logSearch($this->q, $this->total, $customerId === null ? null : (int) $customerId)->getId();
        }
    }

    public function render(): View
    {
        return view('search::livewire.search-page', [
            'categories' => array_slice(app(CategoryTree::class)->roots(), 0, 8),
            // «منظورتان … بود؟» when nothing was found (S39).
            'didYouMean' => $this->q !== '' && $this->available && $this->total === 0 ? app(SearchVocabulary::class)->suggest($this->q) : null,
        ])->title($this->q === '' ? 'جستجو' : 'جستجوی «'.$this->q.'»');
    }
}
