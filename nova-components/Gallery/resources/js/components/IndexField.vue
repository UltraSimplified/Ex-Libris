<template>
    <span>{{
        count ? `${count} ${count === 1 ? 'photo' : 'photos'}` : '—'
    }}</span>
</template>

<script>
export default {
    props: ['resourceName', 'field'],

    computed: {
        count() {
            return Array.isArray(this.field.value)
                ? this.field.value.length
                : 0;
        },
    },
};
</script>
