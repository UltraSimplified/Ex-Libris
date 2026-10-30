<script setup lang="ts">
import type { PullQuoteLayout } from '@/types/global';

interface Props {
    layout: PullQuoteLayout;
}

const props = defineProps<Props>();
</script>

<template>
    <div
        class="pull-quote text-tighten mt-4 mr-5 mb-10 ml-10 border-l-4 pb-0 pl-10"
    >
        <span class="text-[30px]/[32px]">
            &ldquo;<span v-html="props.layout.attributes.quote"></span>&rdquo;
        </span>
        <div
            v-html="props.layout.attributes.cite"
            class="text-tighten mt-4 -mb-8 font-din text-[12px]/[12px] uppercase"
        ></div>
    </div>
</template>

<style scoped></style>
