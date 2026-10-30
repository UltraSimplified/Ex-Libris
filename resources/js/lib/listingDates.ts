const weekdays = ['SUN', 'MON', 'TUES', 'WED', 'THURS', 'FRI', 'SAT'];

const months = [
    'JAN',
    'FEB',
    'MAR',
    'APR',
    'MAY',
    'JUN',
    'JUL',
    'AUG',
    'SEP',
    'OCT',
    'NOV',
    'DEC',
];

/**
 * A day handed out as "2026-10-22", read as that day wherever the reader
 * is, rather than as midnight in UTC, which is the evening before west of
 * Greenwich.
 */
function parseDay(raw: string | null | undefined): Date | null {
    if (!raw) {
        return null;
    }

    const [year, month, day] = raw.slice(0, 10).split('-').map(Number);
    const date = new Date(year, month - 1, day);

    return isNaN(date.getTime()) ? null : date;
}

function weekdayAndDay(date: Date): string {
    return `${weekdays[date.getDay()]} ${date.getDate()}`;
}

function month(date: Date): string {
    return months[date.getMonth()];
}

function shortYear(date: Date): string {
    return String(date.getFullYear()).slice(-2);
}

/**
 * When a run goes from its first day to its last:
 *
 *   THURS 22 OCT
 *   THURS 22 - FRI 23 OCT
 *   THURS 22 OCT - THURS 26 NOV
 *   THURS 22 DEC 26 - THURS 21 JAN 27
 *
 * Only a run crossing into another year carries the year.
 */
function formatRun(start: Date, end: Date | null): string {
    if (!end || end.getTime() <= start.getTime()) {
        return `${weekdayAndDay(start)} ${month(start)}`;
    }

    if (start.getFullYear() !== end.getFullYear()) {
        return `${weekdayAndDay(start)} ${month(start)} ${shortYear(start)} - ${weekdayAndDay(end)} ${month(end)} ${shortYear(end)}`;
    }

    if (start.getMonth() !== end.getMonth()) {
        return `${weekdayAndDay(start)} ${month(start)} - ${weekdayAndDay(end)} ${month(end)}`;
    }

    return `${weekdayAndDay(start)} - ${weekdayAndDay(end)} ${month(end)}`;
}

/**
 * When a listing runs, with anything its two dates cannot say in brackets
 * after them: "THURS 22 OCT - THURS 26 NOV (Thursdays only)".
 */
export function formatListingDates(listing: {
    start_date?: string | null;
    end_date?: string | null;
    additional_date_information?: string | null;
}): string {
    const start = parseDay(listing.start_date);
    const dates = start ? formatRun(start, parseDay(listing.end_date)) : '';
    const additional = listing.additional_date_information?.trim();

    if (!additional) {
        return dates;
    }

    return dates ? `${dates} (${additional})` : `(${additional})`;
}
