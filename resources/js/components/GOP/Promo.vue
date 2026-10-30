<script setup lang="ts">
interface Props {
    title: string;
}

const props = defineProps<Props>();
</script>

<template>
    <!-- PROMO -->
    <div
        class="h-full border-silver bg-gray-200 bg-[url('@/../img/rectangle.png')] bg-cover bg-center pb-10 md:border-l"
    >
        <p class="px-5 py-5 text-xs">{{ props.title }}</p>
        <img
            src="@/../img/site.webp"
            class="m-auto w-full px-5 pb-8 md:hidden"
        />
        <img
            src="@/../img/siter.png"
            class="w-100% m-auto hidden px-5 pb-8 md:block md:p-0"
        />
    </div>
</template>

<style scoped></style>
