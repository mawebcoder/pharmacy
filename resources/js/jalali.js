/**
 * Jalali (Shamsi) ⇄ Gregorian conversion, based on the Borkowski algorithm used by jalaali-js.
 * All months are 1-based. Valid for Jalali years -61 … 3177.
 */

const BREAKS = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];

export const MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

// Saturday-first, as on Iranian calendars.
export const WEEKDAYS = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];

const div = (a, b) => ~~(a / b);
const mod = (a, b) => a - ~~(a / b) * b;

function jalCal(jy, withoutLeap = false) {
    const gy = jy + 621;
    let leapJ = -14;
    let jp = BREAKS[0];
    let jump = 0;

    if (jy < jp || jy >= BREAKS[BREAKS.length - 1]) {
        throw new RangeError(`Invalid Jalali year ${jy}`);
    }

    for (let i = 1; i < BREAKS.length; i++) {
        const jm = BREAKS[i];
        jump = jm - jp;
        if (jy < jm) break;
        leapJ += div(jump, 33) * 8 + div(mod(jump, 33), 4);
        jp = jm;
    }

    let n = jy - jp;
    leapJ += div(n, 33) * 8 + div(mod(n, 33) + 3, 4);
    if (mod(jump, 33) === 4 && jump - n === 4) leapJ += 1;

    const leapG = div(gy, 4) - div((div(gy, 100) + 1) * 3, 4) - 150;
    const march = 20 + leapJ - leapG;

    if (withoutLeap) return { gy, march };

    if (jump - n < 6) n = n - jump + div(jump + 4, 33) * 33;
    let leap = mod(mod(n + 1, 33) - 1, 4);
    if (leap === -1) leap = 4;

    return { leap, gy, march };
}

function g2d(gy, gm, gd) {
    const d = div((gy + div(gm - 8, 6) + 100100) * 1461, 4) + div(153 * mod(gm + 9, 12) + 2, 5) + gd - 34840408;

    return d - div(div(gy + 100100 + div(gm - 8, 6), 100) * 3, 4) + 752;
}

function d2g(jdn) {
    let j = 4 * jdn + 139361631;
    j += div(div(4 * jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
    const i = div(mod(j, 1461), 4) * 5 + 308;
    const gd = div(mod(i, 153), 5) + 1;
    const gm = mod(div(i, 153), 12) + 1;
    const gy = div(j, 1461) - 100100 + div(8 - gm, 6);

    return { gy, gm, gd };
}

function j2d(jy, jm, jd) {
    const { gy, march } = jalCal(jy, true);

    return g2d(gy, 3, march) + (jm - 1) * 31 - div(jm, 7) * (jm - 7) + jd - 1;
}

function d2j(jdn) {
    const { gy } = d2g(jdn);
    let jy = gy - 621;
    const { leap, march } = jalCal(jy);
    let k = jdn - g2d(gy, 3, march);

    if (k >= 0) {
        if (k <= 185) {
            return { jy, jm: 1 + div(k, 31), jd: mod(k, 31) + 1 };
        }
        k -= 186;
    } else {
        jy -= 1;
        k += 179;
        if (leap === 1) k += 1;
    }

    return { jy, jm: 7 + div(k, 30), jd: mod(k, 30) + 1 };
}

export const toJalali = (gy, gm, gd) => d2j(g2d(gy, gm, gd));

export const toGregorian = (jy, jm, jd) => d2g(j2d(jy, jm, jd));

export const isLeapYear = (jy) => jalCal(jy).leap === 0;

export const monthLength = (jy, jm) => (jm <= 6 ? 31 : jm <= 11 ? 30 : isLeapYear(jy) ? 30 : 29);

/** 0 = Saturday … 6 = Friday. */
export const weekday = (jy, jm, jd) => mod(j2d(jy, jm, jd) + 2, 7);

const pad = (n) => String(n).padStart(2, '0');

/** Gregorian ISO date (Y-m-d) of a Jalali date. */
export function jalaliToIso(jy, jm, jd) {
    const { gy, gm, gd } = toGregorian(jy, jm, jd);

    return `${gy}-${pad(gm)}-${pad(gd)}`;
}

/** Jalali parts of a Gregorian ISO date (Y-m-d, extra time part ignored), or null when unparsable. */
export function isoToJalali(iso) {
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso ?? '');

    return match ? toJalali(+match[1], +match[2], +match[3]) : null;
}

export const persianDigits = (value) => String(value).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
