<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Advert from '@/components/GOP/Advert.vue';
import ExcerptText from '@/components/GOP/ExcerptText.vue';
import Subscribe from '@/components/GOP/Subscribe.vue';
import WriterLozenge from '@/components/GOP/WriterLozenge.vue';
import UserAvatar from '@/components/UserAvatar.vue';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import type { Article, LozengeSize, WriterLink } from '@/types/global';

type Card = Pick<
    Article,
    | 'id'
    | 'slug'
    | 'title'
    | 'excerpt'
    | 'cover_photo'
    | 'cover_alt_text'
    | 'published'
    | 'tag'
>;

/** A back issue the writer has an article in, offered as its cover. */
interface IssueCover {
    id: number;
    issue_number: number;
    month_year: string | null;
    cover_photo: string | null;
    /** Whether there is a PDF of it to download yet. */
    downloadable: boolean;
}

interface Props {
    title: string;
    writer: WriterLink & { bio: string | null };
    features: Card[];
    issues: IssueCover[];
    /** The writers either side on the masthead; none when there is only one. */
    previous: WriterLink | null;
    next: WriterLink | null;
}

const props = defineProps<Props>();
const page = usePage();

const articleUrl = (feature: Card): string =>
    `/magazine/features/${feature.id}/${feature.slug}/`;

/*
 * The previous and next writers sit side by side, at the largest size at
 * which both fit on the row. Each is tried in turn and measured: at the
 * two larger sizes a lozenge is as wide as its name and will not shrink,
 * so what it measures is what it needs. The smallest always fits, its
 * name running on to a second line if it must.
 */
const SIZES: LozengeSize[] = ['lg', 'md', 'sm'];
const nav = ref<HTMLElement | null>(null);
const lozengeSize = ref<LozengeSize>('lg');

function fitsSideBySide(row: HTMLElement): boolean {
    const style = getComputedStyle(row);
    const room =
        row.clientWidth -
        parseFloat(style.paddingLeft) -
        parseFloat(style.paddingRight);
    const needed = [
        ...row.querySelectorAll<HTMLElement>('.writer-lozenge'),
    ].reduce((total, lozenge) => total + lozenge.scrollWidth, 0);

    return needed + (parseFloat(style.columnGap) || 0) <= room;
}

async function fitLozenges(): Promise<void> {
    for (const size of SIZES) {
        lozengeSize.value = size;
        await nextTick();

        if (!nav.value || size === 'sm' || fitsSideBySide(nav.value)) {
            return;
        }
    }
}

let observer: ResizeObserver | null = null;
let frame = 0;

onMounted(() => {
    fitLozenges();

    // Webfonts change the width of a name once they arrive.
    document.fonts?.ready.then(fitLozenges);

    observer = new ResizeObserver(() => {
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(() => fitLozenges());
    });

    if (nav.value) {
        observer.observe(nav.value);
    }
});

onBeforeUnmount(() => {
    observer?.disconnect();
    cancelAnimationFrame(frame);
});

// Turning to another writer keeps this page and changes the names.
watch(
    () => [props.previous?.name, props.next?.name],
    () => fitLozenges(),
);
</script>

<template>
    <Head :title="props.writer.name" />
    <DefaultLayout>
        <div class="md:w-full md:pl-48">
            <!-- Back to the masthead, as a listing leads back to the listings. -->
            <div class="border-b border-silver p-2 pl-5 md:border-l">
                <h2 class="text-2xl">
                    <Link href="/our-writers/" class="all-writers"
                        >&#x27C0; back</Link
                    >
                </h2>
            </div>

            <!--
                The writer, worn as a pin badge as their byline wears it at
                the foot of an article, at the size of a page about them.
            -->
            <div
                class="flex flex-col items-start gap-6 border-b border-silver px-5 py-10 md:flex-row md:items-center md:gap-10 md:border-l"
            >
                <UserAvatar
                    :user="props.writer"
                    :alt="props.writer.avatar_alt_text"
                    badge
                    class="size-32 rounded-full border-[3px] border-white md:size-44"
                    fallback-class="text-4xl"
                />
                <div class="min-w-0">
                    <h1
                        class="writer-name text-5xl leading-none font-bold md:text-6xl"
                    >
                        {{ props.writer.name }}
                    </h1>
                    <div
                        v-if="props.writer.bio"
                        class="rich-text mt-4 max-w-2xl text-lg [&>:last-child]:mb-0"
                        v-html="props.writer.bio"
                    ></div>
                </div>
            </div>

            <div class="border-b border-silver px-5 pt-10 pb-10 md:border-l">
                <h2 class="text-3xl font-bold">Articles.</h2>

                <div
                    v-if="props.features.length > 0"
                    class="grid grid-cols-1 gap-x-8 gap-y-12 pt-8 md:grid-cols-2 lg:grid-cols-3"
                >
                    <article
                        v-for="feature in props.features"
                        :key="feature.id"
                        class="writer-article"
                    >
                        <Link :href="articleUrl(feature)">
                            <img
                                :src="
                                    page.props.cdnUrl +
                                    '/' +
                                    feature.cover_photo
                                "
                                :alt="feature.cover_alt_text || feature.title"
                                class="w-full rounded"
                            />
                        </Link>
                        <span
                            v-if="feature.tag"
                            class="mt-4 inline-block rounded-sm bg-tarmac px-4 py-2 text-sm font-bold text-white uppercase md:text-xs"
                            >{{ feature.tag.tag }}</span
                        >
                        <Link :href="articleUrl(feature)">
                            <h3 class="mt-5 text-2xl tracking-tight">
                                {{ feature.title }}
                            </h3>
                        </Link>
                        <ExcerptText
                            :text="feature.excerpt"
                            class="mt-4 text-lg tracking-tight md:text-sm"
                        />
                    </article>
                </div>
                <p v-else class="pt-8 font-din uppercase">
                    Nothing published yet.
                </p>
            </div>

            <!--
                Every back issue with something of theirs in it, as small
                covers that download the issue. The one on sale now is left
                out, as the archive leaves it out.
            -->
            <div
                v-if="props.issues.length > 0"
                class="border-b border-silver px-5 pt-10 pb-10 md:border-l"
            >
                <h2 class="text-3xl font-bold">In print.</h2>
                <ol
                    class="writer-issues grid grid-cols-3 gap-4 pt-8 sm:grid-cols-4 md:grid-cols-6 xl:grid-cols-8"
                >
                    <li v-for="issue in props.issues" :key="issue.id">
                        <component
                            :is="issue.downloadable ? 'a' : 'div'"
                            :href="
                                issue.downloadable
                                    ? `/download/${issue.id}`
                                    : undefined
                            "
                            :title="
                                issue.downloadable
                                    ? `Download Issue ${issue.issue_number}`
                                    : undefined
                            "
                            :rel="
                                issue.downloadable
                                    ? 'noreferrer nofollow'
                                    : undefined
                            "
                            class="group block"
                        >
                            <img
                                v-if="issue.cover_photo"
                                :src="
                                    page.props.cdnUrl + '/' + issue.cover_photo
                                "
                                :alt="`Issue ${issue.issue_number}${issue.month_year ? ', ' + issue.month_year : ''}`"
                                loading="lazy"
                                class="aspect-[256/360] w-full rounded-sm object-cover shadow-sm transition group-hover:-translate-y-0.5 group-hover:shadow-md"
                            />
                            <span
                                class="mt-2 block text-center font-din text-[10px] tracking-[.2em] uppercase"
                                >Issue {{ issue.issue_number }}</span
                            >
                        </component>
                    </li>
                </ol>
            </div>

            <!--
                Each lozenge as wide as what it says, the previous writer
                to the left and the next to the right, drawn smaller until
                the two fit on the row.
            -->
            <nav
                v-if="props.previous && props.next"
                aria-label="More writers"
                ref="nav"
                class="writer-nav flex items-stretch justify-between gap-4 border-b border-silver px-5 pt-10 pb-15 md:gap-6 md:border-l"
            >
                <WriterLozenge
                    :writer="props.previous"
                    direction="previous"
                    :size="lozengeSize"
                />
                <WriterLozenge
                    :writer="props.next"
                    direction="next"
                    :size="lozengeSize"
                />
            </nav>

            <Advert />
            <Subscribe />
        </div>
    </DefaultLayout>
</template>
