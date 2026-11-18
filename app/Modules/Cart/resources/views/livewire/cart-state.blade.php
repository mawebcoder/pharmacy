{{-- Seeds and keeps the Alpine «cart» store in step (roadmap S21): see App\Modules\Cart\Livewire\CartState. Also hands the
     removal countdown («تنظیمات فروشگاه») to the «cartRemoval» store. --}}
<div
    hidden
    x-data
    x-init="$store.cart.replace(@js($state)); $store.cartRemoval.seconds = {{ $removalDelaySeconds }}"
    x-on:cart-updated.window="$store.cart.replace($event.detail)"
></div>
