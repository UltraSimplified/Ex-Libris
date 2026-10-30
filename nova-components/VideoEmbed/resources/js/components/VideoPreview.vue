<template>
    <div v-if="preview.src" class="video-embed__frame">
        <iframe
            :src="preview.src"
            title="Video preview"
            allow="
                accelerometer;
                autoplay;
                clipboard-write;
                encrypted-media;
                gyroscope;
                picture-in-picture;
                web-share;
            "
            referrerpolicy="strict-origin-when-cross-origin"
            allowfullscreen
        ></iframe>
    </div>
    <p v-else-if="preview.message" class="video-embed__message">
        {{ preview.message }}
    </p>
</template>

<script>
import embedPreview from '../embedPreview';

export default {
    props: {
        code: {
            type: String,
            default: '',
        },
    },

    computed: {
        preview() {
            return embedPreview(this.code);
        },
    },
};
</script>
