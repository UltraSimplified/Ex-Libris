<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import UserAvatar from '@/components/UserAvatar.vue';
import type { LozengeSize, WriterLink } from '@/types/global';

interface Props {
    writer: WriterLink;
    direction: 'previous' | 'next';
    size?: LozengeSize;
}

const props = withDefaults(defineProps<Props>(), { size: 'lg' });

const next = computed<boolean>(() => props.direction === 'next');
const label = computed<string>(() =>
    next.value ? 'Next writer' : 'Previous writer',
);

/*
 * Each size spelt out whole, so Tailwind finds every class. The outer end
 * - away from the arrow - carries the extra padding, so it swaps sides
 * with the arrow for the next writer.
 */
const sizes = {
    lg: {
        lozenge:
            'min-h-32 shrink-0 gap-4 p-4 rounded-[2.5rem] supports-[corner-shape:squircle]:rounded-[3.5rem]',
        outer: { previous: 'pr-7', next: 'pl-7' },
        arrow: 'size-14',
        icon: 'size-6',
        avatar: 'size-20',
        avatarWrap: '',
        initials: 'text-xl',
        label: 'text-[10px] tracking-[.25em] whitespace-nowrap',
        name: 'text-3xl whitespace-nowrap',
    },
    md: {
        lozenge:
            'min-h-24 shrink-0 gap-3 p-3 rounded-[2rem] supports-[corner-shape:squircle]:rounded-[3rem]',
        outer: { previous: 'pr-6', next: 'pl-6' },
        arrow: 'size-11',
        icon: 'size-5',
        avatar: 'size-14',
        avatarWrap: '',
        initials: 'text-base',
        label: 'text-[10px] tracking-[.25em] whitespace-nowrap',
        name: 'text-2xl whitespace-nowrap',
    },
    sm: {
        lozenge:
            'min-h-20 min-w-0 flex-1 gap-2 p-2 rounded-[1.5rem] supports-[corner-shape:squircle]:rounded-[2.25rem]',
        outer: { previous: 'pr-4', next: 'pl-4' },
        arrow: 'size-9',
        icon: 'size-4',
        avatar: 'size-10',
        // On a phone the picture gives its room to the name.
        avatarWrap: 'max-sm:hidden',
        initials: 'text-xs',
        label: 'text-[9px] tracking-[.12em]',
        name: 'text-base break-words',
    },
} as const;

const look = computed(() => sizes[props.size]);
</script>

<template>
    <!--
        A squircle where the browser can draw one, and a well-rounded
        rectangle where it cannot. The next writer reads from the right:
        the row is reversed, so the arrow leads on either side.
    -->
    <Link
        :href="`/our-writers/${props.writer.slug}`"
        :rel="next ? 'next' : 'prev'"
        prefetch
        :data-size="props.size"
        class="writer-lozenge group flex max-w-full items-center border border-silver bg-surface-raised transition-colors hover:border-hivis hover:bg-hivis hover:text-black supports-[corner-shape:squircle]:[corner-shape:squircle]"
        :class="[
            look.lozenge,
            look.outer[props.direction],
            next ? 'ml-auto flex-row-reverse text-right' : '',
        ]"
        :aria-label="`${label}: ${props.writer.name}`"
    >
        <span
            class="flex shrink-0 items-center justify-center rounded-full border border-current transition-transform"
            :class="[
                look.arrow,
                next
                    ? 'group-hover:translate-x-1'
                    : 'group-hover:-translate-x-1',
            ]"
            aria-hidden="true"
        >
            <ArrowRight v-if="next" :class="look.icon" />
            <ArrowLeft v-else :class="look.icon" />
        </span>
        <span class="shrink-0" :class="look.avatarWrap" aria-hidden="true">
            <UserAvatar
                :user="props.writer"
                :class="[
                    look.avatar,
                    'rounded-full border border-silver bg-surface',
                ]"
                :fallback-class="look.initials"
            />
        </span>
        <span class="min-w-0">
            <span class="block font-din uppercase" :class="look.label">{{
                label
            }}</span>
            <span
                class="mt-1 block leading-tight font-bold"
                :class="look.name"
                >{{ props.writer.name }}</span
            >
        </span>
    </Link>
</template>
