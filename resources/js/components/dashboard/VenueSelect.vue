<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import VenueController from '@/actions/App/Http/Controllers/Dashboard/VenueController';
import InputError from '../InputError.vue';
import { Label } from '../ui/label';

export type VenueOption = {
    id: number;
    name: string;
    town?: string | null;
    owned?: boolean;
};

const props = withDefaults(
    defineProps<{
        venues: VenueOption[];
        selectedId?: number | null;
        /** Only a promoter may put a new venue on the books. */
        canAdd?: boolean;
        error?: string;
    }>(),
    {
        selectedId: null,
        canAdd: false,
        error: undefined,
    },
);

/**
 * The list is handed over already sorted - whatever the account runs first,
 * then everywhere else by name - so it is held as-is and only added to.
 */
const options = ref<VenueOption[]>([...props.venues]);
const chosenId = ref<number | null>(props.selectedId);
const query = ref('');
const open = ref(false);
const highlighted = ref(0);
const searchField = ref<HTMLInputElement | null>(null);

const NOWHERE = 'Venue to be confirmed';

const chosen = computed<VenueOption | null>(
    () => options.value.find((venue) => venue.id === chosenId.value) ?? null,
);

const label = computed<string>(() =>
    chosen.value ? describe(chosen.value) : NOWHERE,
);

function describe(venue: VenueOption): string {
    return venue.town ? `${venue.name} — ${venue.town}` : venue.name;
}

const matches = computed<VenueOption[]>(() => {
    const needle = query.value.trim().toLowerCase();

    if (needle === '') {
        return options.value;
    }

    return options.value.filter((venue) =>
        describe(venue).toLowerCase().includes(needle),
    );
});

/** Nothing on the books answers to what they have typed. */
const unmatched = computed<boolean>(
    () => query.value.trim() !== '' && matches.value.length === 0,
);

async function reveal(): Promise<void> {
    open.value = true;
    highlighted.value = 0;
    await nextTick();
    searchField.value?.focus();
}

function close(): void {
    open.value = false;
    query.value = '';
    adding.value = false;
}

function choose(id: number | null): void {
    chosenId.value = id;
    close();
}

function move(step: number): void {
    if (matches.value.length === 0) {
        return;
    }

    highlighted.value =
        (highlighted.value + step + matches.value.length) %
        matches.value.length;
}

function takeHighlighted(): void {
    const venue = matches.value[highlighted.value];

    if (venue) {
        choose(venue.id);
    }
}

/* ---------------------------------------------------------------
   Adding somewhere new
   --------------------------------------------------------------- */

const adding = ref(false);
const saving = ref(false);
const addError = ref<string | null>(null);
const fresh = ref({ name: '', town: '', address: '' });

function beginAdding(): void {
    fresh.value = { name: query.value.trim(), town: '', address: '' };
    addError.value = null;
    adding.value = true;
}

/** Laravel's own cookie, so the post is not turned away as forged. */
function xsrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find((crumb) => crumb.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.split('=')[1] ?? '') : '';
}

async function save(): Promise<void> {
    if (saving.value || fresh.value.name.trim() === '') {
        return;
    }

    saving.value = true;
    addError.value = null;

    try {
        const response = await fetch(VenueController.store.url(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: JSON.stringify(fresh.value),
        });

        if (!response.ok) {
            const body = await response.json().catch(() => null);
            addError.value =
                body?.message ?? 'That venue could not be added just now.';

            return;
        }

        const venue: VenueOption = await response.json();

        // The server hands back whatever is already there under that name in
        // that town, so guard against listing it twice.
        if (!options.value.some((known) => known.id === venue.id)) {
            options.value = [...options.value, venue].sort((a, b) => {
                if (Boolean(a.owned) !== Boolean(b.owned)) {
                    return a.owned ? -1 : 1;
                }

                return a.name.localeCompare(b.name);
            });
        }

        choose(venue.id);
    } catch {
        addError.value = 'That venue could not be added just now.';
    } finally {
        saving.value = false;
    }
}

const fieldClass =
    'border-input dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs outline-none transition-[color,box-shadow] focus-visible:ring-[3px] md:text-sm';
</script>

<template>
    <div class="grid gap-2">
        <Label for="venue-select">Venue</Label>

        <!-- What actually gets posted with the listing. -->
        <input type="hidden" name="venue_id" :value="chosenId ?? ''" />

        <div class="relative">
            <button
                id="venue-select"
                type="button"
                :class="[
                    fieldClass,
                    'flex items-center justify-between text-left',
                ]"
                aria-haspopup="listbox"
                :aria-expanded="open"
                @click="open ? close() : reveal()"
            >
                <span :class="chosen ? '' : 'text-muted-foreground'">
                    {{ label }}
                </span>
                <span aria-hidden="true" class="ml-2 opacity-60">▾</span>
            </button>

            <div
                v-if="open"
                class="absolute z-50 mt-1 w-full rounded-md border border-input bg-background shadow-lg"
            >
                <div class="border-b border-input p-2">
                    <input
                        ref="searchField"
                        v-model="query"
                        type="search"
                        :class="fieldClass"
                        placeholder="Search venues"
                        autocomplete="off"
                        @keydown.down.prevent="move(1)"
                        @keydown.up.prevent="move(-1)"
                        @keydown.enter.prevent="takeHighlighted()"
                        @keydown.esc.prevent="close()"
                    />
                </div>

                <ul
                    v-if="!adding"
                    role="listbox"
                    class="max-h-64 overflow-y-auto py-1"
                >
                    <li v-if="query.trim() === ''">
                        <button
                            type="button"
                            class="w-full px-3 py-2 text-left text-sm text-muted-foreground hover:bg-muted"
                            @click="choose(null)"
                        >
                            {{ NOWHERE }}
                        </button>
                    </li>
                    <li
                        v-for="(venue, index) in matches"
                        :key="venue.id"
                        role="option"
                        :aria-selected="venue.id === chosenId"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-muted"
                            :class="index === highlighted ? 'bg-muted' : ''"
                            @click="choose(venue.id)"
                            @mouseenter="highlighted = index"
                        >
                            <span>{{ describe(venue) }}</span>
                            <span
                                v-if="venue.owned"
                                class="shrink-0 rounded-full border border-input px-2 py-0.5 text-xs text-muted-foreground"
                            >
                                Yours
                            </span>
                        </button>
                    </li>
                    <li
                        v-if="unmatched && !canAdd"
                        class="px-3 py-2 text-sm text-muted-foreground"
                    >
                        No venue matches that.
                    </li>
                </ul>

                <!-- Somewhere new -->
                <div
                    v-if="canAdd && !adding && unmatched"
                    class="border-t border-input p-2"
                >
                    <button
                        type="button"
                        class="w-full rounded px-3 py-2 text-left text-sm font-medium hover:bg-muted"
                        @click="beginAdding()"
                    >
                        Add “{{ query.trim() }}” as a new venue
                    </button>
                </div>

                <div v-if="adding" class="grid gap-2 border-t border-input p-3">
                    <Label for="new-venue-name">Venue name</Label>
                    <input
                        id="new-venue-name"
                        v-model="fresh.name"
                        type="text"
                        :class="fieldClass"
                        @keydown.esc.prevent="adding = false"
                    />

                    <Label for="new-venue-town">Town</Label>
                    <input
                        id="new-venue-town"
                        v-model="fresh.town"
                        type="text"
                        :class="fieldClass"
                        @keydown.esc.prevent="adding = false"
                    />

                    <Label for="new-venue-address">Address</Label>
                    <input
                        id="new-venue-address"
                        v-model="fresh.address"
                        type="text"
                        :class="fieldClass"
                        @keydown.esc.prevent="adding = false"
                    />

                    <p class="text-xs text-muted-foreground">
                        The office will fill in the rest.
                    </p>

                    <p v-if="addError" class="text-sm text-red-600">
                        {{ addError }}
                    </p>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="rounded bg-primary px-3 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50"
                            :disabled="saving || fresh.name.trim() === ''"
                            @click="save()"
                        >
                            {{ saving ? 'Adding…' : 'Add venue' }}
                        </button>
                        <button
                            type="button"
                            class="px-3 py-2 text-sm text-muted-foreground hover:underline"
                            @click="adding = false"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <InputError :message="error" />
    </div>
</template>
