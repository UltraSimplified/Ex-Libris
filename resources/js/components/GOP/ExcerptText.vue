<script setup lang="ts">
import { computed } from 'vue';

/**
 * A feature's excerpt, in its paragraphs.
 *
 * Excerpts are typed into the Office as plain text, with a blank line
 * between paragraphs. Many were copied out of the magazine's PDF and keep
 * its line ends mid-sentence, so a single line break is run back into the
 * sentence and only a blank line starts a new paragraph.
 */
interface Props {
    text: string | null;
}

const props = defineProps<Props>();

const paragraphs = computed<string[]>(() =>
    (props.text ?? '')
        .split(/\r?\n\s*\r?\n/)
        .map((paragraph) => paragraph.replace(/\s*\r?\n\s*/g, ' ').trim())
        .filter(Boolean),
);
</script>

<template>
    <div>
        <p
            v-for="(paragraph, index) in paragraphs"
            :key="index"
            :class="{ 'mt-4': index > 0 }"
        >
            {{ paragraph }}
        </p>
    </div>
</template>
