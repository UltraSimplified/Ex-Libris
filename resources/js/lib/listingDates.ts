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
export function parseDay(raw: string | null | undefined): Date | null {
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

const weekdayNames = [
    'SUNDAY',
    'MONDAY',
    'TUESDAY',
    'WEDNESDAY',
    'THURSDAY',
    'FRIDAY',
    'SATURDAY',
];

const monthNames = [
    'JANUARY',
    'FEBRUARY',
    'MARCH',
    'APRIL',
    'MAY',
    'JUNE',
    'JULY',
    'AUGUST',
    'SEPTEMBER',
    'OCTOBER',
    'NOVEMBER',
    'DECEMBER',
];

/** A heading over a run of listings, and what tells one from the next. */
export interface ListingHeading {
    key: string;
    label: string;
}

function isSameDay(one: Date, other: Date): boolean {
    return (
        one.getFullYear() === other.getFullYear() &&
        one.getMonth() === other.getMonth() &&
        one.getDate() === other.getDate()
    );
}

/**
 * The heading a listing goes under on a listings page, from the day the
 * server put it on, its next_day.
 *
 * Days up to the end of the month after the page's first day are headed
 * one by one: TODAY, TOMORROW, then "SATURDAY 14 NOV", the weekday in full
 * as a heading has the room for it, and the month and year, when it is not
 * this one, as a listing's own dates write them. Further out, where
 * nearly every day would be a heading over a single listing, the month is
 * headed as a whole: "JANUARY 2027", or plain "MAY" within this year.
 *
 * Today is the server's, not the reader's clock, so that TODAY sits over
 * the listings the server counted as on today.
 */
export function listingHeading(
    nextDay: string,
    currentDay: string,
    firstDay: string,
): ListingHeading | null {
    const day = parseDay(nextDay);
    const today = parseDay(currentDay);
    const first = parseDay(firstDay);

    if (!day || !today || !first) {
        return null;
    }

    const thisYear = day.getFullYear() === today.getFullYear();
    const lastDayHeaded = new Date(
        first.getFullYear(),
        first.getMonth() + 2,
        0,
    );

    if (day > lastDayHeaded) {
        const name = monthNames[day.getMonth()];

        return {
            key: nextDay.slice(0, 7),
            label: thisYear ? name : `${name} ${day.getFullYear()}`,
        };
    }

    const tomorrow = new Date(
        today.getFullYear(),
        today.getMonth(),
        today.getDate() + 1,
    );
    const key = nextDay.slice(0, 10);

    if (isSameDay(day, today)) {
        return { key, label: 'TODAY' };
    }

    if (isSameDay(day, tomorrow)) {
        return { key, label: 'TOMORROW' };
    }

    const label = `${weekdayNames[day.getDay()]} ${day.getDate()} ${month(day)}`;

    return { key, label: thisYear ? label : `${label} ${shortYear(day)}` };
}
