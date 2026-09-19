<template>
    <Head title="Blog posts" />

    <PageHeader
        title="Blog posts"
        subtitle="Write and manage articles shown on the storefront blog."
        :crumbs="crumbs"
    >
        <template #actions>
            <Button variant="primary" size="sm" tag="a" :href="route('admin.blogs.create')">
                <template #leading><Plus :size="14" /></template>
                New post
            </Button>
        </template>
    </PageHeader>

    <DataTable
        :columns="columns"
        :rows="blogs.data"
        row-key="id"
        :filter-chips="chips"
        :empty-text="hasFilters ? 'No posts match your filters.' : 'No posts yet — write your first article.'"
        @remove-filter="onRemoveChip"
        @clear-filters="clearFilters"
    >
        <template #toolbar>
            <div class="flex flex-wrap items-center gap-2 flex-1">
                <SearchInput v-model="search" placeholder="Search by title, category, author…" class="max-w-xs" />
                <Select v-model="status" :options="statusOptions" class="max-w-[180px]" />
            </div>
            <span class="text-[12px] text-gray-500 dark:text-gray-400">
                <span class="num-tabular font-semibold text-gray-800 dark:text-white/90">{{ blogs.total ?? blogs.data.length }}</span>
                {{ (blogs.total ?? blogs.data.length) === 1 ? 'post' : 'posts' }}
            </span>
        </template>

        <template #cell-title="{ row }">
            <div class="flex items-center gap-3 min-w-0">
                <div v-if="row.image" class="h-10 w-10 shrink-0 overflow-hidden rounded-md border border-gray-200 dark:border-white/[0.06] bg-gray-50">
                    <img :src="`/storage/blog/${row.image}`" :alt="row.title" class="h-full w-full object-cover" />
                </div>
                <div v-else class="h-10 w-10 shrink-0 flex items-center justify-center rounded-md border border-gray-200 dark:border-white/[0.06] bg-gray-50 dark:bg-white/[0.03] text-gray-400">
                    <BookOpen :size="14" />
                </div>
                <div class="min-w-0">
                    <div class="text-body-strong text-gray-900 dark:text-white/95 truncate">{{ row.title }}</div>
                    <div class="text-[11.5px] text-gray-500 dark:text-gray-400 truncate">/{{ row.slug }}</div>
                </div>
            </div>
        </template>

        <template #cell-category="{ row }">
            <span v-if="row.category" class="inline-flex items-center rounded-md bg-gray-100 dark:bg-white/[0.06] px-2 py-0.5 text-[12px] text-gray-700 dark:text-gray-300">
                {{ row.category }}
            </span>
            <span v-else class="text-[12px] text-gray-400 dark:text-gray-500 italic">Uncategorised</span>
        </template>

        <template #cell-author="{ row }">
            <span class="text-[13px] text-gray-700 dark:text-gray-300">{{ row.author || '—' }}</span>
        </template>

        <template #cell-published_at="{ row }">
            <span v-if="row.published_at" class="text-[13px] text-gray-700 dark:text-gray-300 num-tabular">
                {{ formatDate(row.published_at) }}
            </span>
            <span v-else class="text-[12px] text-gray-400 dark:text-gray-500 italic">Not scheduled</span>
        </template>

        <template #cell-is_active="{ row }">
            <Badge :variant="row.is_active ? 'success' : 'neutral'" dot>
                {{ row.is_active ? 'Published' : 'Draft' }}
            </Badge>
        </template>

        <template #rowActions="{ row }">
            <div class="flex items-center justify-end gap-0.5">
                <Tooltip :content="row.is_active ? 'Unpublish' : 'Publish'">
                    <button
                        type="button"
                        @click="toggle(row)"
                        :class="[
                            'inline-flex h-8 w-8 items-center justify-center rounded-md transition-colors focus-ring',
                            row.is_active
                                ? 'text-gray-500 hover:text-warning-700 hover:bg-warning-50 dark:text-gray-400 dark:hover:text-warning-400 dark:hover:bg-warning-500/10'
                                : 'text-gray-500 hover:text-success-700 hover:bg-success-50 dark:text-gray-400 dark:hover:text-success-400 dark:hover:bg-success-500/10',
                        ]"
                        :aria-label="row.is_active ? `Unpublish ${row.title}` : `Publish ${row.title}`"
                    >
                        <PowerOff v-if="row.is_active" :size="14" />
                        <Power v-else :size="14" />
                    </button>
                </Tooltip>
                <Tooltip content="Edit">
                    <Link
                        :href="route('admin.blogs.edit', row.id)"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/[0.08] dark:hover:text-white/95 transition-colors focus-ring"
                        :aria-label="`Edit ${row.title}`"
                    >
                        <Pencil :size="14" />
                    </Link>
                </Tooltip>
                <Tooltip content="Delete">
                    <button
                        type="button"
                        @click.prevent="askDelete(row)"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-500 hover:bg-error-50 hover:text-error-600 dark:text-gray-400 dark:hover:bg-error-500/10 dark:hover:text-error-400 transition-colors focus-ring"
                        :aria-label="`Delete ${row.title}`"
                    >
                        <Trash2 :size="14" />
                    </button>
                </Tooltip>
            </div>
        </template>

        <template #footer>
            <Pagination :links="blogs.links" />
        </template>
    </DataTable>

    <DeleteAlert
        v-if="pendingDelete"
        @confirmDelete="confirmDelete"
        @cancelDelete="cancelDelete"
        title="Delete blog post"
        :message="`Delete post “${pendingDelete.title}”? This cannot be undone.`"
    />
</template>

<script>
import { Head, Link, router } from '@inertiajs/vue3';
import MainLayout from '../../../Layout/MainLayout.vue';
import Pagination from '@/Components/common/Pagination.vue';
import DeleteAlert from '@/Components/common/DeleteAlert.vue';
import { PageHeader, Button, Badge, DataTable, SearchInput, Select, Tooltip } from '@/Components/ui';
import { Plus, Pencil, Trash2, Power, PowerOff, BookOpen } from '@lucide/vue';

export default {
    layout: MainLayout,
    components: {
        Head, Link, Pagination, DeleteAlert,
        PageHeader, Button, Badge, DataTable, SearchInput, Select, Tooltip,
        Plus, Pencil, Trash2, Power, PowerOff, BookOpen,
    },
    props: {
        blogs: Object,
        filters: Object,
    },
    data() {
        return {
            crumbs: [
                { label: 'Dashboard', href: route('admin.dashboard') },
                { label: 'Blog posts' },
            ],
            columns: [
                { key: 'title', label: 'Post' },
                { key: 'category', label: 'Category', headerClass: 'w-40' },
                { key: 'author', label: 'Author', headerClass: 'w-40' },
                { key: 'published_at', label: 'Publish date', headerClass: 'w-40' },
                { key: 'is_active', label: 'Status', headerClass: 'w-28' },
            ],
            statusOptions: [
                { value: '', label: 'All statuses' },
                { value: 'active', label: 'Published' },
                { value: 'inactive', label: 'Draft' },
            ],
            search: this.filters?.search || '',
            status: this.filters?.status || '',
            searchTimer: null,
            pendingDelete: null,
        };
    },
    computed: {
        hasFilters() { return !!(this.search || this.status); },
        chips() {
            const list = [];
            if (this.search) list.push({ key: 'search', label: `“${this.search}”` });
            if (this.status) {
                const opt = this.statusOptions.find((o) => o.value === this.status);
                if (opt) list.push({ key: 'status', label: opt.label });
            }
            return list;
        },
    },
    watch: {
        search() { this.debounceFilter(); },
        status() { this.applyFilters(); },
    },
    beforeUnmount() {
        if (this.searchTimer) clearTimeout(this.searchTimer);
    },
    methods: {
        debounceFilter() {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => this.applyFilters(), 400);
        },
        applyFilters() {
            router.get(route('admin.blogs.index'), {
                search: this.search || undefined,
                status: this.status || undefined,
            }, { preserveState: true, preserveScroll: true, replace: true });
        },
        clearFilters() {
            this.search = '';
            this.status = '';
        },
        onRemoveChip(key) {
            if (key === 'search') this.search = '';
            if (key === 'status') this.status = '';
        },
        formatDate(d) {
            return d
                ? new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
                : '—';
        },
        toggle(b) {
            router.post(route('admin.blogs.toggle', b.id), {}, { preserveScroll: true });
        },
        askDelete(b) { this.pendingDelete = b; },
        confirmDelete() {
            if (this.pendingDelete) {
                router.delete(route('admin.blogs.delete', this.pendingDelete.id), { preserveScroll: true });
            }
            this.pendingDelete = null;
        },
        cancelDelete() { this.pendingDelete = null; },
    },
};
</script>
