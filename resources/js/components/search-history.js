/**
 * The shopper's recent searches (owner, 2026-11-14), shared by every search box: the latest 6, newest first, kept in
 * localStorage (no request). A signed-in customer's searches from the server are merged in when a box opens.
 * Items are { q, at } with `at` in milliseconds. Storage can be missing or full; every access is guarded.
 */
const KEY = 'search-history';
const LIMIT = 6;

const read = () => {
    try {
        const items = JSON.parse(localStorage.getItem(KEY) ?? '[]');

        return Array.isArray(items) ? items.filter((item) => item && typeof item.q === 'string' && item.q.trim() !== '') : [];
    } catch {
        return [];
    }
};

export default {
    items: [],

    init() {
        this.items = read().slice(0, LIMIT);
    },

    add(q) {
        this.merge([{ q: String(q ?? '').trim(), at: Date.now() }]);
    },

    merge(incoming) {
        const seen = new Set();

        this.items = [...incoming, ...this.items]
            .filter((item) => item && typeof item.q === 'string' && item.q.trim() !== '')
            .sort((a, b) => (b.at ?? 0) - (a.at ?? 0))
            .filter((item) => {
                const key = item.q.trim().toLowerCase();

                return seen.has(key) ? false : seen.add(key);
            })
            .slice(0, LIMIT);
        this.save();
    },

    clear() {
        this.items = [];
        this.save();
    },

    save() {
        try {
            localStorage.setItem(KEY, JSON.stringify(this.items));
        } catch {
            // Private mode or full storage: the list lives for this page only.
        }
    },
};
