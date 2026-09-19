<template>
    <Modal
        :model-value="true"
        :title="isEdit ? 'Edit media' : 'Add media'"
        subtitle="Upload an image or paste a video embed URL."
        size="lg"
        @update:modelValue="v => !v && $emit('close')"
    >
        <form @submit.prevent="submit" enctype="multipart/form-data" class="space-y-5">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <FormField label="Caption" hint="Optional — leave blank to skip." :error="errors.caption" class="sm:col-span-2">
                    <Input v-model="form.caption" placeholder="Short caption shown as overlay" :error="!!errors.caption" />
                </FormField>

                <FormField label="Priority" required hint="Lower numbers appear first." :error="errors.priority">
                    <Input v-model="form.priority" type="number" placeholder="0" :error="!!errors.priority" />
                </FormField>

                <FormField label="Action URL" required hint="Where the slide links when clicked." :error="errors.action_url">
                    <Input v-model="form.action_url" placeholder="/shop or full URL" :error="!!errors.action_url" />
                </FormField>

                <FormField label="Media type" required :error="errors.type" class="sm:col-span-2">
                    <Select
                        v-model="form.type"
                        :options="[
                            { value: 'image', label: 'Image' },
                            { value: 'video', label: 'Video (YouTube embed URL)' },
                        ]"
                        :error="!!errors.type"
                    />
                </FormField>
            </div>

            <div v-if="form.type === 'image'">
                <FormField label="Image" required :error="errors.image">
                    <label
                        for="media-dropzone"
                        class="group flex min-h-[10rem] w-full cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-200 dark:border-white/[0.1] bg-gray-50/50 dark:bg-white/[0.02] p-4 transition-colors hover:border-gray-400 dark:hover:border-white/[0.2] hover:bg-gray-50 dark:hover:bg-white/[0.04]"
                    >
                        <input
                            id="media-dropzone"
                            @change="handleFileChange"
                            type="file"
                            ref="fileInput"
                            accept="image/jpeg,image/png,image/webp,image/jpg"
                            class="sr-only"
                        />
                        <div v-if="previewImage" class="w-full flex flex-col items-center gap-2">
                            <img :src="previewImage" class="max-h-40 rounded-lg object-cover border border-gray-200 dark:border-white/[0.06]" alt="Preview" />
                            <span class="text-[12px] font-medium text-gray-700 dark:text-gray-300 group-hover:text-gray-900 dark:group-hover:text-white/95 transition-colors">
                                Click to replace
                            </span>
                        </div>
                        <div v-else class="flex flex-col items-center text-center">
                            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400 mb-2 group-hover:bg-gray-200 dark:group-hover:bg-white/[0.1] transition-colors">
                                <UploadCloud :size="18" />
                            </span>
                            <p class="text-body-strong text-gray-800 dark:text-white/90">
                                Drop image or <span class="text-gray-900 dark:text-white underline underline-offset-2">browse</span>
                            </p>
                            <p class="mt-1 text-caption">PNG, JPG or WebP</p>
                        </div>
                    </label>
                    <button
                        v-if="previewImage"
                        type="button"
                        @click="removeImage"
                        class="mt-2 inline-flex items-center gap-1 text-[12px] font-medium text-error-600 dark:text-error-400 hover:underline focus-ring rounded"
                    >
                        <X :size="12" />
                        Remove image
                    </button>
                </FormField>
            </div>

            <div v-else class="space-y-3">
                <FormField label="Video URL" required :error="errors.video_url">
                    <Input
                        v-model="form.video_url"
                        @input="getVideoUrl"
                        placeholder="https://www.youtube.com/embed/..."
                        :error="!!errors.video_url"
                    />
                </FormField>
                <div v-if="form.video_url" class="overflow-hidden rounded-lg aspect-video w-full bg-gray-100 dark:bg-white/[0.03] border border-gray-200 dark:border-white/[0.06]">
                    <iframe :src="form.video_url" title="Video preview" frameborder="0" allowfullscreen class="w-full h-full"></iframe>
                </div>
            </div>
        </form>

        <template #footer>
            <Button variant="secondary" @click="$emit('close')">Cancel</Button>
            <Button variant="primary" :loading="processing" @click="submit">
                {{ isEdit ? 'Save changes' : 'Add media' }}
            </Button>
        </template>
    </Modal>
</template>

<script>
import { router, usePage } from '@inertiajs/vue3';
import Common from '../../../Components/Composables/Common';
import { Modal, Button, Input, Select, FormField } from '@/Components/ui';
import { UploadCloud, X } from '@lucide/vue';

export default {
    components: { Modal, Button, Input, Select, FormField, UploadCloud, X },
    props: {
        sliderMedia: Object,
        sliderId: Number,
    },
    emits: ['close'],
    data() {
        const isImage = (this.sliderMedia?.type || 'image') === 'image';
        return {
            form: {
                caption: this.sliderMedia?.caption || '',
                priority: this.sliderMedia?.priority ?? '',
                action_url: this.sliderMedia?.action_url || '',
                image: null,
                video_url: this.sliderMedia?.type === 'video' ? this.sliderMedia?.url : '',
                type: this.sliderMedia?.type || 'image',
            },
            previewImage: isImage && this.sliderMedia?.url ? `/storage/slider/${this.sliderMedia.url}` : null,
            processing: false,
        };
    },
    computed: {
        isEdit() { return !!this.sliderMedia?.id; },
        // Read validation errors from Inertia's shared page props so the same
        // errors show whether the response is 302-redirect-back (Laravel's
        // FormRequest default) or 422-JSON. Local `form.errors` alone misses
        // the redirect-back flow because onError isn't always the source.
        errors() { return usePage().props.errors || {}; },
    },
    created() {
        const { getVideoUrl } = Common();
        this.getVideoUrl = getVideoUrl;
    },
    methods: {
        handleFileChange(e) {
            const file = e.target.files[0];
            if (file) {
                this.form.image = file;
                this.previewImage = URL.createObjectURL(file);
            }
        },
        removeImage() {
            this.previewImage = null;
            this.form.image = null;
            if (this.$refs.fileInput) this.$refs.fileInput.value = '';
        },
        submit() {
            let url = route('admin.slider.media.save', this.sliderId);
            const payload = { ...this.form };
            // Only send `image` when the user picked a new File; otherwise omit it so the
            // backend keeps the existing image on edit and doesn't fail required_if on POST-nothing.
            if (!(payload.image instanceof File)) {
                delete payload.image;
            }
            if (this.isEdit) {
                payload._method = 'put';
                url = route('admin.slider.media.update', [this.sliderId, this.sliderMedia.id]);
            }
            this.processing = true;
            router.post(url, payload, {
                forceFormData: true,
                preserveScroll: true,
                // onSuccess only fires when the response has NO validation errors,
                // so it's safe to close the modal here. On a 422, Inertia fires
                // onError instead and this callback is skipped (modal stays open).
                onSuccess: () => { this.$emit('close'); },
                onFinish: () => { this.processing = false; },
            });
        },
    },
};
</script>
