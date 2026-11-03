<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { toUrl } from '@/lib/utils';
import { type NavItem } from '@/types';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from './ui/sidebar';

/**
 * Anything with a scheme - mailto:, https: - leaves the site and opens in
 * its own tab. A path stays here and is followed like any other link.
 */
const leavesTheSite = (item: NavItem): boolean =>
    /^[a-z]+:/i.test(toUrl(item.href));

/**
 * Log out posts rather than gets, so it has to be a button in a form
 * rather than an anchor the browser could prefetch.
 */
const posts = (item: NavItem): boolean =>
    typeof item.href === 'object' &&
    'method' in item.href &&
    item.href.method !== 'get';

type Props = {
    items: NavItem[];
    class?: string;
};

defineProps<Props>();
</script>

<template>
    <SidebarGroup
        :class="`group-data-[collapsible=icon]:p-0 ${$props.class || ''}`"
    >
        <SidebarGroupContent>
            <SidebarMenu class="gap-0">
                <SidebarMenuItem v-for="item in items" :key="item.title">
                    <SidebarMenuButton
                        class="text-neutral-600 hover:text-neutral-800 dark:text-neutral-300 dark:hover:text-neutral-100"
                        as-child
                    >
                        <a
                            v-if="leavesTheSite(item) || item.external"
                            :href="toUrl(item.href)"
                            :target="leavesTheSite(item) ? '_blank' : undefined"
                            :rel="
                                leavesTheSite(item)
                                    ? 'noopener noreferrer'
                                    : undefined
                            "
                        >
                            <component :is="item.icon" />
                            <span
                                class="font-semibold uppercase after:content-['.']"
                                >{{ item.title }}</span
                            >
                        </a>
                        <Link
                            v-else
                            :href="item.href"
                            :as="posts(item) ? 'button' : undefined"
                            :class="posts(item) ? 'w-full' : undefined"
                            @click="item.onSelect?.($event)"
                        >
                            <component :is="item.icon" />
                            <span
                                class="font-semibold uppercase after:content-['.']"
                                >{{ item.title }}</span
                            >
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroupContent>
    </SidebarGroup>
</template>
