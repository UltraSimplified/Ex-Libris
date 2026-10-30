<template>
    <PanelItem :index="index" :field="field">
        <template #value>
            <div v-if="images.length" class="nova-gallery__strip">
                <img
                    v-for="image in images"
                    :key="image.image"
                    :src="`${field.baseUrl}/${image.thumb}`"
                    :alt="image.alt || ''"
                    :title="image.caption || ''"
                />
            </div>
            <p v-else>&mdash;</p>
        </template>
    </PanelItem>
</template>

<script>
export default {
    props: ['index', 'resource', 'resourceName', 'resourceId', 'field'],

    computed: {
        images() {
            return Array.isArray(this.field.value) ? this.field.value : [];
        },
    },
};
</script>
