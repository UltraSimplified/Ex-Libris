<script setup lang="ts">
import { CalendarDays } from '@lucide/vue';
import { ref } from 'vue';

/**
 * A date the owner can type or pick, always shown day/month/year.
 *
 * A plain date input is drawn in the browser's own locale, so an owner
 * whose browser is set to American English saw month/day/year and could
 * not change it. Here the box is ordinary text, read as d/m/y whatever
 * the browser thinks, and the calendar button opens the browser's own
 * picker beside it. What is sent is a hidden YYYY-MM-DD under the field's
 * name, as the date input sent before, so the server is none the wiser.
 */
interface Props {
    id: string;
    name: string;
    /** YYYY-MM-DD, or empty for no date. */
    defaultValue?: string;
    required?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    defaultValue: '',
    required: false,
});

const INVALID_MESSAGE = 'Enter the date as day/month/year, e.g. 27/10/2026.';

/** YYYY-MM-DD as DD/MM/YYYY, or empty. */
const toDayMonthYear = (iso: string): string => {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso);

    return match ? `${match[3]}/${match[2]}/${match[1]}` : '';
};

/**
 * What was typed as YYYY-MM-DD: a day, a month and a year in that order,
 * split by slashes, dots, dashes or spaces, the year in two figures or
 * four. Null when it is no such date - the 31st of June included.
 */
const toIso = (typed: string): string | null => {
    const match =
        /^\s*(\d{1,2})[/.\-\s]+(\d{1,2})[/.\-\s]+(\d{2}|\d{4})\s*$/.exec(typed);

    if (!match) {
        return null;
    }

    const day = Number(match[1]);
    const month = Number(match[2]);
    const year =
        match[3].length === 2 ? 2000 + Number(match[3]) : Number(match[3]);
    const date = new Date(Date.UTC(year, month - 1, day));

    if (
        date.getUTCFullYear() !== year ||
        date.getUTCMonth() !== month - 1 ||
        date.getUTCDate() !== day
    ) {
        return null;
    }

    return date.toISOString().slice(0, 10);
};

const iso = ref<string>(props.defaultValue);
const typed = ref<string>(toDayMonthYear(props.defaultValue));
const textInput = ref<HTMLInputElement | null>(null);
const pickerInput = ref<HTMLInputElement | null>(null);
const invalid = ref<boolean>(false);

/**
 * Kept in step as the owner types, so a date typed and submitted at once
 * still goes. Something that is not a date is held back by the browser
 * with a message saying how to write one.
 */
const onType = (): void => {
    const text = typed.value.trim();
    const parsed = toIso(text);

    iso.value = parsed ?? '';
    invalid.value = text !== '' && parsed === null;
    textInput.value?.setCustomValidity(invalid.value ? INVALID_MESSAGE : '');
};

/** Once the owner moves on, a date they typed is written out in full. */
const onLeave = (): void => {
    if (iso.value) {
        typed.value = toDayMonthYear(iso.value);
    }
};

const onPick = (event: Event): void => {
    iso.value = (event.target as HTMLInputElement).value;
    typed.value = toDayMonthYear(iso.value);
    invalid.value = false;
    textInput.value?.setCustomValidity('');
};

/**
 * The browser's own calendar, opened from the button. Where a browser
 * cannot be asked to open it, the picker is focused instead.
 */
const openPicker = (): void => {
    try {
        pickerInput.value?.showPicker();
    } catch {
        pickerInput.value?.focus();
    }
};
</script>

<template>
    <div class="relative">
        <input
            :id="props.id"
            ref="textInput"
            v-model="typed"
            type="text"
            inputmode="numeric"
            autocomplete="off"
            placeholder="dd/mm/yyyy"
            :required="props.required"
            :aria-invalid="invalid || undefined"
            :aria-describedby="`${props.id}-format`"
            class="h-9 w-full min-w-0 rounded-md border border-input bg-transparent py-1 pr-11 pl-3 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 md:text-sm dark:bg-input/30 dark:aria-invalid:ring-destructive/40"
            @input="onType"
            @blur="onLeave"
        />
        <span :id="`${props.id}-format`" class="sr-only">
            Day, month, year
        </span>

        <div
            class="absolute inset-y-0 right-0 flex w-10 items-center justify-center"
        >
            <button
                type="button"
                class="flex size-8 cursor-pointer items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                aria-label="Choose a date from a calendar"
                @click="openPicker"
            >
                <CalendarDays class="size-4" aria-hidden="true" />
            </button>
            <input
                ref="pickerInput"
                type="date"
                tabindex="-1"
                aria-hidden="true"
                :value="iso"
                class="pointer-events-none absolute inset-0 size-full opacity-0"
                @change="onPick"
            />
        </div>

        <input type="hidden" :name="props.name" :value="iso" />
    </div>
</template>
