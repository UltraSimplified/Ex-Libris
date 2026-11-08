import { parseDay } from '@/lib/listingDates';

export interface CalendarDay<T> {
    /** The day as "2026-10-22". */
    key: string;
    date: Date;
    isToday: boolean;
    isPast: boolean;
    /** What is on, soonest finishing first as they arrived. */
    listings: T[];
}

export interface CalendarMonth<T> {
    /** The month as "2026-10". */
    key: string;
    title: string;
    /** Monday first, with null for the days belonging to the months beside it. */
    weeks: (CalendarDay<T> | null)[][];
}

type Dated = {
    start_date?: string | null;
    end_date?: string | null;
    /** The days it is on, when not every day of its run. */
    days?: { day: string }[];
};

/** A day as "2026-10-22", in the reader's own calendar. */
export function dayKey(date: Date): string {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

/**
 * A month as weeks, Monday first, each day made into whatever the caller
 * needs and null for the days belonging to the months beside it. Shared by
 * the bookmarks calendar and the Dashboard's specific-days picker, so the
 * two draw the same grid.
 *
 * The month is counted from 1, as in "2026-10".
 */
export function monthWeeks<D>(
    year: number,
    month: number,
    makeDay: (date: Date) => D,
): (D | null)[][] {
    const first = new Date(year, month - 1, 1);
    const daysInMonth = new Date(year, month, 0).getDate();
    const cells: (D | null)[] = Array((first.getDay() + 6) % 7).fill(null);

    for (let number = 1; number <= daysInMonth; number++) {
        cells.push(makeDay(new Date(year, month - 1, number)));
    }

    while (cells.length % 7 > 0) {
        cells.push(null);
    }

    const weeks: (D | null)[][] = [];

    for (let index = 0; index < cells.length; index += 7) {
        weeks.push(cells.slice(index, index + 7));
    }

    return weeks;
}

/** A month's name and year, as "October 2026". */
export function monthTitle(year: number, month: number): string {
    return new Date(year, month - 1, 1).toLocaleDateString('en-GB', {
        month: 'long',
        year: 'numeric',
    });
}

/**
 * The days a listing is on from today, as "2026-10-22": its specific days
 * when it has them, otherwise every day of its run. A run that began last
 * month is only still to come from here on, and a listing with no end date
 * is on for one day.
 */
function daysOn(listing: Dated, today: Date): string[] {
    if (listing.days?.length) {
        return listing.days
            .map(({ day }) => parseDay(day))
            .filter((date): date is Date => date !== null && date >= today)
            .map(dayKey);
    }

    const start = parseDay(listing.start_date);

    if (!start) {
        return [];
    }

    const end = parseDay(listing.end_date) ?? start;
    const last = end > start ? end : start;
    const days: string[] = [];

    for (
        const day = new Date(Math.max(start.getTime(), today.getTime()));
        day <= last;
        day.setDate(day.getDate() + 1)
    ) {
        days.push(dayKey(day));
    }

    return days;
}

/** Each day something is on, from today, with what is on it. */
function listingsByDay<T extends Dated>(
    listings: T[],
    today: Date,
): Map<string, T[]> {
    const byDay = new Map<string, T[]>();

    for (const listing of listings) {
        for (const key of daysOn(listing, today)) {
            byDay.set(key, [...(byDay.get(key) ?? []), listing]);
        }
    }

    return byDay;
}

function buildMonth<T>(
    key: string,
    byDay: Map<string, T[]>,
    today: Date,
): CalendarMonth<T> {
    const [year, month] = key.split('-').map(Number);
    const todayKey = dayKey(today);

    const weeks = monthWeeks(year, month, (date): CalendarDay<T> => {
        const dayOfMonth = dayKey(date);

        return {
            key: dayOfMonth,
            date,
            isToday: dayOfMonth === todayKey,
            isPast: date < today,
            listings: byDay.get(dayOfMonth) ?? [],
        };
    });

    return { key, title: monthTitle(year, month), weeks };
}

/**
 * The months with something on in them, in order. A month with nothing on is
 * left out rather than drawn empty, as on a phone it would be a screenful of
 * nothing between the dates that matter.
 */
export function calendarMonths<T extends Dated>(
    listings: T[],
    now: Date = new Date(),
): CalendarMonth<T>[] {
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const byDay = listingsByDay(listings, today);
    const months = [
        ...new Set([...byDay.keys()].map((key) => key.slice(0, 7))),
    ];

    return months.sort().map((key) => buildMonth(key, byDay, today));
}
