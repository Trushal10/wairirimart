<template>
    <Head title="Home videos" />

    <PageHeader
        title="Home videos"
        subtitle="Clips for the home page reel. They play one after another, in priority order."
        :crumbs="crumbs"
    >
        <template #actions>
            <Button variant="primary" size="sm" @click="openForm()">
                <template #leading><Plus :size="14" /></template>
                Add video
            </Button>
        </template>
    </PageHeader>

    <DataTable
        :columns="columns"
        :rows="videos"
        row-key="id"
        empty-text="No videos yet — add one and the home page shows a video reel. With none active, the section is hidden."
    >
        <template #cell-title="{ row }">
            <div class="flex items-center gap-3 min-w-0">
                <div class="relative h-16 w-10 shrink-0 overflow-hidden rounded-md border border-gray-200 dark:border-white/[0.06] bg-gray-900">
                    <img v-if="row.poster_src" :src="row.poster_src" :alt="row.title || 'Video poster'" class="h-full w-full object-cover" />
                    <video v-else :src="`${row.video_src}#t=0.5`" muted playsinline preload="metadata" class="h-full w-full object-cover"></video>
                    <span class="absolute inset-0 flex items-center justify-center bg-black/20 text-white">
                        <Play :size="14" class="drop-shadow" />
                    </span>
                </div>
                <div class="min-w-0">
                    <button
                        type="button"
                        @click="openForm(row)"
                        class="text-body-strong text-gray-900 dark:text-white/95 truncate hover:text-brand-600 dark:hover:text-brand-400 transition-colors block text-left"
                    >{{ row.title || 'Untitled video' }}</button>
                    <div class="mt-0.5 text-[11.5px] text-gray-500 dark:text-gray-400 truncate">
                        {{ row.subtitle || row.link_url || '—' }}
                    </div>
                </div>
            </div>
        </template>

        <template #cell-priority="{ row }">
            <span class="num-tabular">{{ row.priority }}</span>
        </template>

        <template #cell-status="{ row }">
            <Badge :variant="row.status ? 'success' : 'neutral'" dot>
                {{ row.status ? 'Active' : 'Hidden' }}
            </Badge>
        </template>

        <template #rowActions="{ row }">
            <div class="flex items-center justify-end gap-0.5">
                <Tooltip content="Edit">
                    <button
                        type="button"
                        @click="openForm(row)"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/[0.08] dark:hover:text-white/95 transition-colors focus-ring"
                        :aria-label="`Edit ${row.title || 'video'}`"
                    >
                        <Pencil :size="14" />
                    </button>
                </Tooltip>
                <Tooltip content="Delete">
                    <button
                        type="button"
                        @click="deleteId = row.id"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-500 hover:bg-error-50 hover:text-error-600 dark:text-gray-400 dark:hover:bg-error-500/10 dark:hover:text-error-400 transition-colors focus-ring"
                        :aria-label="`Delete ${row.title || 'video'}`"
                    >
                        <Trash2 :size="14" />
                    </button>
                </Tooltip>
            </div>
        </template>
    </DataTable>

    <Modal
        v-if="showForm"
        :model-value="true"
        :title="editing ? 'Edit video' : 'Add video'"
        subtitle="Vertical (9:16) clips of 10–30 seconds look best. They play muted."
        size="lg"
        @update:modelValue="v => !v && closeForm()"
    >
        <form @submit.prevent="submit" class="space-y-5">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <FormField label="Video" :required="!editing" hint="MP4 or WebM, up to 50 MB." :error="errors.video">
                    <label
                        for="home-video-file"
                        class="group flex aspect-[9/12] w-full cursor-pointer flex-col items-center justify-center overflow-hidden rounded-xl border-2 border-dashed border-gray-200 dark:border-white/[0.1] bg-gray-50/50 dark:bg-white/[0.02] transition-colors hover:border-gray-400 dark:hover:border-white/[0.2]"
                    >
                        <input id="home-video-file" ref="videoInput" type="file" accept="video/mp4,video/webm" class="sr-only" @change="onVideo" />
                        <video v-if="videoPreview" :src="videoPreview" muted autoplay loop playsinline class="h-full w-full object-cover"></video>
                        <div v-else class="flex flex-col items-center text-center p-4">
                            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400 mb-2">
                                <UploadCloud :size="18" />
                            </span>
                            <p class="text-body-strong text-gray-800 dark:text-white/90">Choose a video</p>
                            <p class="mt-1 text-caption">MP4 or WebM</p>
                        </div>
                    </label>
                </FormField>

                <FormField label="Poster image" hint="Optional. Shown before the clip loads." :error="errors.poster">
                    <label
                        for="home-video-poster"
                        class="group flex aspect-[9/12] w-full cursor-pointer flex-col items-center justify-center overflow-hidden rounded-xl border-2 border-dashed border-gray-200 dark:border-white/[0.1] bg-gray-50/50 dark:bg-white/[0.02] transition-colors hover:border-gray-400 dark:hover:border-white/[0.2]"
                    >
                        <input id="home-video-poster" ref="posterInput" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="onPoster" />
                        <img v-if="posterPreview" :src="posterPreview" alt="Poster preview" class="h-full w-full object-cover" />
                        <div v-else class="flex flex-col items-center text-center p-4">
                            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400 mb-2">
                                <ImageIcon :size="18" />
                            </span>
                            <p class="text-body-strong text-gray-800 dark:text-white/90">Choose an image</p>
                            <p class="mt-1 text-caption">JPG, PNG or WebP · 1 MB</p>
                        </div>
                    </label>
                    <button
                        v-if="posterPreview"
                        type="button"
                        @click="clearPoster"
                        class="mt-2 inline-flex items-center gap-1 text-[12px] font-medium text-error-600 dark:text-error-400 hover:underline focus-ring rounded"
                    >
                        <X :size="12" /> Remove poster
                    </button>
                </FormField>

                <FormField label="Title" hint="Optional. Shown on the card." :error="errors.title">
                    <Input v-model="form.title" maxlength="80" placeholder="e.g. Pouring a resin coaster" :error="!!errors.title" />
                </FormField>

                <FormField label="Subtitle" hint="Optional." :error="errors.subtitle">
                    <Input v-model="form.subtitle" maxlength="160" placeholder="e.g. Silicone coaster mould" :error="!!errors.subtitle" />
                </FormField>

                <FormField label="Shop link" hint="Optional. /product/slug or a full URL." :error="errors.link_url">
                    <Input v-model="form.link_url" placeholder="/shop" :error="!!errors.link_url" />
                </FormField>

                <FormField label="Priority" hint="Lower numbers play first." :error="errors.priority">
                    <Input v-model="form.priority" type="number" min="0" placeholder="0" :error="!!errors.priority" />
                </FormField>

                <div class="sm:col-span-2">
                    <Switch v-model="form.status">Show on the home page</Switch>
                </div>
            </div>
        </form>

        <template #footer>
            <Button variant="secondary" @click="closeForm">Cancel</Button>
            <Button variant="primary" :loading="processing" @click="submit">
                {{ editing ? 'Save changes' : 'Add video' }}
            </Button>
        </template>
    </Modal>

    <DeleteAlert
        v-if="deleteId"
        @confirmDelete="confirmDelete"
        @cancelDelete="deleteId = null"
        title="Delete video"
        message="Delete this video? The file is removed from the server."
    />
</template>

<script>
import { Head, router, usePage } from '@inertiajs/vue3';
import Layout from '@/Layout/MainLayout.vue';
import DeleteAlert from '@/Components/common/DeleteAlert.vue';
import { PageHeader, Button, Badge, DataTable, Tooltip, Modal, Input, FormField, Switch } from '@/Components/ui';
import { Plus, Pencil, Trash2, Play, UploadCloud, Image as ImageIcon, X } from '@lucide/vue';

const blankForm = () => ({ title: '', subtitle: '', link_url: '', priority: 0, status: true });

export default {
    layout: Layout,
    components: {
        Head, DeleteAlert,
        PageHeader, Button, Badge, DataTable, Tooltip, Modal, Input, FormField, Switch,
        Plus, Pencil, Trash2, Play, UploadCloud, ImageIcon, X,
    },
    props: {
        videos: { type: Array, default: () => [] },
    },
    data() {
        return {
            crumbs: [
                { label: 'Dashboard', href: route('admin.dashboard') },
                { label: 'Home videos' },
            ],
            columns: [
                { key: 'title', label: 'Video' },
                { key: 'priority', label: 'Priority', headerClass: 'w-24' },
                { key: 'status', label: 'Status', headerClass: 'w-28' },
            ],
            showForm: false,
            editing: null,
            form: blankForm(),
            videoFile: null,
            posterFile: null,
            removePoster: false,
            videoPreview: null,
            posterPreview: null,
            processing: false,
            deleteId: null,
        };
    },
    computed: {
        errors() { return usePage().props.errors || {}; },
    },
    methods: {
        openForm(row = null) {
            this.editing = row;
            this.form = row
                ? { title: row.title || '', subtitle: row.subtitle || '', link_url: row.link_url || '', priority: row.priority ?? 0, status: !!row.status }
                : blankForm();
            this.videoFile = null;
            this.posterFile = null;
            this.removePoster = false;
            this.videoPreview = row?.video_src || null;
            this.posterPreview = row?.poster_src || null;
            this.showForm = true;
        },
        closeForm() {
            this.showForm = false;
            this.editing = null;
        },
        onVideo(e) {
            const file = e.target.files[0];
            if (!file) return;
            this.videoFile = file;
            this.videoPreview = URL.createObjectURL(file);
        },
        onPoster(e) {
            const file = e.target.files[0];
            if (!file) return;
            this.posterFile = file;
            this.removePoster = false;
            this.posterPreview = URL.createObjectURL(file);
        },
        clearPoster() {
            this.posterFile = null;
            this.posterPreview = null;
            this.removePoster = true;
            if (this.$refs.posterInput) this.$refs.posterInput.value = '';
        },
        submit() {
            const payload = { ...this.form, status: this.form.status ? 1 : 0 };
            if (this.videoFile) payload.video = this.videoFile;
            if (this.posterFile) payload.poster = this.posterFile;
            if (this.removePoster) payload.remove_poster = 1;

            let url = route('admin.home-videos.store');
            if (this.editing) {
                payload._method = 'put';
                url = route('admin.home-videos.update', this.editing.id);
            }

            this.processing = true;
            router.post(url, payload, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => this.closeForm(),
                onFinish: () => { this.processing = false; },
            });
        },
        confirmDelete() {
            router.delete(route('admin.home-videos.delete', this.deleteId), { preserveScroll: true });
            this.deleteId = null;
        },
    },
};
</script>
