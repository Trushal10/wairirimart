<template>
    <Head title="Sliders" />

    <PageHeader
        title="Sliders"
        subtitle="Homepage banner rotators with images or videos."
        :crumbs="crumbs"
    >
        <template #actions>
            <Button variant="primary" size="sm" tag="a" :href="route('admin.slider.create')">
                <template #leading><Plus :size="14" /></template>
                New slider
            </Button>
        </template>
    </PageHeader>

    <DataTable
        :columns="columns"
        :rows="sliders.data"
        row-key="id"
        :filter-chips="chips"
        :empty-text="filters.search ? 'No sliders match your search.' : 'No sliders yet — create your first one.'"
        @remove-filter="onRemoveChip"
        @clear-filters="clearFilters"
    >
        <template #toolbar>
            <SearchInput v-model="search" placeholder="Search sliders…" class="max-w-xs" />
            <span class="text-[12px] text-gray-500 dark:text-gray-400">
                <span class="num-tabular font-semibold text-gray-800 dark:text-white/90">{{ sliders.total ?? sliders.data.length }}</span>
                {{ (sliders.total ?? sliders.data.length) === 1 ? 'slider' : 'sliders' }}
            </span>
        </template>

        <template #cell-title="{ row }">
            <div class="flex items-center gap-3 min-w-0">
                <div class="relative h-12 w-20 shrink-0 overflow-hidden rounded-lg border border-gray-200 dark:border-white/[0.06] bg-gradient-to-br from-gray-50 to-gray-100 dark:from-white/[0.04] dark:to-white/[0.02]">
                    <img
                        v-if="row.slider_medias?.[0]?.type === 'image'"
                        :src="`/storage/slider/${row.slider_medias[0]?.url}`"
                        :alt="row.title"
                        class="h-full w-full object-cover"
                    />
                    <template v-else-if="row.slider_medias?.[0]?.type === 'video'">
                        <img
                            :src="getVideoThumbnail(row.slider_medias[0]?.url)"
                            :alt="`${row.title} video thumbnail`"
                            class="h-full w-full object-cover"
                        />
                        <span class="absolute inset-0 flex items-center justify-center bg-black/25 text-white">
                            <Play :size="18" class="drop-shadow" />
                        </span>
                    </template>
                    <div v-else class="h-full w-full flex items-center justify-center text-gray-400">
                        <ImageIcon :size="16" />
                    </div>
                </div>
                <div class="min-w-0">
                    <Link
                        :href="route('admin.slider.edit', row.id)"
                        class="text-body-strong text-gray-900 dark:text-white/95 truncate hover:text-brand-600 dark:hover:text-brand-400 transition-colors block"
                    >{{ row.title }}</Link>
                    <div class="mt-0.5 text-[11.5px] text-gray-500 dark:text-gray-400">
                        {{ row.slider_medias?.length || 0 }} media item{{ row.slider_medias?.length === 1 ? '' : 's' }}
                    </div>
                </div>
            </div>
        </template>

        <template #cell-status="{ row }">
            <Badge :variant="row.status ? 'success' : 'neutral'" dot>
                {{ row.status ? 'Active' : 'Inactive' }}
            </Badge>
        </template>

        <template #rowActions="{ row }">
            <div class="flex items-center justify-end gap-0.5">
                <Tooltip content="Edit">
                    <Link
                        :href="route('admin.slider.edit', row.id)"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/[0.08] dark:hover:text-white/95 transition-colors focus-ring"
                        :aria-label="`Edit ${row.title}`"
                    >
                        <Pencil :size="14" />
                    </Link>
                </Tooltip>
                <Tooltip content="Delete">
                    <button
                        type="button"
                        @click.prevent="deleteSlider(row.id)"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-500 hover:bg-error-50 hover:text-error-600 dark:text-gray-400 dark:hover:bg-error-500/10 dark:hover:text-error-400 transition-colors focus-ring"
                        :aria-label="`Delete ${row.title}`"
                    >
                        <Trash2 :size="14" />
                    </button>
                </Tooltip>
            </div>
        </template>

        <template #footer>
            <Pagination :links="sliders.links" />
        </template>
    </DataTable>

    <DeleteAlert
        v-if="isDeleted"
        @confirmDelete="deleteSliderConfirmed"
        @cancelDelete="cancelDelete"
        title="Delete slider"
        message="Delete this slider? All its media items will be removed."
    />
</template>

<script>
import { Head, Link, router } from '@inertiajs/vue3';
import Layout from '@/Layout/MainLayout.vue';
import Pagination from '@/Components/common/Pagination.vue';
import DeleteAlert from '@/Components/common/DeleteAlert.vue';
import Common from '@/Components/Composables/Common';
import { PageHeader, Button, Badge, DataTable, SearchInput, Tooltip } from '@/Components/ui';
import { Plus, Pencil, Trash2, Image as ImageIcon, Play } from '@lucide/vue';

export default {
    layout: Layout,
    components: {
        Link, Head, Pagination, DeleteAlert,
        PageHeader, Button, Badge, DataTable, SearchInput, Tooltip,
        Plus, Pencil, Trash2, ImageIcon, Play,
    },
    props: {
        sliders: { type: Object, default: () => ({ data: [], links: [] }) },
        filters: { type: Object, default: () => ({}) },
    },
    data() {
        return {
            crumbs: [
                { label: 'Dashboard', href: route('admin.dashboard') },
                { label: 'Sliders' },
            ],
            columns: [
                { key: 'title', label: 'Slider' },
                { key: 'status', label: 'Status', headerClass: 'w-28' },
            ],
            search: this.filters?.search || '',
            isDeleted: false,
            deleteId: null,
            searchTimeOut: null,
        };
    },
    computed: {
        chips() {
            return this.search ? [{ key: 'search', label: `“${this.search}”` }] : [];
        },
    },
    created() {
        const { getVideoThumbnail } = Common();
        this.getVideoThumbnail = getVideoThumbnail;
    },
    watch: {
        search(value) {
            clearTimeout(this.searchTimeOut);
            this.searchTimeOut = setTimeout(() => {
                router.get(route('admin.sliders'), { search: value }, {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                });
            }, 400);
        },
    },
    methods: {
        clearFilters() { this.search = ''; },
        onRemoveChip(key) { if (key === 'search') this.search = ''; },
        deleteSlider(id) {
            this.isDeleted = true;
            this.deleteId = id;
        },
        deleteSliderConfirmed() {
            if (this.deleteId) {
                router.delete(route('admin.slider.delete', this.deleteId), { preserveScroll: true });
            }
            this.isDeleted = false;
            this.deleteId = null;
        },
        cancelDelete() {
            this.isDeleted = false;
            this.deleteId = null;
        },
    },
};
</script>
