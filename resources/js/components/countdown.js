// Flash deal countdown (roadmap B10, S28): «HH:MM:SS» in Persian digits until `endsAt` (ISO time), hours over 24
// included. At zero it stays at «۰۰:۰۰:۰۰» and, when given, calls `onEnd` once (e.g. to refresh the deals).
const toPersian = (text) => String(text).replace(/\d/g, (digit) => '۰۱۲۳۴۵۶۷۸۹'[digit]);

export default (endsAt, onEnd = null) => ({
    remaining: '',
    timer: null,
    init() {
        const end = new Date(endsAt).getTime();
        const tick = () => {
            const seconds = Math.max(0, Math.floor((end - Date.now()) / 1000));
            const parts = [Math.floor(seconds / 3600), Math.floor((seconds % 3600) / 60), seconds % 60];
            this.remaining = toPersian(parts.map((part) => String(part).padStart(2, '0')).join(':'));

            if (seconds === 0) {
                clearInterval(this.timer);
                if (onEnd) {
                    onEnd();
                }
            }
        };
        tick();
        this.timer = setInterval(tick, 1000);
    },
    destroy() {
        clearInterval(this.timer);
    },
});
