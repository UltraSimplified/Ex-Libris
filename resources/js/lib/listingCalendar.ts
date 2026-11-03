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

type Dated = { start_date?: string | null; end_date?: string | null };

function dayKey(date: Date): string {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

/**
 * Each day something is on, from today: a run that began last month is only
 * still to come from here on. A listing with no end date is on for one day.
 */
function listingsByDay<T extends Dated>(
    listings: T[],
    today: Date,
): Map<string, T[]> {
    const byDay = new Map<string, T[]>();

    for (const listing of listings) {
        const start = parseDay(listing.start_date);

        if (!start) {
            continue;
        }

        const end = parseDay(listing.end_date) ?? start;
        const last = end > start ? end : start;
        const day = new Date(Math.max(start.getTime(), today.getTime()));

        while (day <= last) {
            const key = dayKey(day);

            byDay.set(key, [...(byDay.get(key) ?? []), listing]);
            day.setDate(day.getDate() + 1);
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
    const first = new Date(year, month - 1, 1);
    const daysInMonth = new Date(year, month, 0).getDate();
    const cells: (CalendarDay<T> | null)[] = Array(
        (first.getDay() + 6) % 7,
    ).fill(null);

    for (let number = 1; number <= daysInMonth; number++) {
        const date = new Date(year, month - 1, number);
        const dayOfMonth = dayKey(date);

        cells.push({
            key: dayOfMonth,
            date,
            isToday: dayOfMonth === dayKey(today),
            isPast: date < today,
            listings: byDay.get(dayOfMonth) ?? [],
        });
    }

    while (cells.length % 7 > 0) {
        cells.push(null);
    }

    const weeks: (CalendarDay<T> | null)[][] = [];

    for (let index = 0; index < cells.length; index += 7) {
        weeks.push(cells.slice(index, index + 7));
    }

    return {
        key,
        title: first.toLocaleDateString('en-GB', {
            month: 'long',
            year: 'numeric',
        }),
        weeks,
    };
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
