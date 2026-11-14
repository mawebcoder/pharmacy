{{-- Quick view (roadmap B10 modal): see App\Modules\Catalog\Livewire\QuickView. --}}
@use('App\Modules\Catalog\Enums\StockStatus')

<div>
    <x-ts-modal wire="show" size="3xl" :title="$this->product?->getNameFa() ?? ''">
        @if ($product = $this->product)
            <div class="grid gap-4 md:grid-cols-2">
                <div class="relative aspect-square overflow-hidden rounded-card bg-white">
                    <x-media::picture :media="$product->getMainImage()" slot-name="pdp-main" :alt="$product->getNameFa()" class="size-full object-contain" />
                    <x-catalog::wishlist-button :product-id="$product->getId()" class="absolute end-2 top-2" />
                </div>

                <div class="space-y-3">
                    <p class="text-sm text-ink-500">{{ $product->getBrand()?->getNameFa() }}</p>
                    @if ($product->getShortDescription())
                        <p class="text-sm leading-7 text-ink-900">{{ $product->getShortDescription() }}</p>
                    @endif

                    <ul class="divide-y divide-line-200 rounded-card border border-line-200">
                        @foreach ($product->getVariants() as $variant)
                            <li class="flex items-center justify-between gap-3 px-3 py-2">
                                <div>
                                    <p class="text-sm text-ink-900">{{ $variant->getTitleFa() }}</p>
                                    <p @class(['text-xs', 'text-success-600' => $variant->getStockStatus() === StockStatus::Available, 'text-ink-500' => $variant->getStockStatus() !== StockStatus::Available])>
                                        {{ $variant->getStockStatus()->label() }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <x-ui.price :price="$variant->currentPrice()" :old="$variant->compareAtPrice()" size="sm" />
                                    @if ($variant->setRelation('product', $product)->canAddToCart())
                                        <x-cart::add-button :variant-id="$variant->getId()" :label="$product->getNameFa().' '.$variant->getTitleFa()" />
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    <a href="{{ route('products.show', $product) }}" wire:navigate class="flex h-11 w-full items-center justify-center rounded-card border border-primary-700 text-sm font-bold text-primary-700 hover:bg-primary-50 focus-visible:outline-2 focus-visible:outline-primary-700">
                        مشاهده جزئیات محصول
                    </a>
                </div>
            </div>
        @elseif ($productId !== null)
            <p class="py-6 text-center text-ink-500">این کالا دیگر در دسترس نیست.</p>
        @endif
    </x-ts-modal>
</div>
