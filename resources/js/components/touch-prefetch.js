/**
 * Phones have no hover, so a link marked wire:navigate.hover (product cards, brands, categories, «مشاهده همه»;
 * owner, 2026-11-28) starts loading its page as soon as a finger touches it, not when the finger lifts. It goes
 * through Livewire's own hover prefetch (60 ms, then the page is kept for the click), so the tap uses the same
 * response. A finger that moves more than 10 px is scrolling: the prefetch is called off before it starts.
 */
const SCROLL_SLOP_PX = 10;

export default function touchPrefetch() {
    let link = null;
    let startX = 0;
    let startY = 0;

    const callOff = () => {
        link?.dispatchEvent(new Event('mouseleave'));
        link = null;
    };

    document.addEventListener('touchstart', (event) => {
        link = event.touches.length === 1 ? event.target.closest?.('a[wire\\:navigate\\.hover]') ?? null : null;

        if (link) {
            startX = event.touches[0].clientX;
            startY = event.touches[0].clientY;
            link.dispatchEvent(new Event('mouseenter'));
        }
    }, { capture: true, passive: true });

    document.addEventListener('touchmove', (event) => {
        if (link && Math.hypot(event.touches[0].clientX - startX, event.touches[0].clientY - startY) > SCROLL_SLOP_PX) {
            callOff();
        }
    }, { capture: true, passive: true });

    document.addEventListener('touchcancel', callOff, { capture: true, passive: true });
    document.addEventListener('touchend', () => (link = null), { capture: true, passive: true });
}
