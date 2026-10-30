<script setup lang="ts">
/**
 * A stamp across a stand-in image saying that is what it is.
 *
 * A listing with no picture of its own is given one of the category
 * placeholders, which look enough like real photographs to be taken for
 * the event's own. This is shown only in the preview - a promoter reading
 * their own listing before it goes out - where it asks them for a
 * picture. The public pages leave it off: most of what is there came in
 * from the magazine without images, and stamping all of it would say
 * nothing to the person who could do something about it.
 *
 * The parent has to be positioned; every image this sits on already is.
 */
</script>

<template>
    <div
        class="@container pointer-events-none absolute inset-0 flex items-center justify-center overflow-hidden"
    >
        <!--
            Sized against the image rather than the page: the same stamp
            reads across a thumbnail in a list and a full-width picture on
            the listing itself.
        -->
        <span
            class="-rotate-12 text-center font-din text-[7cqw] leading-none font-bold tracking-[0.05em] whitespace-nowrap text-red-600 uppercase [text-shadow:0_1px_2px_rgba(255,255,255,0.9)]"
        >
            Image placeholder
        </span>
    </div>
</template>
