<template>
    <DefaultField
        :field="field"
        :errors="errors"
        :show-help-text="showHelpText"
        :full-width-content="true"
    >
        <template #field>
            <div class="nova-gallery" :dusk="field.attribute">
                <label
                    v-if="!isReadonly"
                    class="nova-gallery__drop"
                    :class="{ 'is-dragging': isDraggingFiles }"
                    @dragenter.prevent="isDraggingFiles = true"
                    @dragover.prevent="isDraggingFiles = true"
                    @dragleave.prevent="isDraggingFiles = false"
                    @drop.prevent="dropFiles"
                >
                    <input
                        type="file"
                        multiple
                        accept="image/jpeg,image/png,image/webp"
                        class="nova-gallery__input"
                        :dusk="`${field.attribute}-input`"
                        @change="chooseFiles"
                    />
                    <span class="nova-gallery__drop-title"
                        >Choose photos, or drop them here</span
                    >
                    <span class="nova-gallery__hint"
                        >JPEG, PNG or WebP. Each one uploads as soon as it is
                        chosen.</span
                    >
                </label>

                <p v-if="uploading" class="nova-gallery__status">
                    Uploading {{ uploadedCount + 1 }} of {{ queuedCount }}. Wait
                    for the uploads to finish before saving.
                </p>

                <p v-if="images.length" class="nova-gallery__summary">
                    {{ images.length }}
                    {{ images.length === 1 ? 'photo' : 'photos' }}. Drag to
                    reorder. A removed photo is deleted when the feature is
                    saved.
                </p>

                <ol
                    v-if="images.length || uploads.length"
                    class="nova-gallery__grid"
                >
                    <li
                        v-for="(image, index) in images"
                        :key="image.image"
                        class="nova-gallery__item"
                        :class="{
                            'is-moving': movingIndex === index,
                            'is-target':
                                targetIndex === index && movingIndex !== index,
                        }"
                        :draggable="!isReadonly"
                        @dragstart="startMove(index, $event)"
                        @dragenter.prevent="targetIndex = index"
                        @dragover.prevent
                        @drop.prevent="finishMove(index)"
                        @dragend="endMove"
                    >
                        <div class="nova-gallery__thumb">
                            <img
                                :src="url(image.thumb)"
                                :alt="image.alt || ''"
                            />
                            <span class="nova-gallery__number">{{
                                index + 1
                            }}</span>
                        </div>

                        <div v-if="!isReadonly" class="nova-gallery__toolbar">
                            <button
                                type="button"
                                class="nova-gallery__button"
                                title="Move earlier"
                                :disabled="index === 0"
                                @click="move(index, index - 1)"
                            >
                                &larr;
                            </button>
                            <button
                                type="button"
                                class="nova-gallery__button"
                                title="Move later"
                                :disabled="index === images.length - 1"
                                @click="move(index, index + 1)"
                            >
                                &rarr;
                            </button>
                            <button
                                type="button"
                                class="nova-gallery__button nova-gallery__button--remove"
                                @click="remove(index)"
                            >
                                Remove
                            </button>
                        </div>

                        <input
                            v-model="image.alt"
                            type="text"
                            placeholder="Alt text"
                            class="form-control form-input form-control-bordered nova-gallery__text"
                            :disabled="isReadonly"
                        />
                        <input
                            v-model="image.caption"
                            type="text"
                            placeholder="Caption"
                            class="form-control form-input form-control-bordered nova-gallery__text"
                            :disabled="isReadonly"
                        />
                    </li>

                    <li
                        v-for="upload in uploads"
                        :key="upload.id"
                        class="nova-gallery__item is-uploading"
                    >
                        <div class="nova-gallery__thumb">
                            <img
                                v-if="upload.preview"
                                :src="upload.preview"
                                alt=""
                            />
                        </div>
                        <div
                            v-if="!upload.error"
                            class="nova-gallery__progress"
                        >
                            <span
                                :style="{ width: upload.progress + '%' }"
                            ></span>
                        </div>
                        <p class="nova-gallery__file">{{ upload.name }}</p>
                        <p v-if="upload.error" class="nova-gallery__error">
                            {{ upload.error }}
                            <button
                                type="button"
                                class="nova-gallery__button"
                                @click="dismiss(upload)"
                            >
                                Dismiss
                            </button>
                        </p>
                    </li>
                </ol>
            </div>
        </template>
    </DefaultField>
</template>

<script>
import { FormField, HandlesValidationErrors } from 'laravel-nova';
import scaleDown from '../scaleDown';

let nextUploadId = 1;

export default {
    mixins: [FormField, HandlesValidationErrors],

    data() {
        return {
            images: [],
            uploads: [],
            queuedCount: 0,
            uploadedCount: 0,
            uploading: false,
            isDraggingFiles: false,
            movingIndex: null,
            targetIndex: null,
        };
    },

    beforeUnmount() {
        this.uploads.forEach((upload) => this.revokePreview(upload));
    },

    methods: {
        /*
         * The images saved with the feature, copied so editing them here
         * leaves the field's own value alone until the form is submitted.
         */
        setInitialValue() {
            this.images = Array.isArray(this.field.value)
                ? this.field.value.map((image) => ({ ...image }))
                : [];
        },

        fill(formData) {
            this.fillIfVisible(
                formData,
                this.fieldAttribute,
                JSON.stringify(this.images),
            );
        },

        url(path) {
            return `${this.field.baseUrl}/${path}`;
        },

        chooseFiles(event) {
            this.queue(event.target.files);
            event.target.value = '';
        },

        dropFiles(event) {
            this.isDraggingFiles = false;

            if (!this.isReadonly) {
                this.queue(event.dataTransfer.files);
            }
        },

        /*
         * Photos upload one at a time, in the order they were chosen, so the
         * gallery fills up in that order and the server takes one at a time.
         */
        queue(files) {
            const images = Array.from(files || []).filter((file) =>
                /^image\/(jpeg|png|webp)$/.test(file.type),
            );

            images.forEach((file) => {
                this.uploads.push({
                    id: nextUploadId++,
                    file,
                    name: file.name,
                    preview: URL.createObjectURL(file),
                    progress: 0,
                    error: null,
                });
            });

            this.queuedCount += images.length;
            this.uploadNext();
        },

        async uploadNext() {
            if (this.uploading) {
                return;
            }

            const upload = this.uploads.find((item) => !item.error);

            if (!upload) {
                this.queuedCount = 0;
                this.uploadedCount = 0;

                return;
            }

            this.uploading = true;

            try {
                const data = new FormData();
                data.append(
                    'image',
                    await scaleDown(upload.file, this.field.maxEdge),
                );
                data.append('token', this.field.uploadToken);

                const response = await Nova.request().post(
                    this.field.uploadUrl,
                    data,
                    {
                        onUploadProgress: (event) => {
                            upload.progress = event.total
                                ? Math.round((event.loaded / event.total) * 100)
                                : 0;
                        },
                    },
                );

                this.images.push(response.data);
                this.uploads.splice(this.uploads.indexOf(upload), 1);
                this.revokePreview(upload);
            } catch (error) {
                upload.error = this.uploadError(error);
            }

            this.uploadedCount++;
            this.uploading = false;
            this.uploadNext();
        },

        uploadError(error) {
            const response = error && error.response;

            if (response && response.status === 413) {
                return 'Too large to upload.';
            }

            if (response && response.data && response.data.errors) {
                return Object.values(response.data.errors).flat()[0];
            }

            return 'The upload failed.';
        },

        dismiss(upload) {
            this.uploads.splice(this.uploads.indexOf(upload), 1);
            this.revokePreview(upload);
        },

        revokePreview(upload) {
            if (upload.preview) {
                URL.revokeObjectURL(upload.preview);
                upload.preview = null;
            }
        },

        remove(index) {
            this.images.splice(index, 1);
        },

        move(from, to) {
            if (to < 0 || to >= this.images.length || from === to) {
                return;
            }

            const [image] = this.images.splice(from, 1);
            this.images.splice(to, 0, image);
        },

        startMove(index, event) {
            this.movingIndex = index;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', String(index));
        },

        finishMove(index) {
            if (this.movingIndex !== null) {
                this.move(this.movingIndex, index);
            }

            this.endMove();
        },

        endMove() {
            this.movingIndex = null;
            this.targetIndex = null;
        },
    },
};
</script>
