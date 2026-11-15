/**
 * The storefront's confirmation dialog (owner, 2026-11-15: in the site's design, not the browser's confirm()). One
 * dialog lives in the layout (x-ui.confirm-dialog); anything asks through the store and gets a promise:
 * $store.confirm.ask({ title: 'حذف از سبد خرید', message: '«کرم اوره» از سبد خرید حذف شود؟', confirm: 'حذف' })
 *     .then((ok) => ok && $dispatch('cart-set', { variantId: 12, quantity: 0 }))
 * icon: 'cancel' shows a crossed circle instead of the bin (cancelling an order).
 * `active` stays true until the click that answered is over, so dropdowns that close on an outside click (the mini
 * cart) can ignore clicks on the dialog.
 */
export default {
    open: false,
    active: false,
    title: '',
    message: '',
    confirmLabel: '',
    cancelLabel: '',
    icon: 'trash',
    resolve: null,

    ask({ title, message = '', confirm = 'تأیید', cancel = 'انصراف', icon = 'trash' }) {
        this.resolve?.(false);
        this.title = title;
        this.message = message;
        this.confirmLabel = confirm;
        this.cancelLabel = cancel;
        this.icon = icon;
        this.open = true;
        this.active = true;

        return new Promise((resolve) => (this.resolve = resolve));
    },

    answer(ok) {
        this.open = false;
        this.resolve?.(ok);
        this.resolve = null;
        setTimeout(() => (this.active = false), 0);
    },
};
