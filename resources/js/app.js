import jalaliDate from './components/jalali-date';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('jalaliDate', jalaliDate);
});
