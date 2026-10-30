<template>
    <DefaultField
        :field="field"
        :errors="errors"
        :show-help-text="showHelpText"
        :full-width-content="fullWidthContent"
    >
        <template #field>
            <div class="video-embed" :dusk="`${field.attribute}-video-embed`">
                <VideoPreview :code="previewCode" />

                <textarea
                    :id="field.uniqueKey"
                    :value="value"
                    :rows="field.rows || 5"
                    :placeholder="field.placeholder || ''"
                    :disabled="isReadonly"
                    :dusk="field.attribute"
                    class="form-control form-input form-control-bordered block h-auto w-full py-3"
                    :class="errorClasses"
                    @input="handleChange"
                ></textarea>
            </div>
        </template>
    </DefaultField>
</template>

<script>
import { FormField, HandlesValidationErrors } from 'laravel-nova';
import VideoPreview from './VideoPreview.vue';

export default {
    components: { VideoPreview },

    mixins: [FormField, HandlesValidationErrors],

    data() {
        return {
            previewCode: '',
            previewTimer: null,
        };
    },

    watch: {
        /*
         * The preview follows the code once typing pauses, so the player is
         * not reloaded at every keystroke while a code is being pasted in or
         * picked at.
         */
        value(code) {
            clearTimeout(this.previewTimer);
            this.previewTimer = setTimeout(() => {
                this.previewCode = code || '';
            }, 400);
        },
    },

    created() {
        this.previewCode = this.value || '';
    },

    beforeUnmount() {
        clearTimeout(this.previewTimer);
    },
};
</script>
