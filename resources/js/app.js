import jalaliDate from './components/jalali-date';
import slider from './components/slider';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('jalaliDate', jalaliDate);
    window.Alpine.data('slider', slider);
});
