/**
 * The shopper's recently viewed products (roadmap B9.5, S13): product ids, newest first, kept in localStorage (no
 * request, so guests have it too). The product page adds its own id; its rail sends the list to the server once.
 * Storage can be missing or full; every access is guarded.
 */
const KEY = 'recently-viewed';
const LIMIT = 13;

const read = () => {
    try {
        const ids = JSON.parse(localStorage.getItem(KEY) ?? '[]');

        return Array.isArray(ids) ? ids.filter((id) => Number.isInteger(id) && id > 0) : [];
    } catch {
        return [];
    }
};

export default {
    ids: [],

    init() {
        this.ids = read().slice(0, LIMIT);
    },

    add(id) {
        this.ids = [id, ...this.ids.filter((seen) => seen !== id)].slice(0, LIMIT);

        try {
            localStorage.setItem(KEY, JSON.stringify(this.ids));
        } catch {
            // Private mode or full storage: the list lives for this page only.
        }
    },
};
