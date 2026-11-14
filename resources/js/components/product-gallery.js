import Swiper from 'swiper';
import { Keyboard } from 'swiper/modules';
import 'swiper/css';

const persianDigits = (value) => String(value).replace(/\d/g, (digit) => '۰۱۲۳۴۵۶۷۸۹'[digit]);

/**
 * Alpine state for the product page gallery (roadmap B9.5): a Swiper of the photos (swipe on touch, arrows on the
 * keyboard; right to left follows the page), thumbnails and dots that pick a photo, a «۱ / ۳» counter, hover zoom
 * on desktop and a full-screen view that zooms on tap. <div x-data="productGallery(3)"> with x-ref="slider".
 */
export default (count = 0) => ({
    swiper: null,
    count,
    active: 0,
    fullscreen: false,
    zoomed: false,

    init() {
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        this.swiper = new Swiper(this.$refs.slider, {
            modules: [Keyboard],
            speed: reducedMotion ? 0 : 300,
            watchOverflow: true,
            keyboard: { enabled: true, onlyInViewport: true },
            on: { slideChange: (swiper) => (this.active = swiper.activeIndex) },
        });
    },

    destroy() {
        this.swiper?.destroy();
    },

    show(index) {
        this.active = index;
        this.swiper?.slideTo(index);
    },

    step(by) {
        this.zoomed = false;
        this.show((this.active + by + this.count) % this.count);
    },

    counter() {
        return `${persianDigits(this.active + 1)} / ${persianDigits(this.count)}`;
    },

    open() {
        this.zoomed = false;
        this.fullscreen = true;
        document.documentElement.style.overflow = 'hidden';
    },

    close() {
        this.fullscreen = false;
        document.documentElement.style.overflow = '';
        this.$refs.slider?.focus();
    },

    /** Hover zoom on desktop (pointer that hovers, wide screen): the photo doubles around the cursor. */
    lens(event) {
        if (!window.matchMedia('(hover: hover) and (min-width: 1280px)').matches) {
            return;
        }

        const image = event.currentTarget.querySelector('img');
        const box = event.currentTarget.getBoundingClientRect();

        if (image) {
            image.style.transformOrigin = `${((event.clientX - box.left) / box.width) * 100}% ${((event.clientY - box.top) / box.height) * 100}%`;
            image.style.transform = 'scale(2)';
        }
    },

    unlens(event) {
        const image = event.currentTarget.querySelector('img');

        if (image) {
            image.style.transform = '';
        }
    },

    /** Full-screen view: a tap zooms in around the tap point, another tap zooms out. */
    toggleZoom(event) {
        const box = event.currentTarget.getBoundingClientRect();
        event.currentTarget.style.transformOrigin = `${((event.clientX - box.left) / box.width) * 100}% ${((event.clientY - box.top) / box.height) * 100}%`;
        this.zoomed = !this.zoomed;
    },
});
