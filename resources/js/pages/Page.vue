<script setup lang="ts">
// Head is imported rather than assumed: it is not globally registered here,
// so a page that only uses the tag silently keeps the default title.
import { Head } from '@inertiajs/vue3';
import type { Layout } from '@/types/global';
import Advert from '../components/GOP/Advert.vue';
import Colophon from '../components/GOP/layouts/Colophon.vue';
import Copy from '../components/GOP/layouts/Copy.vue';
import Divider from '../components/GOP/layouts/Divider.vue';
import ImgFull from '../components/GOP/layouts/ImgFull.vue';
import ImgLeft from '../components/GOP/layouts/ImgLeft.vue';
import ImgRight from '../components/GOP/layouts/ImgRight.vue';
import PullQuote from '../components/GOP/layouts/PullQuote.vue';
import Spacer from '../components/GOP/layouts/Spacer.vue';
import TwoColumns from '../components/GOP/layouts/TwoColumns.vue';
import Embed from '../components/GOP/layouts/Embed.vue';
import Subscribe from '../components/GOP/Subscribe.vue';
import DefaultLayout from '../layouts/DefaultLayout.vue';

interface Page {
    title: string;
    slug: string;
    /**
     * The shared union rather than a local guess at it: without the
     * attributes each layout carries, none of them could be handed to the
     * component that renders it.
     */
    body: Layout[] | string;
}

const props = defineProps<{
    title: string;
    page: Page;
}>();

/**
 * Older pages hold a lump of HTML where newer ones hold Nova's Flexible
 * Content, so the body arrives as either a string or a list of layouts.
 */
const layouts = Array.isArray(props.page.body) ? props.page.body : [];
const html = typeof props.page.body === 'string' ? props.page.body : '';
</script>

<template>
    <Head :title="title" />
    <DefaultLayout>
        <div class="md:w-full md:pl-48">
            <div class="border-b border-silver px-5 py-5 pt-4 pb-4 md:border-l">
                <h1 class="text-4xl font-semibold">{{ page.title }}</h1>
            </div>

            <div class="page-body border-silver px-5 py-5 md:border-l">
                <!--
                    The same measure a feature's body keeps, so a long page
                    reads at the width an article does.
                -->
                <div class="w-full md:w-[726px]">
                    <template v-for="layout in layouts" :key="layout.key">
                        <Copy
                            v-if="layout.layout === 'copy'"
                            :layout="layout"
                        />
                        <PullQuote
                            v-else-if="layout.layout === 'pullquote'"
                            :layout="layout"
                        />
                        <TwoColumns
                            v-else-if="layout.layout === 'twocolumns'"
                            :layout="layout"
                        />
                        <ImgLeft
                            v-else-if="layout.layout === 'imgleft'"
                            :layout="layout"
                        />
                        <ImgRight
                            v-else-if="layout.layout === 'imgright'"
                            :layout="layout"
                        />
                        <ImgFull
                            v-else-if="layout.layout === 'imgfull'"
                            :layout="layout"
                            natural-size
                        />
                        <Embed
                            v-else-if="layout.layout === 'embed'"
                            :layout="layout"
                        />
                        <Divider
                            v-else-if="layout.layout === 'divider'"
                            layout="divider"
                            colourway="white"
                        />
                        <Spacer
                            v-else-if="layout.layout === 'spacer'"
                            :layout="layout"
                        />
                        <Colophon
                            v-else-if="layout.layout === 'colophon'"
                            :layout="layout"
                        />
                    </template>

                    <div v-if="html" v-html="html"></div>
                </div>
            </div>

            <Advert />
            <Subscribe />
        </div>
    </DefaultLayout>
</template>
