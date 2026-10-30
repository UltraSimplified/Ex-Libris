<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { type NavItem } from '@/types';
import NavSub from './NavSub.vue';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from './ui/sidebar';

defineProps<{
    items: NavItem[];
    /**
     * Optional: the sidebar divides its bands with rules rather than
     * headings. A band that does carry one has its items indented
     * beneath it, the way a venue's listings sit under the venue.
     */
    label?: string;
    /**
     * Says what a band is for, above entries laid out as usual: a venue
     * keeps its own listings nested under it, which an indented band
     * would flatten.
     */
    heading?: string;
}>();

const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <template v-if="label">
            <!--
                A heading rather than a link, but it reads at the same
                strength as the entries around it: the band label is set
                lighter and smaller by default, which left GOP Online
                looking like a caption above Articles rather than the
                name of the band.
            -->
            <SidebarGroupLabel
                class="text-sm font-semibold text-sidebar-foreground uppercase after:content-['.']"
                >{{ label }}</SidebarGroupLabel
            >
            <NavSub :items="items" :under="label" />
        </template>

        <template v-else>
            <SidebarGroupLabel
                v-if="heading"
                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase after:content-['.']"
                >{{ heading }}</SidebarGroupLabel
            >

            <SidebarMenu>
                <SidebarMenuItem v-for="item in items" :key="item.title">
                    <SidebarMenuButton
                        as-child
                        :is-active="item.isActive ?? isCurrentUrl(item.href)"
                        :tooltip="item.title"
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" />
                            <span
                                class="font-semibold uppercase after:content-['.']"
                                >{{ item.title }}</span
                            >
                        </Link>
                    </SidebarMenuButton>

                    <NavSub
                        v-if="item.items?.length"
                        :items="item.items"
                        :under="item.title"
                    />
                </SidebarMenuItem>
            </SidebarMenu>
        </template>
    </SidebarGroup>
</template>
