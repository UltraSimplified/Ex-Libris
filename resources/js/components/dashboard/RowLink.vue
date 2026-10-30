<script setup lang="ts">
import { router } from '@inertiajs/vue3';

/**
 * A table row that opens the thing it describes.
 *
 * The dashboard's lists all used to carry an Edit link at the end of each
 * row, which said nothing the row did not already say and put the way in
 * at the far right of it. The row itself is the way in now - the same way
 * the Office works - so a list is a list rather than a list with a column
 * of the word "Edit" down the side.
 */
const props = defineProps<{
    /** Where the row goes. */
    href: string;
    /** What the row is, read out in place of its cells. */
    label: string;
}>();

/**
 * Anything in the row that does something of its own - a delete button, a
 * link out to a venue - keeps its click. Without this the row would
 * navigate out from under a form the moment it was submitted.
 */
const ownClick = (event: Event): boolean =>
    event.target instanceof Element &&
    event.target.closest(
        'a, button, input, select, textarea, label, [role="button"]',
    ) !== null;

/**
 * Where somebody asked for more than the row's own click. Emitted with
 * the point they asked at, so whatever draws the menu can put it there.
 */
const emit = defineEmits<{
    context: [{ x: number; y: number }];
}>();

/** How long a finger has to rest before it counts as a right-click. */
const LONG_PRESS_MS = 500;

let pressTimer: ReturnType<typeof setTimeout> | null = null;

/**
 * A long press ends in a touchend and then, on most devices, a click. The
 * menu is already open by then, so that click is the tail of the gesture
 * rather than a second instruction.
 */
let openedByPress = false;

const cancelPress = (): void => {
    if (pressTimer !== null) {
        clearTimeout(pressTimer);
        pressTimer = null;
    }
};

const startPress = (event: TouchEvent): void => {
    if (ownClick(event)) {
        return;
    }

    const touch = event.touches[0];

    cancelPress();
    openedByPress = false;

    pressTimer = setTimeout(() => {
        pressTimer = null;
        openedByPress = true;
        emit('context', { x: touch.clientX, y: touch.clientY });
    }, LONG_PRESS_MS);
};

const openMenu = (event: MouseEvent): void => {
    if (ownClick(event)) {
        return;
    }

    event.preventDefault();
    emit('context', { x: event.clientX, y: event.clientY });
};

const open = (event: MouseEvent | KeyboardEvent): void => {
    if (ownClick(event)) {
        return;
    }

    // Held modifiers mean what they mean on a link, since this is one in
    // everything but markup.
    if (
        event instanceof MouseEvent &&
        (event.metaKey || event.ctrlKey || event.shiftKey)
    ) {
        window.open(props.href, '_blank', 'noopener');

        return;
    }

    if (openedByPress) {
        openedByPress = false;

        return;
    }

    router.visit(props.href);
};
</script>

<template>
    <tr
        role="link"
        tabindex="0"
        :aria-label="props.label"
        class="cursor-pointer border-t border-sidebar-border/70 transition-colors hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none dark:border-sidebar-border"
        @click="open"
        @keydown.enter.prevent="open"
        @keydown.space.prevent="open"
        @contextmenu="openMenu"
        @touchstart.passive="startPress"
        @touchend="cancelPress"
        @touchmove.passive="cancelPress"
        @touchcancel="cancelPress"
    >
        <slot />
    </tr>
</template>
