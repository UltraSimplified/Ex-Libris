<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { edit as editProfile } from '@/routes/profile';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem } from './ui/sidebar';
import UserInfo from './UserInfo.vue';

/*
 * Who is signed in. This used to be a dropdown holding Settings and Log
 * out; both now sit in the footer above it, so there is nothing left
 * behind the chevron to open. The name itself leads to the profile,
 * which is what people try to click on anyway.
 */
const page = usePage();
const user = page.props.auth.user;
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem data-test="sidebar-user">
            <SidebarMenuButton size="lg" as-child>
                <Link :href="editProfile()" class="h-auto gap-2 text-sm">
                    <UserInfo :user="user" badge />
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
