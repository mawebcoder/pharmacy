import Swiper from 'swiper';
import { Keyboard, Navigation } from 'swiper/modules';
import 'swiper/css';

/**
 * Alpine state for Swiper sliders: the home page banners (<x-content::banners>) and the collection rows. Swiper
 * handles swiping with touch, pen and mouse in every browser, the side arrows (x-ref previous / next) and the
 * keyboard; right to left follows the page. No autoplay. Options are passed to Swiper, e.g.
 * x-data="slider({ rewind: true })" or x-data="slider({ slidesPerView: 2.3, breakpoints: { … } })".
 */
export default (options = {}) => ({
    swiper: null,

    init() {
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        this.swiper = new Swiper(this.$refs.slider, {
            modules: [Keyboard, Navigation],
            speed: reducedMotion ? 0 : 400,
            grabCursor: true,
            watchOverflow: true,
            keyboard: { enabled: true, onlyInViewport: true },
            navigation: { prevEl: this.$refs.previous, nextEl: this.$refs.next },
            ...options,
        });
    },

    destroy() {
        this.swiper?.destroy();
    },
});
