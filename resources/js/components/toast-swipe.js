/**
 * Swipe a toast sideways to dismiss it (owner, 2026-11-15: the free-shipping toast, so every toast). TallStackUI's
 * toasts have no swipe, so one listener on the document follows a press on any toast: the toast moves and fades with
 * the finger, and past 80 px it closes as its «×» does; otherwise it springs back. Presses on its buttons are left
 * alone. app.css sets `touch-action: pan-y` on toasts so the page still scrolls vertically.
 */
const TOAST = '[x-data^="tallstackui_toastLoop"]';
const DISMISS_AFTER_PX = 80;

export default function swipeToasts() {
    let toast = null;
    let startX = 0;
    let distance = 0;

    document.addEventListener('pointerdown', (event) => {
        toast = event.button > 0 || event.target.closest('button, a') ? null : event.target.closest(TOAST);

        if (! toast) {
            return;
        }

        startX = event.clientX;
        distance = 0;
        toast.style.transition = 'none';
    });

    document.addEventListener('pointermove', (event) => {
        if (! toast) {
            return;
        }

        distance = event.clientX - startX;
        toast.style.transform = `translateX(${distance}px)`;
        toast.style.opacity = String(Math.max(0.2, 1 - Math.abs(distance) / 240));
    });

    const release = () => {
        if (! toast) {
            return;
        }

        const swiped = toast;
        toast = null;
        swiped.style.transition = 'transform 150ms ease-out, opacity 150ms ease-out';

        if (Math.abs(distance) > DISMISS_AFTER_PX) {
            swiped.style.transform = `translateX(${Math.sign(distance) * 120}%)`;
            swiped.style.opacity = '0';
            setTimeout(() => window.Alpine.$data(swiped).hide(true, false), 150);

            return;
        }

        swiped.style.transform = '';
        swiped.style.opacity = '';
    };

    document.addEventListener('pointerup', release);
    document.addEventListener('pointercancel', release);
}
