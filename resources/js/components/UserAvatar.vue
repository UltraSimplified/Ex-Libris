<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import { useInitials } from '@/composables/useInitials';
import { cn } from '@/lib/utils';
import { Avatar, AvatarFallback, AvatarImage } from './ui/avatar';

/**
 * Anything with a name and perhaps a picture: an account, or the byline a
 * public page is handed, which is an account cut down to what it is
 * allowed to show. Only these two are read, so asking for a whole User
 * would shut the byline out of a component it needs.
 */
type Depicted = {
    name: string;
    avatar?: string | null;
};

const props = withDefaults(
    defineProps<{
        user: Depicted;
        class?: HTMLAttributes['class'];
        /**
         * What the picture should be read as. Falls back to the name,
         * which is right for an account picking its own avatar and wrong
         * for a byline, where the writer has described theirs.
         */
        alt?: string | null;
        /** Sized with the avatar rather than left at the fallback's default. */
        fallbackClass?: HTMLAttributes['class'];
        /** Dressed as a pin-back badge rather than shown as a flat circle. */
        badge?: boolean;
        /**
         * How far the badge stands off the page, as a multiple of what
         * suits a large one. A shadow that reads as a lift at 250px reads
         * as a smudge at 32, so a small badge asks for less of it.
         */
        lift?: number;
    }>(),
    { badge: false, lift: 1 },
);

const { getInitials } = useInitials();
const page = usePage();

/**
 * Avatars are stored as a path on the Spaces disk - "avatars/xyz.webp" -
 * so used raw the browser resolves them against the current page and gets
 * a 404. Anything already absolute is left alone.
 */
const avatarUrl = computed<string | null>(() => {
    const avatar = props.user.avatar;

    if (!avatar) {
        return null;
    }

    return /^(https?:)?\/\//.test(avatar)
        ? avatar
        : `${String(page.props.cdnUrl).replace(/\/$/, '')}/${avatar.replace(/^\//, '')}`;
});
</script>

<template>
    <!--
        Always wrapped, so there is one code path rather than two copies of
        the avatar. Without the badge the wrapper is display:contents and
        the avatar lays out against its parent as though nothing were
        between them.
    -->
    <span
        :class="props.badge ? 'pin-badge rounded-full' : 'contents'"
        :style="props.badge ? { '--badge-lift': props.lift } : undefined"
    >
        <Avatar :class="cn('overflow-hidden', props.class)">
            <AvatarImage
                v-if="avatarUrl"
                :src="avatarUrl"
                :alt="props.alt || props.user.name"
                class="h-full w-full object-cover"
            />
            <AvatarFallback
                :class="cn('text-black dark:text-white', props.fallbackClass)"
            >
                {{ getInitials(props.user.name) }}
            </AvatarFallback>
        </Avatar>
    </span>
</template>

<style scoped>
/*
 * An avatar dressed as a pin-back badge.
 *
 * Two things read as a badge rather than a circular photo: the tin
 * curling over the edge, and a shadow that sits it above the page instead
 * of printing it on to it. Both belong to the circle; nothing is laid over
 * the picture, which shows exactly as it was uploaded.
 *
 * The dashboard rounds nothing by default, which is why the wrapper
 * carries rounded-full: that class is on the short list of things its
 * rule leaves alone.
 */
.pin-badge {
    --badge-lift: 1;

    display: inline-flex;
    border-radius: 9999px;
    box-shadow:
        /* the crimped rim, drawn as a hairline rather than a border so it
           does not eat into the avatar's own size */
        0 0 0 1px rgb(0 0 0 / 0.2),
        0 calc(2px * var(--badge-lift)) calc(3px * var(--badge-lift))
            rgb(0 0 0 / 0.18),
        0 calc(14px * var(--badge-lift)) calc(22px * var(--badge-lift))
            calc(-8px * var(--badge-lift)) rgb(0 0 0 / 0.45);
}
</style>
