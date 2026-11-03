<script setup lang="ts">
import { computed, ref } from 'vue';
import BookmarkedListingCompact from '@/components/GOP/BookmarkedListingCompact.vue';
import { calendarMonths } from '@/lib/listingCalendar';
import type { CalendarDay } from '@/lib/listingCalendar';
import type { BookmarkedListing } from '@/types/global';

/**
 * The bookmarked listings as months of dates, with the days something is on
 * picked out. A picked out day opens what is on it beneath its month, as a
 * date alone says nothing about which event it is.
 */
const props = defineProps<{ listings: BookmarkedListing[] }>();

const weekdays = [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday',
];

const months = computed(() => calendarMonths(props.listings));

const openDay = ref<CalendarDay<BookmarkedListing> | null>(null);

function toggle(day: CalendarDay<BookmarkedListing>): void {
    openDay.value = openDay.value?.key === day.key ? null : day;
}

const isOpenInMonth = (monthKey: string): boolean =>
    openDay.value?.key.startsWith(monthKey) ?? false;

const longDate = (date: Date): string =>
    date.toLocaleDateString('en-GB', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    });

const describe = (day: CalendarDay<BookmarkedListing>): string =>
    `${longDate(day.date)}: ${day.listings.map(({ title }) => title).join(', ')}`;
</script>

<template>
    <!-- One column at every width, as on a phone. -->
    <div class="flex flex-col gap-8 px-5 pt-2 pb-6">
        <div v-for="month in months" :key="month.key">
            <table class="w-full table-fixed">
                <caption
                    class="pb-3 text-left font-din text-sm font-bold uppercase"
                >
                    {{
                        month.title
                    }}
                </caption>
                <thead>
                    <tr>
                        <th
                            v-for="weekday in weekdays"
                            :key="weekday"
                            scope="col"
                            class="pb-1 text-center font-din text-xs font-bold text-tyre uppercase"
                        >
                            <span aria-hidden="true">{{ weekday[0] }}</span>
                            <span class="sr-only">{{ weekday }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(week, index) in month.weeks" :key="index">
                        <td
                            v-for="(day, column) in week"
                            :key="day?.key ?? column"
                            class="p-0.5 text-center"
                        >
                            <button
                                v-if="day?.listings.length"
                                type="button"
                                :data-day="day.key"
                                :aria-label="describe(day)"
                                :aria-current="day.isToday ? 'date' : undefined"
                                :aria-expanded="openDay?.key === day.key"
                                class="mx-auto flex aspect-square w-full max-w-10 cursor-pointer items-center justify-center rounded-full bg-hivis font-bold text-tarmac tabular-nums hover:ring-2 hover:ring-foreground"
                                :class="{
                                    'ring-2 ring-foreground':
                                        openDay?.key === day.key,
                                    'underline decoration-2 underline-offset-4':
                                        day.isToday,
                                }"
                                @click="toggle(day)"
                            >
                                {{ day.date.getDate() }}
                            </button>
                            <span
                                v-else-if="day"
                                :aria-current="day.isToday ? 'date' : undefined"
                                class="mx-auto flex aspect-square w-full max-w-10 items-center justify-center tabular-nums"
                                :class="{
                                    'text-muted-foreground/60': day.isPast,
                                    'font-bold underline decoration-2 underline-offset-4':
                                        day.isToday,
                                }"
                            >
                                {{ day.date.getDate() }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="openDay && isOpenInMonth(month.key)" class="mt-4">
                <p class="font-din text-sm font-bold uppercase">
                    {{ longDate(openDay.date) }}
                </p>
                <ul class="-mx-2 mt-1">
                    <li v-for="listing in openDay.listings" :key="listing.id">
                        <BookmarkedListingCompact :listing="listing" />
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
