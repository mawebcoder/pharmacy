<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Livewire;

use App\Modules\Catalog\Queries\RecentlyViewedProducts;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * «بازدیدهای اخیر» under the product page (roadmap B9.5, S13). The browser keeps the viewed product ids (the
 * `recentlyViewed` Alpine store, so guests have it too) and sends them once the rail comes near the screen.
 */
final class RecentlyViewed extends Component
{
    public int $productId = 0;

    /** @var list<int> */
    public array $productIds = [];

    /**
     * @param  array<mixed>  $productIds
     */
    public function show(array $productIds): void
    {
        $this->productIds = array_values(array_map(
            fn (mixed $id): int => is_numeric($id) ? (int) $id : 0,
            array_slice($productIds, 0, RecentlyViewedProducts::LIMIT + 1),
        ));
    }

    public function render(RecentlyViewedProducts $recentlyViewed): View
    {
        return view('catalog::livewire.recently-viewed', [
            'products' => $recentlyViewed($this->productIds, $this->productId),
        ]);
    }
}
