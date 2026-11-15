/**
 * Undo window before a product leaves the cart (owner, 2026-11-15): a line's bin and the stepper's bin at 1 start a
 * countdown of `seconds` (from «تنظیمات فروشگاه», set by the cart-state component); every add-to-cart control of
 * that SKU shows it with a shrinking bar and «انصراف», and at zero the removal is sent («cart-set» with quantity 0, to
 * CartState). 0 seconds removes at once.
 * At zero the line leaves the page at once and the server removes it in the background (owner, 2026-11-15): the SKU is
 * `removed` (its lines hide, its buttons show «+») until the server answers. If the server kept it (refused), it
 * comes back; a later add of the SKU forgets the removal.
 * «حذف همه» asks in the confirmation dialog, then `clearAll()` blurs the cart until it is empty (`clearing`).
 * $store.cartRemoval.start(variantId)   $store.cartRemoval.cancel(variantId)   $store.cartRemoval.secondsLeft(variantId)
 * $store.cartRemoval.isRemoved(variantId)   $store.cartRemoval.clearAll()   $store.cartRemoval.clearing
 */
export default {
    seconds: 3,
    pending: {},
    // variantId => 'sent' (on its way), 'settled' (answered, waiting for the new cart state) or 'done'.
    removed: {},
    clearing: false,
    clearingTimer: null,
    now: Date.now(),
    ticker: null,

    /**
     * Called by Alpine when the store is registered.
     */
    init() {
        window.addEventListener('cart-add', (event) => delete this.removed[event.detail?.variantId]);

        window.addEventListener('cart-settled', (event) => {
            const variantId = event.detail?.variantId;

            if (this.removed[variantId] === 'sent') {
                this.removed[variantId] = 'settled';
            }
        });
    },

    start(variantId) {
        if (this.pending[variantId]) {
            return;
        }

        if (this.seconds <= 0) {
            this.remove(variantId);

            return;
        }

        const duration = this.seconds * 1000;
        this.pending[variantId] = {
            endsAt: Date.now() + duration,
            duration,
            timer: setTimeout(() => this.remove(variantId), duration),
        };
        this.tick();
    },

    cancel(variantId) {
        clearTimeout(this.pending[variantId]?.timer);
        delete this.pending[variantId];
    },

    isPending(variantId) {
        return variantId in this.pending;
    },

    secondsLeft(variantId) {
        const pending = this.pending[variantId];

        return pending ? Math.max(0, Math.ceil((pending.endsAt - this.now) / 1000)) : 0;
    },

    /**
     * Seconds of the whole countdown, for the bar's CSS animation.
     */
    durationOf(variantId) {
        return (this.pending[variantId]?.duration ?? 0) / 1000;
    },

    /**
     * Called with every new cart state (the cart store's replace: CartState's «cart-updated» right after settling, and
     * each page's first state). A SKU still in it was not removed, or was added back: show it again.
     */
    reconcile(state) {
        const items = state.items ?? {};

        for (const [variantId, status] of Object.entries(this.removed)) {
            if (status === 'sent') {
                continue;
            }

            if (variantId in items) {
                delete this.removed[variantId];
            } else {
                this.removed[variantId] = 'done';
            }
        }

        // The emptied cart's lines leave with the next render of the cart; a cart with items again ends the blur.
        if (this.clearing && (state.count ?? 0) > 0) {
            this.stopClearing();
        }
    },

    isRemoved(variantId) {
        return variantId in this.removed;
    },

    remove(variantId) {
        this.cancel(variantId);
        this.removed[variantId] = 'sent';
        window.dispatchEvent(new CustomEvent('cart-set', { detail: { variantId, quantity: 0 } }));
    },

    /**
     * «حذف همه» after the confirmation: empties the cart on the server and blurs the cart while it happens. The blur
     * ends when the cart re-renders empty, when items come back, or after 10 seconds without an answer.
     */
    clearAll() {
        this.clearing = true;
        clearTimeout(this.clearingTimer);
        this.clearingTimer = setTimeout(() => this.stopClearing(), 10000);
        window.dispatchEvent(new CustomEvent('cart-clear'));
    },

    stopClearing() {
        this.clearing = false;
        clearTimeout(this.clearingTimer);
    },

    /**
     * Keeps `now` fresh for the seconds shown while any countdown runs.
     */
    tick() {
        if (this.ticker) {
            return;
        }

        this.ticker = setInterval(() => {
            this.now = Date.now();

            if (Object.keys(this.pending).length === 0) {
                clearInterval(this.ticker);
                this.ticker = null;
            }
        }, 200);
    },
};
