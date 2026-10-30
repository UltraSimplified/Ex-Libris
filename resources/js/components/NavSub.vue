<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { type NavItem } from '@/types';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from './ui/sidebar';

/**
 * What sits under something else in the sidebar: a venue's own listings
 * under the venue, the magazine's articles under GOP Online.
 */
defineProps<{
    items: NavItem[];
    /**
     * What these sit under, named in the tooltip once the sidebar is
     * compacted and the indent that said so is gone. Two venues both
     * offer Event Listings, so the name is what tells them apart.
     */
    under?: string;
}>();

const { isCurrentUrl } = useCurrentUrl();
const { state, isMobile } = useSidebar();

/**
 * Compacted to a rail, there is no width left to indent anything under
 * anything, so the sub-menu hides itself - and its entries would go with
 * it. A venue's listings are only ever reached from here, so they come
 * back as icons in their own right rather than dropping off the rail.
 *
 * The mobile sidebar slides out at full width, so it keeps the indent.
 */
const compacted = computed<boolean>(
    () => state.value === 'collapsed' && !isMobile.value,
);
</script>

<template>
    <SidebarMenu v-if="compacted">
        <SidebarMenuItem v-for="item in items" :key="item.title">
            <SidebarMenuButton
                as-child
                :is-active="item.isActive ?? isCurrentUrl(item.href)"
                :tooltip="under ? `${under}: ${item.title}` : item.title"
            >
                <Link :href="item.href">
                    <component :is="item.icon" />
                    <span class="font-semibold uppercase after:content-['.']">{{
                        item.title
                    }}</span>
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
    </SidebarMenu>

    <SidebarMenuSub v-else>
        <SidebarMenuSubItem v-for="item in items" :key="item.title">
            <SidebarMenuSubButton
                as-child
                :is-active="item.isActive ?? isCurrentUrl(item.href)"
            >
                <Link :href="item.href">
                    <component :is="item.icon" />
                    <span class="font-semibold uppercase after:content-['.']">{{
                        item.title
                    }}</span>
                </Link>
            </SidebarMenuSubButton>
        </SidebarMenuSubItem>
    </SidebarMenuSub>
</template>
