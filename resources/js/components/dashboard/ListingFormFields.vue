<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';
import InputError from '../InputError.vue';
import { Input } from '../ui/input';
import { Label } from '../ui/label';
import DateField from './DateField.vue';
import VenueSelect from './VenueSelect.vue';

type Option = { id: number; name: string; town?: string | null };

type GalleryImage = {
    key: string;
    attributes: { image: string; caption: string | null };
};

/** One artist on the bill, as the form edits it. */
type LineUpArtist = {
    name: string;
    playlist: string | null;
    video: string | null;
};

type Listing = {
    title?: string;
    promoter_id?: number | null;
    body?: string;
    meta_description?: string | null;
    event_link?: string | null;
    ticket_price?: string | null;
    sold_out?: boolean;
    button_text?: string | null;
    venue_id?: number | null;
    category_id?: number | null;
    start_date?: string | null;
    end_date?: string | null;
    event_time_description?: string | null;
    additional_date_information?: string | null;
    thumbnail?: string | null;
};

const props = withDefaults(
    defineProps<{
        categories: Option[];
        venues: Option[];
        /** Only the promoters this account manages; empty for most people. */
        promoters?: Option[];
        /** Whether this account also speaks for a venue. */
        managesVenue?: boolean;
        /** Whether this account may put a new venue on the books. */
        canAddVenue?: boolean;
        errors: Record<string, string>;
        listing?: Listing | null;
        gallery?: GalleryImage[];
        /** The artists on the bill, each with a playlist, a video or both. */
        lineUp?: LineUpArtist[];
    }>(),
    {
        promoters: () => [],
        managesVenue: false,
        canAddVenue: false,
        listing: null,
        gallery: () => [],
        lineUp: () => [],
    },
);

/**
 * Somebody who manages exactly one promoter is putting the event on under
 * it, so it is picked for them and there is nothing else to choose.
 *
 * Unless they also run a venue: their building hosts other people's events
 * as well as their own, so they need to be able to say "nobody" - and a new
 * listing starts there rather than quietly going up under their own name.
 */
const onlyPromoter = computed<boolean>(
    () => props.promoters.length === 1 && !props.managesVenue,
);

/**
 * Whether the media panel starts open. A listing that already has some
 * starts open, or editing one would hide work already done.
 */
const mediaOpen = ref<boolean>(
    Boolean(props.gallery.length || props.lineUp.length),
);

/** The most artists one listing carries, as the server allows. */
const MAX_ARTISTS = 6;

let artistUid = 0;

/**
 * The line-up as it is being edited. Each artist carries a key of its own
 * so moving one keeps its boxes with it; the server is sent the rest, in
 * this order, as line_up[0][name] and so on.
 */
const artists = ref<(LineUpArtist & { uid: number })[]>(
    props.lineUp.map((artist) => ({ ...artist, uid: artistUid++ })),
);

const addArtist = (): void => {
    if (artists.value.length < MAX_ARTISTS) {
        artists.value.push({
            name: '',
            playlist: '',
            video: '',
            uid: artistUid++,
        });
    }
};

const removeArtist = (index: number): void => {
    artists.value.splice(index, 1);
};

const moveArtist = (index: number, by: -1 | 1): void => {
    const to = index + by;

    if (to < 0 || to >= artists.value.length) {
        return;
    }

    const [artist] = artists.value.splice(index, 1);
    artists.value.splice(to, 0, artist);
};

/** Trim an ISO date string down to the YYYY-MM-DD a date input expects. */
const toDateInput = (value?: string | null): string =>
    value ? value.slice(0, 10) : '';

const fieldClass =
    'border-input dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs outline-none transition-[color,box-shadow] focus-visible:ring-[3px] md:text-sm';

const listing = props.listing;

const page = usePage();

/** The thumbnail already on the listing, resolved against the CDN. */
const existingThumbnail = computed<string | null>(() =>
    listing?.thumbnail
        ? `${String(page.props.cdnUrl).replace(/\/$/, '')}/${listing.thumbnail}`
        : null,
);

const preview = ref<string | null>(null);
const removeThumbnail = ref(false);

const releasePreview = (): void => {
    if (preview.value) {
        URL.revokeObjectURL(preview.value);
        preview.value = null;
    }
};

const onThumbnailChange = (event: Event): void => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;

    releasePreview();

    if (file) {
        preview.value = URL.createObjectURL(file);
        removeThumbnail.value = false;
    }
};

onBeforeUnmount(releasePreview);

/** Resolve a stored path against the CDN. */
const cdn = (path: string): string =>
    `${String(page.props.cdnUrl).replace(/\/$/, '')}/${path}`;

const removedImages = ref<Record<string, boolean>>({});

/** Matches the `gallery` array limit enforced in ListingRequest. */
const MAX_NEW_IMAGES = 12;

type PendingImage = { id: number; file: File; url: string };

const galleryInput = ref<HTMLInputElement | null>(null);
const pending = ref<PendingImage[]>([]);
const dragging = ref(false);
const tooMany = ref(false);
let nextImageId = 0;

const signatureOf = (file: File): string =>
    `${file.name}:${file.size}:${file.lastModified}`;

/**
 * Push the accumulated selection back onto the native input, since that is
 * what the form actually submits. A file input replaces its selection on
 * every pick, so without this a promoter choosing a few images and then
 * coming back for a few more would silently lose the first batch.
 */
const syncGalleryInput = (): void => {
    const transfer = new DataTransfer();
    pending.value.forEach((image) => transfer.items.add(image.file));

    if (galleryInput.value) {
        galleryInput.value.files = transfer.files;
    }
};

const addFiles = (files: FileList | File[] | null): void => {
    if (!files) return;

    const known = new Set(
        pending.value.map((image) => signatureOf(image.file)),
    );
    tooMany.value = false;

    for (const file of Array.from(files)) {
        if (!file.type.startsWith('image/') || known.has(signatureOf(file))) {
            continue;
        }

        if (pending.value.length >= MAX_NEW_IMAGES) {
            tooMany.value = true;
            break;
        }

        known.add(signatureOf(file));
        pending.value.push({
            id: nextImageId++,
            file,
            url: URL.createObjectURL(file),
        });
    }

    syncGalleryInput();
};

const removePending = (id: number): void => {
    const image = pending.value.find((candidate) => candidate.id === id);

    if (image) {
        URL.revokeObjectURL(image.url);
    }

    pending.value = pending.value.filter((candidate) => candidate.id !== id);
    tooMany.value = false;
    syncGalleryInput();
};

const onGalleryChange = (event: Event): void => {
    addFiles((event.target as HTMLInputElement).files);
};

const onDrop = (event: DragEvent): void => {
    dragging.value = false;
    addFiles(event.dataTransfer?.files ?? null);
};

onBeforeUnmount(() =>
    pending.value.forEach((image) => URL.revokeObjectURL(image.url)),
);

/** A file input styled as a button, driven by a label rather than the input. */
const pickerButtonClass =
    'inline-flex cursor-pointer items-center gap-2 rounded-md border border-input bg-transparent px-3 py-2 text-sm font-medium shadow-xs transition-colors hover:bg-accent hover:text-accent-foreground focus-within:ring-[3px] focus-within:ring-ring/50';
</script>

<template>
    <div class="grid gap-6">
        <div class="grid gap-2">
            <Label for="title">Title</Label>
            <Input
                id="title"
                name="title"
                :default-value="listing?.title"
                required
                placeholder="e.g. The Lovely Eggs at Rough Trade"
            />
            <InputError :message="errors.title" />
        </div>

        <div class="grid items-start gap-6 md:grid-cols-2">
            <div class="grid gap-2">
                <Label for="category_id">Category</Label>
                <select
                    id="category_id"
                    name="category_id"
                    :class="fieldClass"
                    required
                >
                    <option value="">Choose a category</option>
                    <option
                        v-for="category in categories"
                        :key="category.id"
                        :value="category.id"
                        :selected="listing?.category_id === category.id"
                    >
                        {{ category.name }}
                    </option>
                </select>
                <InputError :message="errors.category_id" />
            </div>

            <VenueSelect
                :venues="venues"
                :selected-id="listing?.venue_id ?? null"
                :can-add="canAddVenue"
                :error="errors.venue_id"
            />

            <div v-if="promoters.length" class="grid gap-2">
                <Label for="promoter_id">Promoter</Label>
                <select id="promoter_id" name="promoter_id" :class="fieldClass">
                    <option v-if="!onlyPromoter" value="">No promoter</option>
                    <option
                        v-for="promoter in promoters"
                        :key="promoter.id"
                        :value="promoter.id"
                        :selected="
                            onlyPromoter || listing?.promoter_id === promoter.id
                        "
                    >
                        {{ promoter.name }}
                    </option>
                </select>
                <InputError :message="errors.promoter_id" />
            </div>
        </div>

        <div class="grid items-start gap-6 md:grid-cols-2">
            <div class="grid gap-2">
                <Label for="start_date">Start date</Label>
                <DateField
                    id="start_date"
                    name="start_date"
                    :default-value="toDateInput(listing?.start_date)"
                    required
                />
                <InputError :message="errors.start_date" />
            </div>

            <div class="grid gap-2">
                <Label for="end_date">End date</Label>
                <DateField
                    id="end_date"
                    name="end_date"
                    :default-value="toDateInput(listing?.end_date)"
                />
                <InputError :message="errors.end_date" />
            </div>
        </div>

        <div class="grid items-start gap-6 md:grid-cols-2">
            <div class="grid gap-2">
                <Label for="additional_date_information"
                    >Additional date information</Label
                >
                <Input
                    id="additional_date_information"
                    name="additional_date_information"
                    :default-value="listing?.additional_date_information ?? ''"
                    placeholder="e.g. Tuesday and Thursday mornings only"
                />
                <InputError :message="errors.additional_date_information" />
            </div>

            <div class="grid gap-2">
                <Label for="event_time_description">Time description</Label>
                <Input
                    id="event_time_description"
                    name="event_time_description"
                    :default-value="listing?.event_time_description ?? ''"
                    placeholder="e.g. Doors 7pm, on stage 8pm"
                />
                <InputError :message="errors.event_time_description" />
            </div>
        </div>

        <!--
            The text areas take :defaultValue, never :value. A bound value is
            written back to the DOM on every render of this component, so
            choosing a thumbnail, adding a gallery image or opening the media
            panel would put back what the server sent and wipe what was typed.
            This is only where they start; the browser keeps what follows.
        -->
        <div class="grid gap-2">
            <Label for="body">Description</Label>
            <textarea
                id="body"
                name="body"
                rows="8"
                :class="fieldClass"
                required
                placeholder="Tell people what the event is about."
                :defaultValue="listing?.body ?? ''"
            ></textarea>
            <InputError :message="errors.body" />
        </div>

        <div class="grid gap-2">
            <Label for="thumbnail">Thumbnail image</Label>

            <div class="flex items-start gap-4">
                <img
                    v-if="preview || (existingThumbnail && !removeThumbnail)"
                    :src="preview ?? existingThumbnail ?? ''"
                    alt=""
                    class="h-24 w-28 shrink-0 rounded-md border border-sidebar-border/70 object-cover dark:border-sidebar-border"
                />
                <div
                    v-else
                    class="flex h-24 w-28 shrink-0 items-center justify-center rounded-md border border-dashed border-sidebar-border/70 text-xs text-muted-foreground dark:border-sidebar-border"
                >
                    No image
                </div>

                <div class="grid flex-1 gap-2">
                    <div>
                        <label for="thumbnail" :class="pickerButtonClass">
                            Choose image
                        </label>
                        <input
                            id="thumbnail"
                            name="thumbnail"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="sr-only"
                            @change="onThumbnailChange"
                        />
                    </div>
                    <p class="text-xs text-muted-foreground">
                        JPEG, PNG or WebP, up to 8MB. Whatever shape it is, the
                        whole image is kept &mdash; it is fitted to
                        548&times;462 and any gaps are padded with a colour
                        taken from the edges of your artwork.
                    </p>

                    <label
                        v-if="existingThumbnail"
                        class="flex items-center gap-2 text-sm"
                    >
                        <input
                            type="checkbox"
                            name="remove_thumbnail"
                            value="1"
                            v-model="removeThumbnail"
                            class="size-4 rounded border-input"
                        />
                        Remove the current image
                    </label>
                </div>
            </div>

            <InputError :message="errors.thumbnail" />
        </div>

        <div class="grid items-start gap-6 md:grid-cols-2">
            <div class="grid gap-2">
                <Label for="ticket_price">Ticket price</Label>
                <Input
                    id="ticket_price"
                    name="ticket_price"
                    :default-value="listing?.ticket_price ?? ''"
                    placeholder="e.g. £12, Pay What You Feel, Free"
                />
                <InputError :message="errors.ticket_price" />

                <!--
                    The hidden field is what an unticked box submits: a
                    checkbox on its own sends nothing at all, which would
                    leave a sold-out event sold out for ever.
                -->
                <label class="mt-1 flex items-center gap-2 text-sm">
                    <input type="hidden" name="sold_out" value="0" />
                    <input
                        type="checkbox"
                        name="sold_out"
                        value="1"
                        :checked="listing?.sold_out ?? false"
                        class="size-4 rounded border-input"
                    />
                    SOLD OUT
                </label>
                <InputError :message="errors.sold_out" />
            </div>

            <div class="grid gap-2">
                <Label for="button_text">Button text</Label>
                <Input
                    id="button_text"
                    name="button_text"
                    :default-value="listing?.button_text ?? 'Find out more'"
                    required
                />
                <InputError :message="errors.button_text" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="event_link">Event link</Label>
            <Input
                id="event_link"
                name="event_link"
                type="url"
                :default-value="listing?.event_link ?? ''"
                placeholder="https://"
            />
            <InputError :message="errors.event_link" />
        </div>

        <!--
            Most listings are a picture and some words. The rest of the
            media is shut away so it is not clutter for the promoters who
            will never use it.
        -->
        <div class="rounded-lg border border-input">
            <button
                type="button"
                class="flex w-full items-center justify-between gap-2 px-4 py-3 text-left"
                :aria-expanded="mediaOpen"
                aria-controls="additional-media"
                @click="mediaOpen = !mediaOpen"
            >
                <span class="grid gap-0.5">
                    <span class="text-sm font-medium">Additional media</span>
                    <span class="text-xs text-muted-foreground">
                        Gallery images, a video and a playlist
                    </span>
                </span>
                <span aria-hidden="true" class="opacity-60">
                    {{ mediaOpen ? '−' : '+' }}
                </span>
            </button>

            <!--
                v-show, not v-if: the gallery's file input and its
                gallery_existing hidden fields have to stay in the form even
                while the panel is shut. Unmounting them would post no
                gallery_existing at all, which reconcileGallery reads as
                "remove every image".
            -->
            <div
                v-show="mediaOpen"
                id="additional-media"
                class="grid gap-6 border-t border-input p-4"
            >
                <div class="grid gap-3">
                    <Label for="gallery">Gallery images</Label>

                    <div
                        v-if="gallery.length"
                        class="grid grid-cols-2 gap-3 sm:grid-cols-3"
                    >
                        <div
                            v-for="(image, index) in gallery"
                            :key="image.key"
                            class="space-y-2 rounded-md border border-sidebar-border/70 p-2 dark:border-sidebar-border"
                            :class="
                                removedImages[image.key] ? 'opacity-40' : ''
                            "
                        >
                            <img
                                :src="cdn(image.attributes.image)"
                                :alt="image.attributes.caption ?? ''"
                                class="aspect-square w-full rounded object-cover"
                            />

                            <input
                                type="hidden"
                                :name="`gallery_existing[${index}][key]`"
                                :value="image.key"
                            />
                            <Input
                                :name="`gallery_existing[${index}][caption]`"
                                :default-value="image.attributes.caption ?? ''"
                                placeholder="Caption"
                                class="h-8 text-xs"
                            />
                            <label class="flex items-center gap-2 text-xs">
                                <input
                                    type="checkbox"
                                    :name="`gallery_existing[${index}][remove]`"
                                    value="1"
                                    v-model="removedImages[image.key]"
                                    class="size-3.5 rounded border-input"
                                />
                                Remove
                            </label>
                        </div>
                    </div>

                    <div
                        class="rounded-md border border-dashed px-4 py-6 text-center transition-colors"
                        :class="
                            dragging
                                ? 'border-ring bg-accent/50'
                                : 'border-sidebar-border/70 dark:border-sidebar-border'
                        "
                        @dragover.prevent="dragging = true"
                        @dragenter.prevent="dragging = true"
                        @dragleave.prevent="dragging = false"
                        @drop.prevent="onDrop"
                    >
                        <label :for="'gallery'" :class="pickerButtonClass">
                            <svg
                                class="size-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path d="M12 5v14M5 12h14" />
                            </svg>
                            Add images
                        </label>

                        <input
                            id="gallery"
                            ref="galleryInput"
                            name="gallery[]"
                            type="file"
                            multiple
                            accept="image/jpeg,image/png,image/webp"
                            class="sr-only"
                            @change="onGalleryChange"
                        />

                        <p class="mt-2 text-xs text-muted-foreground">
                            Drag images here, or choose several at once. Up to
                            {{ MAX_NEW_IMAGES }}, 8MB each.
                        </p>
                        <p
                            v-if="pending.length"
                            class="mt-1 text-xs font-medium text-muted-foreground"
                        >
                            {{ pending.length }} of {{ MAX_NEW_IMAGES }} ready
                            to upload &mdash; pick more to add to these.
                        </p>
                        <p
                            v-if="tooMany"
                            class="mt-1 text-xs text-red-600 dark:text-red-500"
                        >
                            That is as many as you can add in one go. Save
                            these, then add the rest.
                        </p>
                    </div>

                    <InputError :message="errors.gallery" />

                    <div
                        v-if="pending.length"
                        class="grid grid-cols-2 gap-3 sm:grid-cols-3"
                    >
                        <div
                            v-for="(image, index) in pending"
                            :key="image.id"
                            class="space-y-2 rounded-md border border-dashed border-sidebar-border/70 p-2 dark:border-sidebar-border"
                        >
                            <div class="relative">
                                <img
                                    :src="image.url"
                                    alt=""
                                    class="aspect-square w-full rounded object-cover"
                                />
                                <button
                                    type="button"
                                    class="absolute top-1 right-1 flex size-6 items-center justify-center rounded-full bg-background/90 text-sm leading-none shadow-xs hover:bg-destructive hover:text-white"
                                    :aria-label="`Remove ${image.file.name}`"
                                    @click="removePending(image.id)"
                                >
                                    &times;
                                </button>
                            </div>
                            <Input
                                :name="`gallery_captions[${index}]`"
                                placeholder="Caption"
                                class="h-8 text-xs"
                            />
                            <p
                                class="truncate text-[11px] text-muted-foreground"
                            >
                                {{ image.file.name }}
                            </p>
                        </div>
                    </div>
                </div>

                <fieldset class="grid gap-4">
                    <legend class="text-sm font-medium">Line-up</legend>
                    <p class="text-xs text-muted-foreground">
                        Each artist on the bill, with a playlist, a video or
                        both, in the order they should appear. Up to
                        {{ MAX_ARTISTS }} artists.
                    </p>

                    <div
                        v-for="(artist, index) in artists"
                        :key="artist.uid"
                        class="line-up-artist grid gap-3 rounded-md border p-4"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-medium">
                                Artist {{ index + 1 }}
                            </p>
                            <div class="flex gap-3 text-xs">
                                <button
                                    type="button"
                                    class="underline underline-offset-4 disabled:no-underline disabled:opacity-40"
                                    :disabled="index === 0"
                                    @click="moveArtist(index, -1)"
                                >
                                    Move up
                                </button>
                                <button
                                    type="button"
                                    class="underline underline-offset-4 disabled:no-underline disabled:opacity-40"
                                    :disabled="index === artists.length - 1"
                                    @click="moveArtist(index, 1)"
                                >
                                    Move down
                                </button>
                                <button
                                    type="button"
                                    class="text-destructive underline underline-offset-4"
                                    @click="removeArtist(index)"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label :for="`line_up_${artist.uid}_name`"
                                >Artist name</Label
                            >
                            <Input
                                :id="`line_up_${artist.uid}_name`"
                                v-model="artist.name"
                                :name="`line_up[${index}][name]`"
                                maxlength="120"
                                required
                            />
                            <InputError
                                :message="errors[`line_up.${index}.name`]"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label :for="`line_up_${artist.uid}_playlist`"
                                >Playlist embed</Label
                            >
                            <textarea
                                :id="`line_up_${artist.uid}_playlist`"
                                v-model="artist.playlist"
                                :name="`line_up[${index}][playlist]`"
                                rows="3"
                                :class="`${fieldClass} font-mono text-xs`"
                                placeholder="Paste the embed code from Bandcamp, Spotify, Tidal or Qobuz"
                            ></textarea>
                            <InputError
                                :message="errors[`line_up.${index}.playlist`]"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label :for="`line_up_${artist.uid}_video`"
                                >Video embed</Label
                            >
                            <textarea
                                :id="`line_up_${artist.uid}_video`"
                                v-model="artist.video"
                                :name="`line_up[${index}][video]`"
                                rows="3"
                                :class="`${fieldClass} font-mono text-xs`"
                                placeholder="Paste a YouTube or Vimeo embed code, or just the link to the video"
                            ></textarea>
                            <InputError
                                :message="errors[`line_up.${index}.video`]"
                            />
                        </div>
                    </div>

                    <InputError :message="errors.line_up" />

                    <div v-if="artists.length < MAX_ARTISTS">
                        <button
                            type="button"
                            class="rounded-md border px-4 py-2 text-sm font-medium"
                            @click="addArtist"
                        >
                            Add artist
                        </button>
                    </div>

                    <p class="text-xs text-muted-foreground">
                        On Bandcamp: open the release, choose
                        <strong>Share/Embed</strong> &rarr;
                        <strong>Embed this album</strong>, then under
                        <strong>Layout</strong> pick
                        <strong>Standard</strong> with artwork set to
                        <strong>Big</strong> and
                        <strong>Show tracklist</strong> ticked. Copy the whole
                        <code>&lt;iframe&gt;</code>. Ignore the width &mdash; we
                        widen it to fit the page and keep the height Bandcamp
                        gives you. For a video, a plain link to the YouTube or
                        Vimeo page works too.
                    </p>
                </fieldset>
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="meta_description">Meta description</Label>
            <Input
                id="meta_description"
                name="meta_description"
                maxlength="160"
                :default-value="listing?.meta_description ?? ''"
                placeholder="A short summary for search engines (160 characters max)"
            />
            <InputError :message="errors.meta_description" />
        </div>
    </div>
</template>
