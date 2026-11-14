{{-- Seeds and keeps the Alpine «cart» store in step (roadmap S19): see App\Modules\Cart\Livewire\CartState. --}}
<div
    hidden
    x-data
    x-init="$store.cart.replace(@js($state))"
    x-on:cart-updated.window="$store.cart.replace($event.detail)"
></div>
