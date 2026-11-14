{{-- «علاقه‌مندی‌ها» (roadmap B9.9): see App\Modules\Catalog\Livewire\Account\Wishlist. --}}
<x-identity::account-shell active="wishlist" title="علاقه‌مندی‌ها">
    @if ($products->isEmpty())
        <x-identity::empty-state icon="heart" message="هنوز محصولی را ذخیره نکرده‌اید." hint="با زدن ♡ روی هر محصول، آن را اینجا نگه دارید." />
    @else
        <ul class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:gap-4">
            @foreach ($products as $product)
                <li wire:key="wishlist-{{ $product->getId() }}" class="flex flex-col gap-2">
                    <x-catalog::product-card :product="$product" :quick-view="false" />
                    <button type="button" wire:click="remove({{ $product->getId() }})" class="inline-flex min-h-11 items-center justify-center gap-1 rounded-card border border-line-200 bg-white text-sm text-danger-600 hover:bg-surface-50 focus-visible:outline-2 focus-visible:outline-primary-700">
                        <x-ts-icon icon="trash" class="size-4" />
                        حذف
                    </button>
                </li>
            @endforeach
        </ul>

        {{ $products->links() }}
    @endif
</x-identity::account-shell>
