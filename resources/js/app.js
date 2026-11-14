import jalaliDate from './components/jalali-date';
import slider from './components/slider';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('jalaliDate', jalaliDate);
    window.Alpine.data('slider', slider);

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
