<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { logout } from '@/routes';
import TextLink from '../../components/TextLink.vue';
import AuthLayout from '../../layouts/AuthLayout.vue';

const page = usePage();

const description = computed(
    () =>
        `You're logged in as ${page.props.auth.user?.email}. Log out, then open the link in your email again.`,
);
</script>

<template>
    <AuthLayout
        title="This link is for a different account"
        :description="description"
    >
        <Head title="Email verification" />

        <div class="space-y-6 text-center">
            <p class="text-sm text-muted-foreground">
                If you changed your email address after this was sent, use the
                link in the newest email instead.
            </p>

            <TextLink
                :href="logout()"
                as="button"
                class="mx-auto block text-sm"
            >
                Log out
            </TextLink>
        </div>
    </AuthLayout>
</template>
