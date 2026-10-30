<script setup lang="ts">
import type { ColophonLayout } from '@/types/global';

interface Props {
    layout: ColophonLayout;
}

const props = defineProps<Props>();
</script>

<template>
    <div class="rich-text" v-html="props.layout.attributes.text"></div>
</template>

<style scoped></style>
