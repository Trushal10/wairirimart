<template>
    <Head :title="isEdit ? 'Edit slider' : 'New slider'" />

    <PageHeader
        :title="isEdit ? slider.title : 'New slider'"
        :subtitle="isEdit ? 'Update slider details and manage media items.' : 'Create the slider first, then add images or videos.'"
        :crumbs="crumbs"
    >
        <template #titleBadge>
            <Badge v-if="isEdit" :variant="form.status ? 'success' : 'neutral'" size="sm" dot>
                {{ form.status ? 'Active' : 'Draft' }}
            </Badge>
        </template>
        <template #actions>
            <Button variant="secondary" size="sm" tag="a" :href="route('admin.sliders')">
                <template #leading><ArrowLeft :size="14" /></template>
                Back
            </Button>
            <Button variant="primary" size="sm" :loading="form.processing" @click="submit">
                {{ isEdit ? 'Save changes' : 'Create slider' }}
            </Button>
        </template>
    </PageHeader>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <!-- Main -->
        <div class="xl:col-span-2 space-y-6">
            <Card mode="flat" title="Details" subtitle="Title, description, and status.">
                <form @submit.prevent="submit" class="space-y-5">
                    <FormField label="Title" required :error="errors?.title">
                        <Input v-model="form.title" placeholder="e.g. Summer sale 2026" :error="!!errors?.title" />
                    </FormField>
                    <FormField label="Description" required :error="errors?.description">
                        <Input v-model="form.description" placeholder="Short subtitle shown under the title" :error="!!errors?.description" />
                    </FormField>
                    <FormField label="Status">
                        <Switch v-model="form.status">
                            <span class="font-medium text-gray-900 dark:text-white/95">
                                {{ form.status ? 'Active' : 'Inactive' }}
                            </span>
                            <span class="block text-[12px] text-gray-500 dark:text-gray-400">
                                Only active sliders are shown on the storefront.
                            </span>
                        </Switch>
                    </FormField>
                </form>
            </Card>

            <Card v-if="isEdit" mode="flat" :title="`Media items · ${slider.slider_medias?.length || 0}`" subtitle="Shown in priority order on the homepage.">
                <template #actions>
                    <Button variant="primary" size="sm" @click="isOpen = true">
                        <template #leading><Plus :size="14" /></template>
                        Add media
                    </Button>
                </template>

                <div v-if="slider?.slider_medias?.length" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="media in slider.slider_medias"
                        :key="media.id"
                        class="group relative overflow-hidden rounded-xl border border-gray-200 dark:border-white/[0.06] bg-white dark:bg-[color:var(--color-surface-dark)] transition-shadow hover:shadow-elevation-2"
                    >
                        <div class="aspect-video w-full bg-gray-100 dark:bg-white/[0.03]">
                            <img
                                v-if="media.type === 'image'"
                                :src="`/storage/slider/${media.url}`"
                                :alt="media.caption || 'Slider media'"
                                class="h-full w-full object-cover"
                            />
                            <iframe
                                v-else
                                :src="media.url"
                                title="Slider video"
                                frameborder="0"
                                allowfullscreen
                                class="h-full w-full"
                            ></iframe>
                        </div>

                        <div class="p-3">
                            <div class="flex items-center gap-2 mb-1">
                                <Badge :variant="media.type === 'video' ? 'info' : 'neutral'" size="sm">
                                    <component :is="media.type === 'video' ? Play : ImageIcon" :size="10" class="mr-0.5" />
                                    {{ media.type }}
                                </Badge>
                                <span class="text-[11.5px] text-gray-500 dark:text-gray-400 num-tabular">
                                    Priority · {{ media.priority ?? '—' }}
                                </span>
                            </div>
                            <p v-if="media.caption" class="text-[12.5px] text-gray-700 dark:text-gray-300 truncate">
                                {{ media.caption }}
                            </p>
                        </div>

                        <div class="absolute top-2 right-2 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button
                                type="button"
                                @click="editMedia(media)"
                                class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-white/95 dark:bg-gray-900/85 text-gray-700 dark:text-gray-200 hover:bg-white dark:hover:bg-gray-900 shadow-theme-sm backdrop-blur-sm focus-ring"
                                title="Edit media"
                            >
                                <Pencil :size="12" />
                            </button>
                            <button
                                type="button"
                                @click.prevent="deleteMedia(media)"
                                class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-white/95 dark:bg-gray-900/85 text-error-600 dark:text-error-400 hover:bg-error-50 dark:hover:bg-error-500/15 shadow-theme-sm backdrop-blur-sm focus-ring"
                                title="Delete media"
                            >
                                <Trash2 :size="12" />
                            </button>
                        </div>
                    </div>
                </div>

                <EmptyState
                    v-else
                    size="sm"
                    title="No media yet"
                    description="Add images or a YouTube video to bring this slider to life."
                >
                    <template #actions>
                        <Button variant="primary" size="sm" @click="isOpen = true">
                            <template #leading><Plus :size="14" /></template>
                            Add first media
                        </Button>
                    </template>
                </EmptyState>
            </Card>
        </div>

        <!-- Sidebar -->
        <aside class="space-y-6 xl:col-span-1">
            <Card mode="flat" title="Tips">
                <ul class="space-y-3 text-[13px] text-gray-600 dark:text-gray-400">
                    <li class="flex gap-2">
                        <Check :size="14" class="mt-0.5 shrink-0 text-success-600 dark:text-success-400" />
                        <span>Create the slider first, then upload media items below.</span>
                    </li>
                    <li class="flex gap-2">
                        <Check :size="14" class="mt-0.5 shrink-0 text-success-600 dark:text-success-400" />
                        <span>Landscape images (~1920×720) work best. Videos accept YouTube embed URLs.</span>
                    </li>
                    <li class="flex gap-2">
                        <Check :size="14" class="mt-0.5 shrink-0 text-success-600 dark:text-success-400" />
                        <span>Priority controls the order media appear in the slider.</span>
                    </li>
                </ul>
            </Card>
        </aside>
    </div>

    <MediaCreate
        v-if="isOpen"
        @close="() => { isOpen = false; sliderMedia = {}; }"
        :sliderMedia="sliderMedia"
        :sliderId="slider?.id"
    />
    <DeleteAlert
        v-if="isDeleted"
        @confirmDelete="deleteMediaConfirmed"
        @cancelDelete="cancelDelete"
        title="Delete media"
        message="Delete this media item?"
    />
</template>

<script>
import { Head, router, Link, usePage } from '@inertiajs/vue3';
import Layout from '@/Layout/MainLayout.vue';
import MediaCreate from './MediaCreate.vue';
import DeleteAlert from '@/Components/common/DeleteAlert.vue';
import { PageHeader, Card, Button, Input, FormField, Switch, Badge, EmptyState } from '@/Components/ui';
import { Plus, Pencil, Trash2, ArrowLeft, Play, Image as ImageIcon, Check } from '@lucide/vue';

export default {
    layout: Layout,
    components: {
        Head, Link, MediaCreate, DeleteAlert,
        PageHeader, Card, Button, Input, FormField, Switch, Badge, EmptyState,
        Plus, Pencil, Trash2, ArrowLeft, Play, ImageIcon, Check,
    },
    props: { slider: Object },
    setup() {
        return { Play, ImageIcon };
    },
    data() {
        return {
            form: {
                title: this.slider?.title || '',
                description: this.slider?.description || '',
                status: this.slider?.status ?? true,
                processing: false,
                errors: {},
            },
            sliderMedia: null,
            isOpen: false,
            isDeleted: false,
            deleteId: null,
        };
    },
    computed: {
        errors() { return usePage().props.errors || {}; },
        isEdit() { return !!this.slider?.id; },
        crumbs() {
            return [
                { label: 'Dashboard', href: route('admin.dashboard') },
                { label: 'Sliders', href: route('admin.sliders') },
                { label: this.isEdit ? 'Edit' : 'New' },
            ];
        },
    },
    methods: {
        editMedia(media) {
            this.sliderMedia = media;
            this.isOpen = true;
        },
        deleteMedia(media) {
            this.isDeleted = true;
            this.deleteId = media;
        },
        deleteMediaConfirmed() {
            if (this.deleteId) {
                router.delete(route('admin.slider.media.delete', [this.slider, this.deleteId]), {
                    preserveScroll: true,
                });
            }
            this.isDeleted = false;
            this.deleteId = null;
        },
        cancelDelete() {
            this.isDeleted = false;
            this.deleteId = null;
        },
        submit() {
            let url = route('admin.slider.save');
            const payload = { ...this.form };
            if (this.isEdit) {
                url = route('admin.slider.update', this.slider.id);
                payload._method = 'put';
            }
            payload.status = this.form.status ? 1 : 0;

            this.form.processing = true;
            this.form.errors = {};

            router.post(url, payload, {
                onError: (errors) => { this.form.errors = errors; },
                onFinish: () => { this.form.processing = false; },
            });
        },
    },
};
</script>
