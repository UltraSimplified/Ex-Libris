<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { switchBack } from '@/routes';

const page = usePage();

/** Who switched to this account from the Office, while they are in it. */
const switchedFrom = computed(() => page.props.auth?.switchedFrom ?? null);
</script>

<template>
    <!--
        Staff in somebody else's account, from the Office: say whose it is,
        so nothing is done in it by mistake, and offer the way back.
    -->
    <div
        v-if="switchedFrom"
        class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 bg-hivis px-6 py-2 text-sm text-black md:px-4"
    >
        <p>
            You're in <strong>{{ page.props.auth.user.name }}</strong
            >'s account, switched from the Office by {{ switchedFrom }}.
        </p>
        <Link
            :href="switchBack()"
            as="button"
            class="font-bold underline underline-offset-2 hover:no-underline"
        >
            Switch back
        </Link>
    </div>
</template>
