<script setup lang="ts">
import { nextTick, onMounted, ref, watch } from 'vue';
import type { EmbedLayout } from '@/types/global';

interface Props {
    layout: EmbedLayout;
}

const props = defineProps<Props>();
const container = ref<HTMLElement | null>(null);

/**
 * A script that arrives as markup is never run by the browser, so a widget
 * pasted in, Donorbox's for one, would load as an empty tag. Each script is
 * swapped for one made here with the same attributes and body, which the
 * browser does run.
 */
function runScripts(): void {
    container.value?.querySelectorAll('script').forEach((pasted) => {
        const script = document.createElement('script');

        Array.from(pasted.attributes).forEach(({ name, value }) =>
            script.setAttribute(name, value),
        );
        script.text = pasted.text;
        pasted.replaceWith(script);
    });
}

onMounted(runScripts);
watch(
    () => props.layout.attributes.code,
    () => nextTick(runScripts),
);
</script>

<template>
    <!--
        The code is placed as pasted, for widgets such as Donorbox's that
        bring their own script. An embed left empty draws nothing at all.
    -->
    <div
        v-if="props.layout.attributes.code"
        ref="container"
        v-html="props.layout.attributes.code"
    ></div>
</template>

<style scoped></style>
