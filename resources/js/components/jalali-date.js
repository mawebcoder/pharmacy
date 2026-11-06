import { isoToJalali, jalaliToIso, monthLength, MONTHS, persianDigits, WEEKDAYS, weekday } from '../jalali';

/**
 * Alpine state for <x-jalali-date>. The bound value is always a Gregorian ISO date (Y-m-d),
 * so Livewire properties, validation and the database never see Jalali strings.
 */
export default ({ value = null, today, min = null, max = null }) => ({
    value,
    open: false,
    view: 'days',
    cursor: { jy: 0, jm: 1 },
    yearPage: 0,
    months: MONTHS,
    weekdays: WEEKDAYS,
    fa: persianDigits,

    init() {
        this.resetCursor();
        this.$watch('value', () => this.resetCursor());
    },

    get selected() {
        return isoToJalali(this.value);
    },

    get display() {
        const date = this.selected;

        return date ? persianDigits(`${date.jd} ${MONTHS[date.jm - 1]} ${date.jy}`) : '';
    },

    get days() {
        const { jy, jm } = this.cursor;
        const cells = Array.from({ length: weekday(jy, jm, 1) }, (_, i) => ({ key: `blank-${i}`, blank: true }));

        for (let jd = 1; jd <= monthLength(jy, jm); jd++) {
            const iso = jalaliToIso(jy, jm, jd);

            cells.push({
                key: iso,
                iso,
                jd,
                disabled: this.outOfRange(iso, iso),
                today: iso === today,
                selected: iso === this.value,
                friday: cells.length % 7 === 6,
            });
        }

        return cells;
    },

    get years() {
        return Array.from({ length: 12 }, (_, i) => this.yearPage + i);
    },

    outOfRange(firstIso, lastIso) {
        return (min !== null && lastIso < min) || (max !== null && firstIso > max);
    },

    monthDisabled(jm) {
        const { jy } = this.cursor;

        return this.outOfRange(jalaliToIso(jy, jm, 1), jalaliToIso(jy, jm, monthLength(jy, jm)));
    },

    yearDisabled(jy) {
        return this.outOfRange(jalaliToIso(jy, 1, 1), jalaliToIso(jy, 12, monthLength(jy, 12)));
    },

    resetCursor() {
        const { jy, jm } = this.selected ?? isoToJalali(today);
        this.cursor = { jy, jm };
    },

    toggle() {
        this.open ? this.close() : this.show();
    },

    show() {
        this.resetCursor();
        this.view = 'days';
        this.open = true;
    },

    close() {
        this.open = false;
    },

    move(months) {
        const index = this.cursor.jy * 12 + this.cursor.jm - 1 + months;
        this.cursor = { jy: Math.floor(index / 12), jm: (index % 12) + 1 };
    },

    pick(iso) {
        this.value = iso;
        this.close();
    },

    pickMonth(jm) {
        this.cursor.jm = jm;
        this.view = 'days';
    },

    pickYear(jy) {
        this.cursor.jy = jy;
        this.view = 'months';
    },

    showYears() {
        this.yearPage = this.cursor.jy - (this.cursor.jy % 12);
        this.view = 'years';
    },

    get todayDisabled() {
        return this.outOfRange(today, today);
    },

    pickToday() {
        if (!this.todayDisabled) this.pick(today);
    },

    clear() {
        this.value = null;
        this.close();
    },
});
