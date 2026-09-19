<template>
    <Head title="Categories" />

    <PageHeader
        title="Categories"
        subtitle="Organize your catalog into shoppable groups."
        :crumbs="crumbs"
    >
        <template #actions>
            <Button variant="primary" size="sm" tag="a" :href="route('admin.category.create')">
                <template #leading><Plus :size="14" /></template>
                New category
            </Button>
        </template>
    </PageHeader>

    <DataTable
        :columns="columns"
        :rows="categories.data"
        row-key="id"
        :filter-chips="chips"
        :empty-text="filters.search ? 'No categories match your search.' : 'No categories yet — create your first one.'"
        @remove-filter="onRemoveChip"
        @clear-filters="clearFilters"
    >
        <template #toolbar>
            <SearchInput
                v-model="search"
                placeholder="Search categories…"
                class="max-w-xs"
            />
            <span class="text-[12px] text-gray-500 dark:text-gray-400">
                <span class="num-tabular font-semibold text-gray-800 dark:text-white/90">{{ categories.total ?? categories.data.length }}</span>
                {{ (categories.total ?? categories.data.length) === 1 ? 'category' : 'categories' }}
            </span>
        </template>

        <template #cell-name="{ row }">
            <div class="flex items-center gap-3 min-w-0">
                <div class="h-10 w-10 shrink-0 overflow-hidden rounded-lg border border-gray-200 dark:border-white/[0.06] bg-gradient-to-br from-gray-50 to-gray-100 dark:from-white/[0.04] dark:to-white/[0.02]">
                    <img
                        v-if="row.image"
                        :src="`/storage/category/${row.image}`"
                        :alt="row.name"
                        class="h-full w-full object-cover"
                        @error="onImgError"
                    />
                    <div v-else class="h-full w-full flex items-center justify-center text-gray-400">
                        <ImageIcon :size="16" />
                    </div>
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <Link
                            :href="route('admin.category.edit', row.id)"
                            class="text-body-strong text-gray-900 dark:text-white/95 truncate hover:text-brand-600 dark:hover:text-brand-400 transition-colors"
                        >{{ row.name }}</Link>
                        <Badge v-if="row.featured" variant="brand" size="sm">Featured</Badge>
                    </div>
                    <div class="mt-0.5 text-[11.5px] text-gray-500 dark:text-gray-400 truncate font-mono">
                        /{{ row.slug }}
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
                        :href="route('admin.category.edit', row.id)"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/[0.08] dark:hover:text-white/95 transition-colors focus-ring"
                        :aria-label="`Edit ${row.name}`"
                    >
                        <Pencil :size="14" />
                    </Link>
                </Tooltip>
                <Tooltip content="Delete">
                    <button
                        @click.prevent="deleteCategory(row.id)"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-500 hover:bg-error-50 hover:text-error-600 dark:text-gray-400 dark:hover:bg-error-500/10 dark:hover:text-error-400 transition-colors focus-ring"
                        :aria-label="`Delete ${row.name}`"
                        type="button"
                    >
                        <Trash2 :size="14" />
                    </button>
                </Tooltip>
            </div>
        </template>

        <template #footer>
            <Pagination :links="categories.links" />
        </template>
    </DataTable>

    <DeleteAlert
        v-if="isDeleted"
        @confirmDelete="deleteCategoryConfirmed"
        @cancelDelete="cancelDelete"
        title="Delete category"
        message="Delete this category? Product links will be removed. This can't be undone."
    />
</template>

<script>
import Layout from '@/Layout/MainLayout.vue';
import { Link, Head, router } from '@inertiajs/vue3';
import DeleteAlert from '@/Components/common/DeleteAlert.vue';
import Pagination from '@/Components/common/Pagination.vue';
import { PageHeader, Button, Badge, DataTable, SearchInput, Tooltip } from '@/Components/ui';
import { Plus, Pencil, Trash2, Image as ImageIcon } from '@lucide/vue';

export default {
    layout: Layout,
    components: {
        Link, Head, DeleteAlert, Pagination,
        PageHeader, Button, Badge, DataTable, SearchInput, Tooltip,
        Plus, Pencil, Trash2, ImageIcon,
    },
    props: {
        categories: { type: Object, default: () => ({ data: [], links: [] }) },
        filters: { type: Object, default: () => ({}) },
    },
    data() {
        return {
            crumbs: [
                { label: 'Dashboard', href: route('admin.dashboard') },
                { label: 'Categories' },
            ],
            columns: [
                { key: 'name', label: 'Category' },
                { key: 'status', label: 'Status', headerClass: 'w-28' },
            ],
            search: this.filters?.search || '',
            isDeleted: false,
            deleteId: null,
            isDeleting: false,
            searchTimeOut: null,
        };
    },
    computed: {
        chips() {
            return this.search ? [{ key: 'search', label: `“${this.search}”` }] : [];
        },
    },
    watch: {
        search(value) {
            clearTimeout(this.searchTimeOut);
            this.searchTimeOut = setTimeout(() => {
                router.get(route('admin.category'), { search: value }, {
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
        deleteCategory(id) {
            this.isDeleted = true;
            this.deleteId = id;
        },
        deleteCategoryConfirmed() {
            if (! this.deleteId || this.isDeleting) return;
            this.isDeleting = true;
            router.delete(route('admin.category.delete', this.deleteId), {
                preserveScroll: true,
                // Ensure the list is re-fetched after the redirect back so a
                // deleted row can't linger on screen due to a stale prop cache.
                preserveState: false,
                onFinish: () => {
                    this.isDeleting = false;
                    this.isDeleted = false;
                    this.deleteId = null;
                },
            });
        },
        cancelDelete() {
            this.isDeleted = false;
            this.deleteId = null;
        },
        onImgError(e) { e.target.style.display = 'none'; },
    },
};
</script>
