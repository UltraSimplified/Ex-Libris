<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Advert from '@/components/GOP/Advert.vue';
import BylineName from '@/components/GOP/BylineName.vue';
import ListingRow from '@/components/GOP/ListingRow.vue';
import UserAvatar from '@/components/UserAvatar.vue';
import { useFeatureReading } from '@/composables/useFeatureReading';
import { resolvePlaceholders } from '@/composables/usePlaceholders.js';
import type { Article, Colourway } from '@/types/global';
import Colophon from '../../components/GOP/layouts/Colophon.vue';
import Copy from '../../components/GOP/layouts/Copy.vue';
import Divider from '../../components/GOP/layouts/Divider.vue';
import Gallery from '../../components/GOP/layouts/Gallery.vue';
import ImgFull from '../../components/GOP/layouts/ImgFull.vue';
import ImgLeft from '../../components/GOP/layouts/ImgLeft.vue';
import ImgRight from '../../components/GOP/layouts/ImgRight.vue';
import Playlist from '../../components/GOP/layouts/Playlist.vue';
import PullQuote from '../../components/GOP/layouts/PullQuote.vue';
import TwoColumns from '../../components/GOP/layouts/TwoColumns.vue';
import Video from '../../components/GOP/layouts/Video.vue';
import Subscribe from '../../components/GOP/Subscribe.vue';
import DefaultLayout from '../../layouts/DefaultLayout.vue';

interface Props {
    title: string;
    feature: Article;
    /**
     * Framed in the Office behind the eye on the features list, where the
     * point is to read this one feature. The site's own header, footer,
     * advert and mailing list are left off, as the listing preview does:
     * with them the frame is a working copy of the whole site.
     */
    preview?: boolean;
}

const props = withDefaults(defineProps<Props>(), { preview: false });
const page = usePage();

/**
 * The event the feature is about, with its image resolved as the listings
 * pages resolve it: a placeholder where it has none of its own.
 */
const listing = computed(() =>
    props.feature.listing
        ? resolvePlaceholders([props.feature.listing], page.props.cdnUrl)[0]
        : null,
);

/**
 * How long the feature is read, for the Office. Not in the preview, which is
 * the Office reading its own work.
 */
const readingEnd = ref<HTMLElement | null>(null);

useFeatureReading(
    () => (props.preview ? null : props.feature.id),
    readingEnd,
);
</script>
<template>
    <Head>
        <title>{{ page.props.title }}</title>
    </Head>
    <component
        :is="props.preview ? 'div' : DefaultLayout"
        v-bind="props.preview ? {} : { featureLayout: true }"
    >
        <div
            class="left container px-5 md:h-[397] md:w-[690] md:w-full md:pl-48"
        >
            <div
                class="pb-6 pl-0 md:-ml-10 md:border-l md:border-silver md:px-5"
            >
                <div class="pt-5">
                    <img
                        v-if="props.feature.cover_photo"
                        :src="
                            page.props.cdnUrl + '/' + props.feature.cover_photo
                        "
                        class="w-full max-w-[640px] rounded-md"
                    />
                </div>
            </div>
        </div>
        <div
            :class="
                `left -mt-40 pr-5 pl-10 md:w-full md:pl-48 ` +
                props.feature.colourway
            "
        >
            <div
                class="-ml-10 pt-40 pl-5 md:border-l md:border-silver md:pt-30 lg:pt-50"
            >
                <div
                    class="w-full md:w-[726px]"
                    :class="{ 'is-draft': props.feature.status === 'draft' }"
                >
                    <span
                        v-if="props.feature.tag"
                        class="pl-2 font-din text-[10px] tracking-[.25em] uppercase"
                        >{{ props.feature.tag.tag }}</span
                    >
                    <h1
                        class="pt-5 pb-5 text-[60px] leading-[60px] font-normal"
                    >
                        {{ props.feature.title }}
                    </h1>
                    <span class="font-din uppercase"
                        >by <BylineName :writer="props.feature.user"
                    /></span>
                    <div class="feature-body pt-5">
                        <template
                            v-for="layout in props.feature.body"
                            :key="layout.key"
                        >
                            <div v-if="layout.layout === 'copy'">
                                <Copy :layout="layout" />
                            </div>

                            <div v-else-if="layout.layout === 'pullquote'">
                                <PullQuote :layout="layout" />
                            </div>

                            <div v-else-if="layout.layout === 'twocolumns'">
                                <TwoColumns :layout="layout" />
                            </div>

                            <div v-else-if="layout.layout === 'imgleft'">
                                <ImgLeft :layout="layout" />
                            </div>

                            <div v-else-if="layout.layout === 'imgright'">
                                <ImgRight :layout="layout" />
                            </div>

                            <div v-else-if="layout.layout === 'imgfull'">
                                <ImgFull :layout="layout" />
                            </div>

                            <div v-else-if="layout.layout === 'divider'">
                                <Divider
                                    layout="divider"
                                    :colourway="
                                        props.feature.colourway as Colourway
                                    "
                                />
                            </div>

                            <div v-else-if="layout.layout === 'colophon'">
                                <Colophon :layout="layout" />
                            </div>

                            <div v-else-if="layout.layout === 'playlist'">
                                <Playlist :layout="layout" />
                            </div>

                            <div v-else-if="layout.layout === 'video'">
                                <Video :layout="layout" />
                            </div>

                            <div v-else-if="layout.layout === 'gallery'">
                                <Gallery :layout="layout" />
                            </div>

                            <div v-else>
                                <!-- fallback if no layout type matches -->
                                <h2 class="text-3xl">OTHER</h2>
                                {{ layout }}
                            </div>
                        </template>
                        <!--
                            The event the feature is about, closing the body
                            ahead of the byline. Pulled out by the row's own
                            padding, so its picture lines up with the text.
                        -->
                        <div v-if="listing" class="feature-listing -mx-5">
                            <ListingRow :listing="listing" />
                        </div>
                        <!--
                            The end of the article, for counting who reached
                            it. After the listing rather than before: the
                            body's spacing rules look for the listing
                            directly after the last block of text.
                        -->
                        <span
                            ref="readingEnd"
                            class="block"
                            aria-hidden="true"
                        ></span>
                        <div>
                            <Divider
                                layout="divider"
                                :colourway="
                                    props.feature.colourway as Colourway
                                "
                            />
                        </div>
                        <div class="pt-0 pb-10">
                            <div class="flex items-start gap-4 pt-5 pb-10">
                                <!--
                                    The writer, worn as a pin badge - the
                                    same treatment their own dashboard
                                    gives them, at a shadow scaled for
                                    something this size. The white rim the
                                    page already used is kept: hi-vis
                                    belongs to the dashboard.

                                    A border rather than an outline,
                                    because an outline is drawn outside
                                    the box and would paint over the tin
                                    rim and the lift the badge sits on.
                                -->
                                <div
                                    v-if="props.feature.user.avatar"
                                    class="shrink-0"
                                >
                                    <UserAvatar
                                        :user="props.feature.user"
                                        :alt="
                                            props.feature.user.avatar_alt_text
                                        "
                                        badge
                                        :lift="0.4"
                                        class="h-20 w-20 rounded-full border-[3px] border-white"
                                    />
                                </div>
                                <div>
                                    <h2 class="text-lg font-bold">
                                        <BylineName
                                            :writer="props.feature.user"
                                        />
                                    </h2>
                                    <div
                                        class="rich-text [&>:last-child]:mb-0"
                                        v-html="props.feature.user.bio"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <template v-if="!props.preview">
            <div class="m-auto -mt-5 pt-5 md:w-full md:pl-38">
                <Advert />
            </div>
            <div class="md:w-full md:pl-38">
                <Subscribe />
            </div>
        </template>
    </component>
</template>

<style>
.feature-body div div {
    padding-bottom: 20px;
}

/*
 * The listing keeps the spacing it has on the listings page rather than
 * the body's, and darkens a shade on hover rather than turning near white,
 * which would hide a slate feature's pale text.
 */
.feature-body .feature-listing div {
    padding-bottom: 0;
}

/*
 * The space under the last block of text moves to under the listing, so
 * the row sits close to the text it follows and clear of the divider.
 */
.feature-body > div:has(+ .feature-listing) .rich-text {
    padding-bottom: 0;
}

.feature-body > div:has(+ .feature-listing) .rich-text > :last-child {
    margin-bottom: 0;
}

.feature-body .feature-listing {
    margin-top: 40px;
}

.feature-body .feature-listing > div {
    padding-bottom: calc(1.25rem + 20px + 1rem);
}

.feature-listing > div:hover,
.feature-listing > div:active {
    background-color: rgb(0 0 0 / 0.05);
}

.white {
    background-color: #ffffff;
    color: #000;
}

.slate {
    background-color: #343434;
    color: #f2f2f2;
}

.hi-vis {
    background-color: #fced3a;
    color: #000;
}

.key-lime {
    background-color: #6bfa93;
    color: #000;
}

.slate div {
    border-color: #919191;
}

.slate .pull-quote {
    border-color: #f2f2f2;
}

.hi-vis div {
    border-color: #e4da4a;
}

.hi-vis .pull-quote {
    border-color: #000;
}

.key-lime div {
    border-color: #5adf85;
}

.key-lime .pull-quote {
    border-color: #000;
}
</style>
