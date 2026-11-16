import cartRemoval from './components/cart-removal';
import confirmDialog from './components/confirm-dialog';
import copyText from './components/copy-text';
import countdown from './components/countdown';
import imagePlaceholders from './components/image-placeholders';
import jalaliDate from './components/jalali-date';
import productGallery from './components/product-gallery';
import searchHistory from './components/search-history';
import slider from './components/slider';
import swipeClose from './components/swipe-close';
import swipeToasts from './components/toast-swipe';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('jalaliDate', jalaliDate);
    window.Alpine.data('slider', slider);
    window.Alpine.data('countdown', countdown);
    window.Alpine.data('productGallery', productGallery);
    window.Alpine.store('searchHistory', searchHistory);
    window.Alpine.directive('swipe-close', swipeClose);
    window.Alpine.store('confirm', confirmDialog);
    window.Alpine.store('cartRemoval', cartRemoval);
    window.Alpine.magic('copy', () => copyText);

    // The shopper's cart for the add-to-cart buttons and the header (roadmap S19), seeded and updated by cart-state.
    window.Alpine.store('cart', {
        items: {},
        limits: {},
        count: 0,
        total: 0,
        quantityOf(variantId) {
            // A SKU whose removal is on its way already shows as gone.
            if (window.Alpine.store('cartRemoval').isRemoved(variantId)) {
                return 0;
            }

            return this.items[variantId] ?? 0;
        },
        atLimit(variantId) {
            const limit = this.limits[variantId];

            return limit !== undefined && this.quantityOf(variantId) >= limit;
        },
        replace(state) {
            this.items = state.items ?? {};
            this.limits = state.limits ?? {};
            this.count = state.count ?? 0;
            this.total = state.total ?? 0;
            window.Alpine.store('cartRemoval').reconcile(state);
        },
    });

    // Saved product ids for the heart buttons (roadmap S18), seeded and updated by the wishlist-toggle component.
    window.Alpine.store('wishlist', {
        ids: [],
        has(id) {
            return this.ids.includes(id);
        },
        set(id, saved) {
            this.ids = saved ? [...new Set([...this.ids, id])] : this.ids.filter((savedId) => savedId !== id);
        },
    });
});

swipeToasts();
imagePlaceholders();

// A page restored from the back/forward cache shows old quantities: refresh them from the lightweight endpoint.
window.addEventListener('pageshow', async (event) => {
    if (! event.persisted || ! window.Alpine) {
        return;
    }

    const response = await fetch('/cart/quantities', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });

    if (response.ok) {
        const { data } = await response.json();
        window.Alpine.store('cart').items = data.items;
    }
});
