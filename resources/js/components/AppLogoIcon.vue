<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<!--
  The Good on Paper mark: the wordmark in three lines, set solid and
  ragged right, as it runs on the masthead and in Nova's sidebar.

  The leading is 0.8 of the type size, which is what the masthead sets -
  the lines close up until the block reads as one shape rather than three
  words.

  Drawn as type rather than paths so it picks up the brand face the app
  already loads, and carried by currentColor so it sits on the light badge
  and the hivis one alike.

  The box is a little wider than the longest line needs. Ragged right
  means the slack is invisible, and it leaves room for the fallback face,
  which sets wider - without it PAPER overflowed the box and lost its last
  letter until Typekit arrived.
-->
<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 44 32"
        :class="className"
        v-bind="$attrs"
        role="img"
        aria-label="Good on Paper"
    >
        <g
            fill="currentColor"
            font-family="neue-haas-grotesk-display, 'Helvetica Neue', sans-serif"
            font-weight="700"
            font-size="13"
        >
            <text x="0" y="10.4">GOOD</text>
            <text x="0" y="20.8">ON</text>
            <text x="0" y="31.2">PAPER</text>
        </g>
    </svg>
</template>
