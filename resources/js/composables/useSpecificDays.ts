import type { ComputedRef, MaybeRefOrGetter, Ref } from 'vue';
import { computed, ref, toValue } from 'vue';
import { dayKey, monthTitle, monthWeeks } from '@/lib/listingCalendar';
import { parseDay } from '@/lib/listingDates';

/** The weekdays, Monday first, as the calendar's columns run. */
export const WEEKDAYS = [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday',
] as const;

export type SpecificDay = {
    key: string;
    date: Date;
    inRun: boolean;
    isToday: boolean;
};

export type SpecificDaysMonth = {
    key: string;
    title: string;
    weeks: (SpecificDay | null)[][];
};

export type SpecificDays = {
    /** Whether "Set specific days" is ticked. */
    enabled: Ref<boolean>;
    /** Every ticked day, including any the run no longer reaches. */
    chosen: Ref<Set<string>>;
    /** Every day of the run in order, or none unless the end is after the start. */
    runDays: ComputedRef<string[]>;
    /** What is shown ticked, and what is sent. */
    chosenInRun: ComputedRef<string[]>;
    /** How many ticked days the run no longer reaches. */
    leftOut: ComputedRef<number>;
    months: ComputedRef<SpecificDaysMonth[]>;
    runDaysOn: (column: number) => string[];
    weekdayTicked: (column: number) => boolean;
    toggleDay: (day: string) => void;
    toggleWeekday: (column: number) => void;
    selectAll: () => void;
    clearAll: () => void;
};

/**
 * The days a multi-day event is actually on, as both calendars tick them:
 * the Dashboard's and the Office's, which differ only in how they look.
 *
 * Ticked days the run no longer reaches are kept back rather than thrown
 * away, so a date mistyped on the way to the right one costs nothing, but
 * only the days inside the run count as chosen.
 */
export function useSpecificDays(
    start: MaybeRefOrGetter<string>,
    end: MaybeRefOrGetter<string>,
    initialDays: string[] = [],
): SpecificDays {
    const todayKey = dayKey(new Date());

    const enabled = ref<boolean>(initialDays.length > 0);
    const chosen = ref<Set<string>>(new Set(initialDays));

    const runDays = computed<string[]>(() => {
        const first = parseDay(toValue(start));
        const last = parseDay(toValue(end));

        if (!first || !last || last <= first) {
            return [];
        }

        const days: string[] = [];

        for (
            const day = new Date(first);
            day <= last;
            day.setDate(day.getDate() + 1)
        ) {
            days.push(dayKey(day));
        }

        return days;
    });

    const inRun = computed<Set<string>>(() => new Set(runDays.value));

    const chosenInRun = computed<string[]>(() =>
        runDays.value.filter((day) => chosen.value.has(day)),
    );

    const leftOut = computed<number>(
        () => [...chosen.value].filter((day) => !inRun.value.has(day)).length,
    );

    /** The run's days that fall on a weekday, counted from Monday as 0. */
    const runDaysOn = (column: number): string[] =>
        runDays.value.filter(
            (day) => ((parseDay(day)?.getDay() ?? 0) + 6) % 7 === column,
        );

    /** Whether every one of a weekday in the run is ticked, so its chip is on. */
    const weekdayTicked = (column: number): boolean => {
        const days = runDaysOn(column);

        return days.length > 0 && days.every((day) => chosen.value.has(day));
    };

    const months = computed<SpecificDaysMonth[]>(() =>
        [...new Set(runDays.value.map((day) => day.slice(0, 7)))].map(
            (key) => {
                const [year, month] = key.split('-').map(Number);

                return {
                    key,
                    title: monthTitle(year, month),
                    weeks: monthWeeks(year, month, (date): SpecificDay => {
                        const dayOfMonth = dayKey(date);

                        return {
                            key: dayOfMonth,
                            date,
                            inRun: inRun.value.has(dayOfMonth),
                            isToday: dayOfMonth === todayKey,
                        };
                    }),
                };
            },
        ),
    );

    const toggleDay = (day: string): void => {
        const next = new Set(chosen.value);

        if (next.has(day)) {
            next.delete(day);
        } else {
            next.add(day);
        }

        chosen.value = next;
    };

    /**
     * Every one of a weekday in the run: ticked, or unticked when they all
     * already are.
     */
    const toggleWeekday = (column: number): void => {
        const days = runDaysOn(column);
        const next = new Set(chosen.value);
        const allTicked = days.every((day) => next.has(day));

        for (const day of days) {
            if (allTicked) {
                next.delete(day);
            } else {
                next.add(day);
            }
        }

        chosen.value = next;
    };

    const selectAll = (): void => {
        chosen.value = new Set([...chosen.value, ...runDays.value]);
    };

    const clearAll = (): void => {
        chosen.value = new Set();
    };

    return {
        enabled,
        chosen,
        runDays,
        chosenInRun,
        leftOut,
        months,
        runDaysOn,
        weekdayTicked,
        toggleDay,
        toggleWeekday,
        selectAll,
        clearAll,
    };
}
