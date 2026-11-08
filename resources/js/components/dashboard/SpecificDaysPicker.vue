<script setup lang="ts">
import { useSpecificDays, WEEKDAYS } from '@/composables/useSpecificDays';
import InputError from '../InputError.vue';

/**
 * The days a multi-day event is actually on, ticked on a calendar of its
 * run: a pub quiz every other Tuesday, a festival at weekends, a gallery
 * closed on Mondays (Select all, then Mon).
 *
 * Offered only once the end date comes after the start. Only the days
 * inside the run are sent, and the person is told about the rest. How the
 * calendar behaves is shared with the Office's (useSpecificDays); this is
 * the Dashboard's look over it.
 */
const props = withDefaults(
    defineProps<{
        /** The run's first day, YYYY-MM-DD, or empty. */
        start: string;
        /** The run's last day, YYYY-MM-DD, or empty. */
        end: string;
        /** The days already chosen, YYYY-MM-DD. */
        initialDays?: string[];
        error?: string;
    }>(),
    {
        initialDays: () => [],
        error: undefined,
    },
);

const {
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
} = useSpecificDays(
    () => props.start,
    () => props.end,
    props.initialDays,
);

/**
 * The listings page's genre chips: black until chosen, hi-vis when on or
 * pointed at.
 */
const chipClass = (on: boolean): string =>
    on
        ? 'bg-hivis text-tarmac'
        : 'bg-tarmac text-white hover:bg-hivis hover:text-tarmac';

const longDate = (date: Date): string =>
    date.toLocaleDateString('en-GB', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
</script>

<template>
    <div v-if="runDays.length" class="grid gap-3">
        <label class="flex items-center gap-2 text-sm">
            <input
                v-model="enabled"
                type="checkbox"
                name="set_specific_days"
                value="1"
                class="size-4 rounded border-input"
            />
            Set specific days
        </label>

        <div
            v-if="enabled"
            role="group"
            aria-labelledby="specific-days-help"
            class="grid w-full max-w-sm gap-4 rounded-md border border-input p-3"
        >
            <p id="specific-days-help" class="text-sm text-muted-foreground">
                Tick the days the event is on, or tap Mon, Tue and so on to tick
                every one of that weekday. When you save, the start and end
                dates move to the first and last day you tick.
            </p>

            <!-- Chips, as the genres on the listings page are. -->
            <div class="flex justify-between gap-2">
                <button
                    v-for="control in [
                        { label: 'Select all', act: selectAll },
                        { label: 'Clear all', act: clearAll },
                    ]"
                    :key="control.label"
                    type="button"
                    class="cursor-pointer rounded-sm px-4 py-2 font-din text-sm whitespace-nowrap uppercase"
                    :class="chipClass(false)"
                    @click="control.act"
                >
                    {{ control.label }}
                </button>
            </div>

            <!--
                The weekdays head the columns, each a chip that is on while
                every one of that day in the run is ticked.
            -->
            <div class="grid grid-cols-7 gap-1">
                <button
                    v-for="(weekday, column) in WEEKDAYS"
                    :key="weekday"
                    type="button"
                    :aria-label="`Every ${weekday}`"
                    :aria-pressed="weekdayTicked(column)"
                    :disabled="runDaysOn(column).length === 0"
                    class="flex h-8 cursor-pointer items-center justify-center rounded-sm font-din text-xs uppercase disabled:cursor-default disabled:opacity-40"
                    :class="chipClass(weekdayTicked(column))"
                    @click="toggleWeekday(column)"
                >
                    {{ weekday.slice(0, 3) }}
                </button>
            </div>

            <!-- Ticked days are picked out as the bookmarks calendar's are. -->
            <div v-for="month in months" :key="month.key" class="grid gap-1">
                <p class="pb-2 font-din text-sm font-bold uppercase">
                    {{ month.title }}
                </p>
                <div
                    v-for="(week, row) in month.weeks"
                    :key="row"
                    class="grid grid-cols-7 gap-1"
                >
                    <template v-for="(day, column) in week" :key="day?.key ?? column">
                        <button
                            v-if="day?.inRun"
                            type="button"
                            :data-day="day.key"
                            :aria-label="longDate(day.date)"
                            :aria-pressed="chosen.has(day.key)"
                            :aria-current="day.isToday ? 'date' : undefined"
                            class="mx-auto flex aspect-square w-full max-w-10 cursor-pointer items-center justify-center rounded-full tabular-nums hover:ring-2 hover:ring-foreground"
                            :class="{
                                'bg-hivis font-bold text-tarmac': chosen.has(day.key),
                                'underline decoration-2 underline-offset-4': day.isToday,
                            }"
                            @click="toggleDay(day.key)"
                        >
                            {{ day.date.getDate() }}
                        </button>
                        <span
                            v-else-if="day"
                            aria-hidden="true"
                            class="mx-auto flex aspect-square w-full max-w-10 items-center justify-center text-muted-foreground/60 tabular-nums"
                            :class="{
                                'font-bold underline decoration-2 underline-offset-4': day.isToday,
                            }"
                        >
                            {{ day.date.getDate() }}
                        </span>
                        <span v-else />
                    </template>
                </div>
            </div>

            <div class="grid gap-1" aria-live="polite">
                <p class="text-sm" data-chosen-count>
                    {{ chosenInRun.length }} of {{ runDays.length }} days chosen
                </p>
                <p v-if="leftOut" class="text-sm text-muted-foreground">
                    {{
                        leftOut === 1
                            ? '1 chosen day is'
                            : `${leftOut} chosen days are`
                    }}
                    outside the new dates, so will not be saved.
                </p>
            </div>

            <input
                v-for="day in chosenInRun"
                :key="day"
                type="hidden"
                name="specific_days[]"
                :value="day"
            />
        </div>

        <InputError :message="error" />
    </div>
</template>
