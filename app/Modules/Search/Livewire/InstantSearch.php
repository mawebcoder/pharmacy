<?php

declare(strict_types=1);

namespace App\Modules\Search\Livewire;

use App\Modules\Catalog\Data\SearchSuggestions;
use App\Modules\Catalog\Queries\CollectionProducts;
use App\Modules\Catalog\Queries\SearchShortcuts;
use App\Modules\Catalog\Queries\SearchSuggestionsQuery;
use App\Modules\Search\Actions\ForgetRecentSearches;
use App\Modules\Search\Queries\RecentSearches;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The search box (roadmap B2 rule 2, D21.2 item 6): suggestions while typing (300 ms pause, from 2 letters) —
 * up to 6 products, rows of 8 categories and 8 brands (or the admin's pinned ones when none match; owner,
 * 2026-11-15), 3 tags — with keyboard navigation; Enter opens /search?q=…. Without JavaScript it is a plain form to
 * the search page. Opened empty, it shows «جستجوهای اخیر», «محصولات محبوب» (S13b) and the pinned categories and brands. Recent searches live in the browser (latest 6); a signed-in customer's come from their
 * search logs too, loaded with popular products in the same request and merged in the browser (owner, 2026-11-14).
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

    /**
     * The signed-in customer's recent searches, filled with popular products on the first focus.
     *
     * @var list<array{q: string, at: int}>
     */
    #[Locked]
    public array $serverRecent = [];

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

    /**
     * The admin's pinned categories and brands for the empty box, loaded with popular products.
     *
     * @return array{categories: Collection<int, Model>, brands: Collection<int, Model>}
     */
    #[Computed]
    public function shortcuts(): array
    {
        if (! $this->popularRequested) {
            return ['categories' => new Collection, 'brands' => new Collection];
        }

        $shortcuts = app(SearchShortcuts::class);
        /** @var Collection<int, Model> $categories */
        $categories = $shortcuts->categories();
        /** @var Collection<int, Model> $brands */
        $brands = $shortcuts->brands();

        return ['categories' => $categories, 'brands' => $brands];
    }

    public function updatedPopularRequested(RecentSearches $recentSearches): void
    {
        $customerId = $this->customerId();
        $this->serverRecent = $this->popularRequested && $customerId !== null ? $recentSearches->for($customerId) : [];
    }

    /**
     * «پاک کردن»: the browser clears its own list; this unlinks a signed-in customer's searches.
     */
    public function clearRecent(ForgetRecentSearches $forget): void
    {
        $customerId = $this->customerId();

        if ($customerId !== null) {
            $forget($customerId);
        }

        $this->serverRecent = [];
        $this->skipRender();
    }

    public function render(): View
    {
        return view('search::livewire.instant-search', [
            'minLength' => SearchSuggestionsQuery::MIN_LENGTH,
        ]);
    }

    private function customerId(): ?int
    {
        $id = auth('customer')->id();

        return $id === null ? null : (int) $id;
    }
}
