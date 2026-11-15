/**
 * Swipe down to close a bottom sheet on phones (owner, 2026-11-15: every modal on phones; TallStackUI modals use their
 * own `handle` option instead). Put it on the sheet's grab handle:
 * x-swipe-close="$tsui.close.slide('listing-filters')". The sheet (the nearest ancestor with x-show, the panel that
 * slides) follows the finger, and the sheet's title row is a handle too (its buttons keep working). Released after more
 * than 80 px, or flicked down, the sheet slides away and the expression runs; otherwise it springs back.
 */
const CLOSE_AFTER_PX = 80;
const FLICK_PX_PER_MS = 0.5;

export default function swipeClose(el, { expression }, { evaluateLater, cleanup }) {
    const close = evaluateLater(expression);
    const sheet = el.parentElement?.closest('[x-show]') ?? el;
    const titleRow = sheet.querySelector('h2')?.parentElement;
    const handles = [el, titleRow].filter(Boolean);
    const duration = () => (window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 200);
    let startY = 0;
    let startedAt = 0;
    let distance = 0;
    let dragging = false;

    const settle = (transform, after) => {
        sheet.style.transition = `transform ${duration()}ms ease-out`;
        sheet.style.transform = transform;
        setTimeout(after, duration());
    };

    const down = (event) => {
        // From tablets up these panels are centred dialogs or side panels, not sheets.
        if (window.matchMedia('(min-width: 768px)').matches || event.button > 0 || event.target.closest('button, a, input')) {
            return;
        }

        dragging = true;
        startY = event.clientY;
        startedAt = performance.now();
        distance = 0;
        sheet.style.transition = 'none';
        try {
            event.currentTarget.setPointerCapture(event.pointerId);
        } catch {
            // The pointer is already gone; moves still arrive while it stays over the handle.
        }
    };

    const move = (event) => {
        if (! dragging) {
            return;
        }

        distance = Math.max(0, event.clientY - startY);
        sheet.style.transform = `translateY(${distance}px)`;
    };

    const up = (event) => {
        if (! dragging) {
            return;
        }

        dragging = false;
        const speed = distance / Math.max(1, performance.now() - startedAt);

        if (event.type !== 'pointercancel' && (distance > CLOSE_AFTER_PX || (distance > 20 && speed > FLICK_PX_PER_MS))) {
            // Keep it down while the sheet's own leave transition runs, then give the panel back its styles.
            settle('translateY(100%)', () => {
                close();
                setTimeout(() => sheet.style.removeProperty('transform'), 1000);
                sheet.style.removeProperty('transition');
            });

            return;
        }

        settle('', () => sheet.style.removeProperty('transition'));
    };

    for (const handle of handles) {
        handle.style.touchAction = 'none';
        handle.addEventListener('pointerdown', down);
        handle.addEventListener('pointermove', move);
        handle.addEventListener('pointerup', up);
        handle.addEventListener('pointercancel', up);
    }

    cleanup(() => {
        for (const handle of handles) {
            handle.removeEventListener('pointerdown', down);
            handle.removeEventListener('pointermove', move);
            handle.removeEventListener('pointerup', up);
            handle.removeEventListener('pointercancel', up);
        }
    });
}
