<template>
    <PanelItem :index="index" :field="field">
        <template #value>
            <div class="video-embed">
                <VideoPreview :code="field.value || ''" />
                <p v-if="!field.value">&mdash;</p>
            </div>
        </template>
    </PanelItem>
</template>

<script>
import VideoPreview from './VideoPreview.vue';

export default {
    components: { VideoPreview },

    props: ['index', 'resource', 'resourceName', 'resourceId', 'field'],
};
</script>
