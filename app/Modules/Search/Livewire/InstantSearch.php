<?php

declare(strict_types=1);

namespace App\Modules\Search\Livewire;

use App\Modules\Catalog\Data\SearchSuggestions;
use App\Modules\Catalog\Queries\CollectionProducts;
use App\Modules\Catalog\Queries\SearchSuggestionsQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The search box (roadmap B2 rule 2, D21.2 item 6): suggestions while typing (300 ms pause, from 2 letters) —
 * up to 6 products, 3 brands, 3 categories, 3 tags — with keyboard navigation; Enter opens /search?q=….
 * Without JavaScript it is a plain form to the search page. Opened empty, it shows «محصولات محبوب» (S13b).
 */
final class InstantSearch extends Component
{
    #[Locked]
    public string $inputId = 'site-search';

    public string $value = '';

    public string $class = '';

    /**
     * Set on the first focus, so popular products load only when someone opens the box.
     */
    public bool $popularRequested = false;

    public function mount(string $value = '', string $inputId = 'site-search', string $class = ''): void
    {
        $this->value = $value;
        $this->inputId = $inputId;
        $this->class = $class;
    }

    #[Computed]
    public function suggestions(): SearchSuggestions
    {
        return app(SearchSuggestionsQuery::class)->for($this->value);
    }

    /**
     * «محصولات محبوب» for the empty box (roadmap D21.2): at most 6 in-stock products in the admin's order.
     *
     * @return Collection<int, Model>
     */
    #[Computed]
    public function popular(): Collection
    {
        if (! $this->popularRequested) {
            return new Collection;
        }

        /** @var Collection<int, Model> $products */
        $products = app(CollectionProducts::class)->popular();

        return $products;
    }

    public function render(): View
    {
        return view('search::livewire.instant-search', [
            'minLength' => SearchSuggestionsQuery::MIN_LENGTH,
        ]);
    }
}
