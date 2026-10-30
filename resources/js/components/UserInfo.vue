<script setup lang="ts">
import type { User } from '@/types';
import UserAvatar from './UserAvatar.vue';

type Props = {
    user: User;
    showEmail?: boolean;
    /** Dressed as a pin-back badge rather than shown as a flat square. */
    badge?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
    badge: false,
});
</script>

<template>
    <!--
        A badge is round and rimmed whatever size it is shown at, and it
        casts a shadow to scale: what lifts a 250px one off the page would
        be a smudge under this.
    -->
    <UserAvatar
        :user="props.user"
        :badge="props.badge"
        :lift="0.25"
        :class="
            props.badge
                ? 'h-8 w-8 rounded-full border-2 border-hivis'
                : 'h-8 w-8 rounded-lg'
        "
        :fallback-class="props.badge ? 'rounded-full' : 'rounded-lg'"
    />

    <div class="grid flex-1 text-left text-sm leading-tight">
        <span class="truncate font-medium">{{ user.name }}</span>
        <span v-if="showEmail" class="truncate text-xs text-muted-foreground">{{
            user.email
        }}</span>
    </div>
</template>
