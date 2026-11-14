{{-- Seeds and keeps the Alpine «wishlist» store in step (roadmap S18): see App\Modules\Catalog\Livewire\WishlistToggle. --}}
<div
    hidden
    x-data
    x-init="$store.wishlist.ids = @js($productIds)"
    x-on:wishlist-updated.window="$store.wishlist.set($event.detail.productId, $event.detail.saved)"
></div>
