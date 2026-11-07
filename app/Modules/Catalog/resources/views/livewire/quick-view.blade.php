{{-- Quick view (roadmap B10 modal): see App\Modules\Catalog\Livewire\QuickView. --}}
@use('App\Modules\Catalog\Enums\StockStatus')

<div>
    <x-ts-modal wire="show" size="3xl" :title="$this->product?->getNameFa() ?? ''">
        @if ($product = $this->product)
            <div class="grid gap-4 md:grid-cols-2">
                <div class="aspect-square overflow-hidden rounded-card bg-white">
                    <x-media::picture :media="$product->getMainImage()" slot-name="pdp-main" :alt="$product->getNameFa()" class="size-full object-contain" />
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
                                <x-ui.price :price="$variant->currentPrice()" :old="$variant->compareAtPrice()" size="sm" />
                            </li>
                        @endforeach
                    </ul>

                    <x-ts-button color="primary" class="w-full" :href="route('products.show', $product)" wire:navigate>
                        مشاهده و خرید
                    </x-ts-button>
                </div>
            </div>
        @elseif ($productId !== null)
            <p class="py-6 text-center text-ink-500">این کالا دیگر در دسترس نیست.</p>
        @endif
    </x-ts-modal>
</div>
