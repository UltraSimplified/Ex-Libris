<script setup lang="ts">
import type { TwoColumnLayout } from '@/types/global';

interface Props {
    layout: TwoColumnLayout;
}

const props = defineProps<Props>();
</script>

<template>
    <div
        class="rich-text"
        style="float: left; width: 45%; margin-right: 5%"
        v-html="props.layout.attributes.col1_text"
    ></div>
    <div
        class="rich-text"
        style="float: left; width: 45%"
        v-html="props.layout.attributes.col2_text"
    ></div>
    <div style="clear: both">&nbsp;</div>
</template>

<style scoped></style>
