<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CalendarController from '@/actions/App/Http/Controllers/CalendarController';
import {
    destroy as stopAttending,
    store as startAttending,
} from '@/actions/App/Http/Controllers/ListingAttendingController';
import ListingShareLinks from '@/components/GOP/ListingShareLinks.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useBookmarks } from '@/composables/useBookmarks';
import { jsonRequestHeaders } from '@/lib/jsonRequest';
import type { BookmarkedListing } from '@/types/global';

/**
 * What can be done with a bookmarked listing, under its row on the
 * Dashboard: say tickets are bought, get to them if they are not, and pass
 * the event on or put it in a calendar.
 *
 * Outside the row rather than in it, as the row's link is stretched over
 * the whole row: a slightly missed tap here should not leave the page.
 */
const props = defineProps<{ listing: BookmarkedListing }>();

const { isBookmarked, isBusy } = useBookmarks();

const bookmarked = computed(() => isBookmarked(props.listing.id));
const attending = computed(() => Boolean(props.listing.pivot.attending));
const attendingId = computed(() => `attending-${props.listing.id}`);

/** A request in flight, so a double tap cannot race itself. */
const saving = ref(false);

/**
 * The tick is kept in the page's own props rather than beside them: going
 * back to the page restores its props, and a copy kept here would come back
 * as it was when the page first loaded.
 */
function showAttending(value: boolean): void {
    router.replaceProp('bookmarkedListings', (current: unknown) =>
        (current as BookmarkedListing[]).map((listing) =>
            listing.id === props.listing.id
                ? { ...listing, pivot: { ...listing.pivot, attending: value } }
                : listing,
        ),
    );
}

/**
 * The tick lands on screen first and is undone if the request fails, as
 * with the heart.
 */
async function setAttending(wanted: boolean): Promise<void> {
    if (saving.value) {
        return;
    }

    saving.value = true;
    showAttending(wanted);

    const { url, method } = wanted
        ? startAttending(props.listing.id)
        : stopAttending(props.listing.id);

    try {
        const response = await fetch(url, {
            method: method.toUpperCase(),
            headers: jsonRequestHeaders(),
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(`Attending request failed: ${response.status}`);
        }
    } catch (error) {
        showAttending(!wanted);
        console.error(error);
    } finally {
        saving.value = false;
    }
}

/*
 * The tick goes with the bookmark, so one saved again starts unticked. Not
 * until the request has answered: one that fails leaves the bookmark, and
 * the tick, as they were.
 */
watch(
    () => bookmarked.value || isBusy(props.listing.id),
    (kept) => {
        if (!kept) {
            showAttending(false);
        }
    },
);
</script>

<template>
    <div
        class="mx-5 flex flex-col gap-4 border-t border-dashed border-sidebar-border/70 py-4 dark:border-sidebar-border"
    >
        <div
            v-if="bookmarked"
            class="flex flex-wrap items-center justify-between gap-x-6 gap-y-3"
        >
            <Label
                :for="attendingId"
                class="flex min-h-6 cursor-pointer items-center gap-3 text-base font-normal"
            >
                <Checkbox
                    :id="attendingId"
                    class="size-5"
                    :model-value="attending"
                    @update:model-value="setAttending($event === true)"
                />
                <span>{{
                    listing.is_free ? 'Going' : 'Tickets purchased'
                }}</span>
            </Label>

            <a
                v-if="
                    !attending &&
                    listing.ticket_link &&
                    listing.button_text?.trim()
                "
                :href="listing.ticket_link"
                target="_blank"
                rel="noopener noreferrer nofollow"
                class="w-fit rounded-full bg-hivis px-4 py-2 font-din text-sm font-bold text-slate uppercase hover:text-tarmac md:text-xs dark:text-tarmac hover:dark:bg-black hover:dark:text-hivis"
            >
                {{ listing.button_text }}
            </a>
        </div>

        <ListingShareLinks :listing="listing">
            <!-- A plain link: this is a file for the phone, not a page. -->
            <a
                :href="CalendarController.url(listing.id)"
                class="inline-flex items-center gap-2 underline underline-offset-4"
            >
                <span
                    class="flex size-6 shrink-0 items-center justify-center rounded-full bg-hivis text-tarmac"
                >
                    <Plus class="size-4" aria-hidden="true" />
                </span>
                Add to calendar
            </a>
        </ListingShareLinks>
    </div>
</template>
