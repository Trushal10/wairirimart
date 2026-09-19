<template>
    <Head :title="isEdit ? 'Edit blog post' : 'New blog post'" />

    <PageHeader
        :title="isEdit ? blog.title : 'New blog post'"
        :subtitle="isEdit ? 'Update the article content, cover image, and visibility.' : 'Write a new article to feature on your storefront blog.'"
        :crumbs="crumbs"
    >
        <template #titleBadge>
            <Badge :variant="form.is_active ? 'success' : 'neutral'" size="sm" dot>
                {{ form.is_active ? 'Published' : 'Draft' }}
            </Badge>
        </template>
        <template #actions>
            <Button variant="secondary" size="sm" tag="a" :href="route('admin.blogs.index')">Cancel</Button>
            <Button variant="primary" size="sm" :loading="form.processing" @click="submit">
                {{ isEdit ? 'Save changes' : 'Publish post' }}
            </Button>
        </template>
    </PageHeader>

    <form @submit.prevent="submit" enctype="multipart/form-data" class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <!-- Main -->
        <div class="xl:col-span-2 space-y-6">
            <Card mode="flat" title="Article" subtitle="The heading and body shoppers will read.">
                <div class="space-y-5">
                    <FormField label="Title" required :error="errors?.title">
                        <Input v-model="form.title" placeholder="e.g. How to Choose the Right Wax for Container Candles" :error="!!errors?.title" />
                    </FormField>

                    <FormField label="Excerpt" hint="A short teaser shown on the blog listing." :error="errors?.excerpt">
                        <Textarea v-model="form.excerpt" rows="3" placeholder="A one- or two-sentence summary…" :error="!!errors?.excerpt" />
                    </FormField>

                    <FormField label="Content" required :error="errors?.content">
                        <div class="rounded-lg border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-[color:var(--color-surface-dark)] overflow-hidden">
                            <QuillEditorComponent v-model="form.content" />
                        </div>
                    </FormField>
                </div>
            </Card>

            <Card mode="flat" title="SEO" subtitle="Optional overrides for search engines and social sharing.">
                <div class="space-y-5">
                    <FormField label="Meta title" :error="errors?.meta_title">
                        <Input v-model="form.meta_title" placeholder="Defaults to article title" :error="!!errors?.meta_title" />
                    </FormField>
                    <FormField label="Meta description" :error="errors?.meta_description">
                        <Textarea v-model="form.meta_description" rows="3" placeholder="Defaults to excerpt…" :error="!!errors?.meta_description" />
                    </FormField>
                </div>
            </Card>
        </div>

        <!-- Sidebar -->
        <aside class="space-y-6 xl:col-span-1">
            <Card mode="flat" title="Publish">
                <div class="space-y-4">
                    <Switch v-model="form.is_active">
                        <span class="font-medium text-gray-900 dark:text-white/95">
                            {{ form.is_active ? 'Published' : 'Draft' }}
                        </span>
                        <span class="block text-[12px] text-gray-500 dark:text-gray-400">
                            Only published posts appear on the storefront.
                        </span>
                    </Switch>

                    <FormField label="Publish date" hint="Leave blank to publish immediately." :error="errors?.published_at">
                        <Input v-model="form.published_at" type="datetime-local" />
                    </FormField>
                </div>
            </Card>

            <Card mode="flat" title="Details">
                <div class="space-y-5">
                    <FormField label="Category" hint="Free-text, e.g. Tutorials, Tips, News." :error="errors?.category">
                        <Input v-model="form.category" placeholder="e.g. Tutorials" :error="!!errors?.category" />
                    </FormField>
                    <FormField label="Author" :error="errors?.author">
                        <Input v-model="form.author" placeholder="e.g. Priya Sharma" :error="!!errors?.author" />
                    </FormField>
                </div>
            </Card>

            <Card mode="flat" title="Cover image">
                <div>
                    <label
                        for="blog-dropzone"
                        class="group flex min-h-[10rem] w-full cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-200 dark:border-white/[0.1] bg-gray-50/50 dark:bg-white/[0.02] p-4 transition-colors hover:border-gray-400 dark:hover:border-white/[0.2] hover:bg-gray-50 dark:hover:bg-white/[0.04]"
                    >
                        <input
                            id="blog-dropzone"
                            @change="handleFileChange"
                            type="file"
                            ref="fileInput"
                            accept="image/jpeg,image/png,image/webp,image/jpg"
                            class="sr-only"
                        />
                        <div v-if="previewImage" class="w-full flex flex-col items-center gap-3">
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
                            <p class="mt-1 text-caption">PNG, JPG, WebP · auto-compressed</p>
                        </div>
                    </label>

                    <button
                        v-if="previewImage"
                        type="button"
                        @click="removeImage"
                        class="mt-3 inline-flex items-center gap-1 text-[12px] font-medium text-error-600 dark:text-error-400 hover:underline focus-ring rounded"
                    >
                        <X :size="12" />
                        Remove image
                    </button>

                    <p v-if="errors?.image" class="mt-2 text-[12px] text-error-500">{{ form.errors.image }}</p>

                    <div v-if="compressingImage" class="mt-2 flex items-center gap-1.5 text-[12px] text-gray-600 dark:text-gray-400">
                        <Loader2 :size="12" class="animate-spin" />
                        Compressing image…
                    </div>
                    <p v-else-if="imageStats" class="mt-2 text-[12px] text-success-700 dark:text-success-400 num-tabular">
                        Compressed: {{ formatBytes(imageStats.original) }} → {{ formatBytes(imageStats.compressed) }}
                        (saved {{ Math.round((1 - imageStats.compressed / imageStats.original) * 100) }}%)
                    </p>
                </div>
            </Card>

            <div v-if="form.processing && uploadProgress > 0" class="rounded-xl border border-gray-200 dark:border-white/[0.06] bg-white dark:bg-[color:var(--color-surface-dark)] p-4">
                <div class="mb-1.5 flex items-center justify-between text-[12px] text-gray-600 dark:text-gray-400">
                    <span class="inline-flex items-center gap-1.5">
                        <Loader2 :size="12" class="animate-spin" />
                        Uploading…
                    </span>
                    <span class="num-tabular font-medium text-gray-900 dark:text-white/95">{{ uploadProgress }}%</span>
                </div>
                <div class="h-1.5 bg-gray-100 dark:bg-white/[0.06] rounded-full overflow-hidden">
                    <div class="h-full bg-gray-900 dark:bg-white rounded-full transition-all duration-200" :style="{ width: uploadProgress + '%' }"></div>
                </div>
            </div>
        </aside>
    </form>
</template>

<script>
import Layout from '@/Layout/MainLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { compressImage, formatBytes } from '@/Utils/compressImage';
import QuillEditorComponent from '@/Components/common/QuillEditorComponent.vue';
import { PageHeader, Card, Button, Input, Textarea, Switch, FormField, Badge } from '@/Components/ui';
import { UploadCloud, X, Loader2 } from '@lucide/vue';

export default {
    layout: Layout,
    components: {
        Head, QuillEditorComponent,
        PageHeader, Card, Button, Input, Textarea, Switch, FormField, Badge,
        UploadCloud, X, Loader2,
    },
    props: {
        blog: Object,
    },
    data() {
        return {
            form: {
                title:            this.blog?.title || '',
                category:         this.blog?.category || '',
                author:           this.blog?.author || '',
                excerpt:          this.blog?.excerpt || '',
                content:          this.blog?.content || '',
                meta_title:       this.blog?.meta_title || '',
                meta_description: this.blog?.meta_description || '',
                is_active:        this.blog ? Boolean(this.blog.is_active) : true,
                published_at:     this.blog?.published_at ? String(this.blog.published_at).substring(0, 16) : '',
                image:            null,
                processing:       false,
                errors:           {},
            },
            previewImage: null,
            compressingImage: false,
            uploadProgress: 0,
            imageStats: null,
        };
    },
    computed: {
        // Reactive validation errors from Inertia's shared page props.
        // This is the reliable source (works with 302-back and 422 responses).
        errors() { return usePage().props.errors || {}; },
        isEdit() { return !!this.blog?.id; },
        crumbs() {
            return [
                { label: 'Dashboard', href: route('admin.dashboard') },
                { label: 'Blog posts', href: route('admin.blogs.index') },
                { label: this.isEdit ? 'Edit' : 'New' },
            ];
        },
    },
    mounted() {
        this.previewImage = this.blog?.image ? `/storage/blog/${this.blog.image}` : null;
    },
    methods: {
        async handleFileChange(e) {
            const file = e.target.files[0];
            if (!file) return;
            e.target.value = null;
            this.compressingImage = true;
            const original = file.size;
            try {
                let output = file;
                try {
                    output = await compressImage(file, {
                        maxDimension: 1600,
                        quality: 0.85,
                        mimeType: 'image/jpeg',
                    });
                } catch (err) {
                    console.warn('Blog image compression failed, using original:', err);
                }
                if (this.previewImage && this.previewImage.startsWith('blob:')) {
                    URL.revokeObjectURL(this.previewImage);
                }
                this.form.image = output;
                this.previewImage = URL.createObjectURL(output);
                this.imageStats = { original, compressed: output.size };
            } finally {
                this.compressingImage = false;
            }
        },
        removeImage() {
            if (this.previewImage && this.previewImage.startsWith('blob:')) {
                URL.revokeObjectURL(this.previewImage);
            }
            this.form.image = null;
            this.previewImage = null;
            this.imageStats = null;
            if (this.$refs.fileInput) this.$refs.fileInput.value = '';
        },
        formatBytes,
        submit() {
            this.form.processing = true;
            this.uploadProgress = 0;
            this.form.errors = {};

            const payload = {
                title:            this.form.title,
                category:         this.form.category,
                author:           this.form.author,
                excerpt:          this.form.excerpt,
                content:          this.form.content,
                meta_title:       this.form.meta_title,
                meta_description: this.form.meta_description,
                is_active:        this.form.is_active ? 1 : 0,
                published_at:     this.form.published_at || null,
                image:            this.form.image,
            };

            let url = route('admin.blogs.store');
            if (this.isEdit) {
                url = route('admin.blogs.update', this.blog.id);
                payload._method = 'put';
            }

            router.post(url, payload, {
                forceFormData: true,
                onProgress: (event) => {
                    if (event && event.total) {
                        this.uploadProgress = Math.round((event.loaded / event.total) * 100);
                    }
                },
                onError: (errors) => {
                    this.form.errors = errors;
                    this.form.processing = false;
                    this.uploadProgress = 0;
                },
                onFinish: () => {
                    this.form.processing = false;
                    setTimeout(() => { this.uploadProgress = 0; }, 800);
                },
            });
        },
    },
    beforeUnmount() {
        if (this.previewImage && this.previewImage.startsWith('blob:')) {
            URL.revokeObjectURL(this.previewImage);
        }
    },
};
</script>
