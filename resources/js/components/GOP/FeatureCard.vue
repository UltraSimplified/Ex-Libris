<script setup lang="ts">
import { usePage, Link } from '@inertiajs/vue3';
import BylineName from '@/components/GOP/BylineName.vue';
import ExcerptText from '@/components/GOP/ExcerptText.vue';
import { articleUrl, tagUrl } from '@/lib/features';
import type { Article } from '@/types/global';

/**
 * A feature as a card in a row of them: its picture, its category, its
 * title, who wrote it and the start of it.
 *
 * The whole card is a way into the feature: the link on the title is
 * stretched over it, and the category is lifted above that link so it
 * still goes to the features filed under it.
 */
interface Props {
    feature: Article;
}

const props = defineProps<Props>();
const page = usePage();
</script>

<template>
    <div class="relative">
        <img
            :src="page.props.cdnUrl + '/' + props.feature.cover_photo"
            :alt="props.feature.cover_alt_text"
            class="rounded"
        />
        <Link
            :href="tagUrl(props.feature)"
            class="relative z-10 mt-4 inline-block rounded-sm bg-tarmac px-4 py-2 text-sm font-bold text-white uppercase hover:bg-hivis hover:text-tarmac md:text-xs"
        >
            {{ props.feature.tag.tag }}
        </Link>
        <h1 class="mt-5 text-2xl tracking-tight">
            <Link
                :href="articleUrl(props.feature)"
                class="after:absolute after:inset-0"
                >{{ props.feature.title }}</Link
            >
        </h1>
        <h2 class="mt-4 font-din text-sm font-bold uppercase md:text-xs">
            BY
            <BylineName :writer="props.feature.user" />
        </h2>
        <ExcerptText
            :text="props.feature.excerpt"
            class="mt-8 text-lg md:text-base"
        />
    </div>
</template>
