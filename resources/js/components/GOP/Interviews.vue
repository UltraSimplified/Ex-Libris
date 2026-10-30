<script setup lang="ts">
import { usePage, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import BylineName from '@/components/GOP/BylineName.vue';
import ExcerptText from '@/components/GOP/ExcerptText.vue';
import FeatureCard from '@/components/GOP/FeatureCard.vue';
import { articleUrl, tagUrl } from '@/lib/features';
import type { Article } from '@/types/global';

interface Props {
    title: string;
    featured: Article[];
    features: Article[];
    /**
     * Headed as the page's own title, as Archives and Stockists are,
     * rather than as one section among others on the home page.
     */
    pageTitle?: boolean;
}

const page = usePage();
const props = withDefaults(defineProps<Props>(), { pageTitle: false });

/*
 * The featured articles sit side by side in a track one article wide,
 * which snaps to each in turn. A finger or a trackpad scrolls it as it
 * is; the arrows and dots scroll it to an article, smoothly unless the
 * reader has asked for less motion.
 */
const track = ref<HTMLElement | null>(null);
const current = ref(0);
let frame = 0;

function show(index: number): void {
    if (!track.value) {
        return;
    }

    const count = props.featured.length;
    const target = ((index % count) + count) % count;
    const reduceMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    ).matches;

    track.value.scrollTo({
        left: target * track.value.clientWidth,
        behavior: reduceMotion ? 'auto' : 'smooth',
    });
}

function trackScrolled(): void {
    cancelAnimationFrame(frame);
    frame = requestAnimationFrame(() => {
        if (track.value && track.value.clientWidth > 0) {
            current.value = Math.round(
                track.value.scrollLeft / track.value.clientWidth,
            );
        }
    });
}
</script>
<template>
    <!--
        The whole of a panel is a way into its article: the link on the
        title is stretched over the panel, and the tag is lifted above it
        so it still goes to the features filed under it - as a listing's
        row does.
    -->
    <!-- FEATURES -->
    <div
        v-if="props.pageTitle"
        class="border-b border-silver px-5 py-5 pt-4 pb-4 md:border-l"
    >
        <h1 class="text-4xl font-semibold">{{ title }}</h1>
    </div>
    <div v-else class="border-b border-silver px-5 py-5 pt-10 pb-4 md:border-l">
        <h1
            class="text-3xl font-bold"
            style="
                font-family: 'neue-haas-grotesk-display';
                font-size: 24px;
                font-weight: 600 !important;
            "
        >
            {{ title }}
        </h1>
    </div>
    <div class="border-silver px-5 py-5 md:border-l">
        <section
            v-if="props.featured.length"
            class="md:pb-8"
            aria-roledescription="carousel"
            aria-label="Featured"
        >
            <ul
                ref="track"
                class="flex snap-x snap-mandatory [scrollbar-width:none] overflow-x-auto [&::-webkit-scrollbar]:hidden"
                @scroll.passive="trackScrolled"
            >
                <li
                    v-for="(article, index) in props.featured"
                    :key="article.id"
                    class="w-full shrink-0 snap-start"
                    role="group"
                    aria-roledescription="slide"
                    :aria-label="`${index + 1} of ${props.featured.length}`"
                >
                    <div
                        class="relative min-[1160px]:flex min-[1160px]:items-start min-[1160px]:gap-8"
                    >
                        <!-- Image column: matched to the columns below -->
                        <div
                            class="min-[1160px]:w-[calc((100%-4rem)/2)] min-[1160px]:shrink-0"
                        >
                            <img
                                :src="
                                    page.props.cdnUrl +
                                    '/' +
                                    article.cover_photo
                                "
                                :alt="article.cover_alt_text"
                                :loading="index === 0 ? 'eager' : 'lazy'"
                                class="w-full rounded"
                            />
                        </div>

                        <!-- Text takes the rest -->
                        <div class="min-w-0 min-[1160px]:flex-1">
                            <Link
                                :href="tagUrl(article)"
                                class="relative z-10 mt-4 inline-block rounded-sm bg-tarmac px-4 py-2 text-sm font-bold text-white uppercase hover:bg-hivis hover:text-tarmac min-[1160px]:mt-0 md:text-xs"
                            >
                                {{ article.tag.tag }}
                            </Link>

                            <h1
                                class="mt-5 pr-20 text-3xl tracking-tight md:text-3xl"
                            >
                                <Link
                                    :href="articleUrl(article)"
                                    class="after:absolute after:inset-0"
                                    >{{ article.title }}</Link
                                >
                            </h1>
                            <h2
                                class="mt-4 font-din text-sm font-bold uppercase md:text-xs"
                            >
                                BY
                                <BylineName :writer="article.user" />
                            </h2>
                            <ExcerptText
                                :text="article.excerpt"
                                class="mt-8 text-lg min-[1160px]:pr-30 md:text-base md:tracking-normal"
                            />
                        </div>
                    </div>
                </li>
            </ul>

            <nav
                v-if="props.featured.length > 1"
                class="mt-6 flex items-center gap-4"
                aria-label="Featured articles"
            >
                <button
                    type="button"
                    class="flex size-9 cursor-pointer items-center justify-center rounded-full border border-silver text-xl transition hover:bg-tarmac hover:text-white"
                    aria-label="Previous featured article"
                    @click="show(current - 1)"
                >
                    &lsaquo;
                </button>
                <span class="flex items-center gap-2">
                    <button
                        v-for="(article, index) in props.featured"
                        :key="article.id"
                        type="button"
                        class="size-2.5 cursor-pointer rounded-full bg-current opacity-25 transition hover:opacity-60 aria-[current=true]:opacity-100"
                        :aria-label="`Show featured article ${index + 1}`"
                        :aria-current="index === current ? 'true' : undefined"
                        @click="show(index)"
                    ></button>
                </span>
                <button
                    type="button"
                    class="flex size-9 cursor-pointer items-center justify-center rounded-full border border-silver text-xl transition hover:bg-tarmac hover:text-white"
                    aria-label="Next featured article"
                    @click="show(current + 1)"
                >
                    &rsaquo;
                </button>
            </nav>
        </section>
        <div class="md:flex md:gap-8 md:pb-8">
            <FeatureCard
                v-for="feature in props.features"
                :key="feature.id"
                :feature="feature"
                class="m:w-1/3 mt-15 sm:w-full"
            />
        </div>
    </div>
</template>

<style scoped></style>
