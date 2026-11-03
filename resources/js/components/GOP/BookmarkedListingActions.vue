<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Link2, Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CalendarController from '@/actions/App/Http/Controllers/CalendarController';
import {
    destroy as stopAttending,
    store as startAttending,
} from '@/actions/App/Http/Controllers/ListingAttendingController';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useBookmarks } from '@/composables/useBookmarks';
import { jsonRequestHeaders } from '@/lib/jsonRequest';
import { formatListingDates } from '@/lib/listingDates';
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

const shareUrl = computed(
    () =>
        `${window.location.origin}/listings/${props.listing.id}/${props.listing.slug}`,
);

const whatsappUrl = computed(() => {
    const { title, venue } = props.listing;
    const where = venue.id ? ` at ${venue.name}` : '';
    const message = `${title}${where}, ${formatListingDates(props.listing)} ${shareUrl.value}`;

    return `https://wa.me/?text=${encodeURIComponent(message)}`;
});

const copied = ref(false);
const copyFailed = ref(false);

/** Where the clipboard refuses, the address is shown to copy by hand. */
async function copyLink(): Promise<void> {
    try {
        await navigator.clipboard.writeText(shareUrl.value);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch (error) {
        copyFailed.value = true;
        console.error(error);
    }
}
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

        <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
            <button
                type="button"
                class="inline-flex cursor-pointer items-center gap-2 underline underline-offset-4"
                @click="copyLink"
            >
                <Link2 class="size-4 shrink-0" aria-hidden="true" />
                {{ copied ? 'Copied' : 'Copy link' }}
            </button>

            <a
                :href="whatsappUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-2 underline underline-offset-4"
            >
                <svg
                    class="size-4 shrink-0"
                    viewBox="0 0 24 24"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="currentColor"
                    aria-hidden="true"
                >
                    <path
                        d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"
                    />
                </svg>
                Share to WhatsApp
            </a>

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
        </div>

        <p v-if="copyFailed" class="text-sm break-all select-all">
            {{ shareUrl }}
        </p>
    </div>
</template>
