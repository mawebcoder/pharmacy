<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Livewire;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Quick view of a product from a list (roadmap B5, B1 Darochi): photo, brand, name, short description and the
 * variants with price and stock, plus a link to the product page. Opened by the "quick-view" event from cards.
 */
final class QuickView extends Component
{
    public ?int $productId = null;

    public bool $show = false;

    #[On('quick-view')]
    public function open(int $productId): void
    {
        $this->productId = $productId;
        $this->show = true;
        unset($this->product);
    }

    #[Computed]
    public function product(): ?Product
    {
        if ($this->productId === null) {
            return null;
        }

        return Product::query()
            ->active()
            ->with(['brand', 'media', 'variants' => fn ($variants) => $variants->where(ProductVariant::COLUMN_IS_ACTIVE, true)])
            ->find($this->productId);
    }

    public function render(): View
    {
        return view('catalog::livewire.quick-view');
    }
}
