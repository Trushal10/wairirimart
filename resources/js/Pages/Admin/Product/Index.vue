<template>
    <Head title="Products" />

    <PageHeader
        title="Products"
        subtitle="Manage catalog inventory, pricing, and visibility."
        :crumbs="crumbs"
    >
        <template #actions>
            <Button variant="secondary" size="sm" tag="a" :href="route('admin.reports.orders')">
                <template #leading><Download :size="14" /></template>
                Export
            </Button>
            <Button variant="primary" size="sm" tag="a" :href="route('admin.products.create')">
                <template #leading><Plus :size="14" /></template>
                New product
            </Button>
        </template>
    </PageHeader>

    <DataTable
        :columns="columns"
        :rows="products.data"
        row-key="id"
        :filter-chips="chips"
        :empty-text="hasFilters ? 'No products match your filters.' : 'No products yet — create your first one.'"
        @remove-filter="onRemoveChip"
        @clear-filters="clearFilters"
    >
        <template #toolbar>
            <div class="flex flex-wrap items-center gap-2 flex-1">
                <SearchInput v-model="search" placeholder="Search by name, SKU or slug…" class="max-w-xs" />
                <Select
                    v-model="status"
                    :options="statusOptions"
                    class="max-w-[160px]"
                />
            </div>
            <span class="text-[12px] text-gray-500 dark:text-gray-400">
                <span class="num-tabular font-semibold text-gray-800 dark:text-white/90">{{ products.total ?? products.data.length }}</span>
                {{ (products.total ?? products.data.length) === 1 ? 'product' : 'products' }}
            </span>
        </template>

        <template #bulkActions="{ selected, clear }">
            <Button variant="ghost" size="sm" class="text-white/90 hover:text-white hover:bg-white/10" @click="clear">
                Cancel
            </Button>
            <Button variant="danger" size="sm" @click="bulkDelete(selected)">
                <template #leading><Trash2 :size="14" /></template>
                Delete {{ selected.length }}
            </Button>
        </template>

        <template #cell-name="{ row }">
            <div class="flex items-center gap-3 min-w-0">
                <span class="relative flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-gray-200 dark:border-white/[0.06] bg-gradient-to-br from-gray-50 to-gray-100 dark:from-white/[0.04] dark:to-white/[0.02] text-gray-400 dark:text-gray-600">
                    <Package :size="18" />
                </span>
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <Link
                            :href="route('admin.product.edit', row.id)"
                            class="text-body-strong text-gray-900 dark:text-white/95 truncate hover:text-brand-600 dark:hover:text-brand-400 transition-colors"
                        >
                            {{ row.name }}
                        </Link>
                        <Badge v-if="row.featured" variant="brand" size="sm">Featured</Badge>
                    </div>
                    <div class="mt-0.5 flex items-center gap-2 text-[11.5px] text-gray-500 dark:text-gray-400">
                        <span v-if="row.sku" class="inline-flex items-center gap-1 num-tabular">
                            <Hash :size="10" />{{ row.sku }}
                        </span>
                        <span v-else class="italic">no SKU</span>
                        <span v-if="row.has_variants && row.variants_count" class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-300">
                            <span class="text-gray-300 dark:text-gray-600">·</span>
                            <Layers :size="10" />
                            {{ row.variants_count }} {{ row.variants_count === 1 ? 'variant' : 'variants' }}
                        </span>
                    </div>
                </div>
            </div>
        </template>

        <template #cell-price="{ row }">
            <div class="num-tabular">
                <div class="text-body-strong text-gray-900 dark:text-white/95">₹{{ formatPrice(row.price) }}</div>
                <div v-if="row.compere_price && Number(row.compere_price) > Number(row.price)" class="text-[11.5px] text-gray-400 line-through">
                    ₹{{ formatPrice(row.compere_price) }}
                </div>
            </div>
        </template>

        <template #cell-stock="{ row }">
            <Badge :variant="stockVariant(effectiveStock(row))" dot>
                {{ effectiveStock(row) === 0 ? 'Out of stock' : `${effectiveStock(row)} in stock` }}
            </Badge>
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
                        :href="route('admin.product.edit', row.id)"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/[0.08] dark:hover:text-white/95 transition-colors focus-ring"
                        :aria-label="`Edit ${row.name}`"
                    >
                        <Pencil :size="14" />
                    </Link>
                </Tooltip>
                <Tooltip content="Delete">
                    <button
                        type="button"
                        @click.prevent="deleteProduct(row.id)"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-500 hover:bg-error-50 hover:text-error-600 dark:text-gray-400 dark:hover:bg-error-500/10 dark:hover:text-error-400 transition-colors focus-ring"
                        :aria-label="`Delete ${row.name}`"
                    >
                        <Trash2 :size="14" />
                    </button>
                </Tooltip>
            </div>
        </template>

        <template #footer>
            <Pagination :links="products.links" />
        </template>
    </DataTable>

    <DeleteAlert
        v-if="isDeleted"
        @confirmDelete="deleteProductConfirmed"
        @cancelDelete="cancelDelete"
        title="Delete product"
        message="Are you sure you want to delete this product? Variants, gallery items, and category links will also be removed."
    />
</template>

<script>
import Layout from '@/Layout/MainLayout.vue';
import { Link, Head, router } from '@inertiajs/vue3';
import Pagination from '@/Components/common/Pagination.vue';
import DeleteAlert from '@/Components/common/DeleteAlert.vue';
import {
    PageHeader, Button, Badge, DataTable, SearchInput, Select, Tooltip,
} from '@/Components/ui';
import { Plus, Download, Pencil, Trash2, Package, Layers, Hash } from '@lucide/vue';

export default {
    layout: Layout,
    components: {
        Link, Head, Pagination, DeleteAlert,
        PageHeader, Button, Badge, DataTable, SearchInput, Select, Tooltip,
        Plus, Download, Pencil, Trash2, Package, Layers, Hash,
    },
    props: {
        products: { type: Object, default: () => ({ data: [], links: [] }) },
        filters: { type: Object, default: () => ({}) },
    },
    data() {
        return {
            crumbs: [
                { label: 'Dashboard', href: route('admin.dashboard') },
                { label: 'Products' },
            ],
            columns: [
                { key: 'name', label: 'Product', sortable: false },
                { key: 'price', label: 'Price', align: 'right', headerClass: 'w-28' },
                { key: 'stock', label: 'Stock', headerClass: 'w-40' },
                { key: 'status', label: 'Status', headerClass: 'w-28' },
            ],
            statusOptions: [
                { value: '', label: 'All statuses' },
                { value: 'active', label: 'Active only' },
                { value: 'inactive', label: 'Inactive only' },
            ],
            search: this.filters?.search || '',
            status: this.filters?.status || '',
            isDeleted: false,
            deleteId: null,
            searchTimeOut: null,
        };
    },
    computed: {
        hasFilters() {
            return !!(this.search || this.status);
        },
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
        search() { this.debouncedReload(); },
        status() { this.reload(); },
    },
    methods: {
        debouncedReload() {
            clearTimeout(this.searchTimeOut);
            this.searchTimeOut = setTimeout(this.reload, 400);
        },
        reload() {
            const params = {};
            if (this.search) params.search = this.search;
            if (this.status) params.status = this.status;
            router.get(route('admin.products'), params, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        },
        clearFilters() {
            this.search = '';
            this.status = '';
        },
        onRemoveChip(key) {
            if (key === 'search') this.search = '';
            if (key === 'status') this.status = '';
        },
        deleteProduct(id) {
            this.isDeleted = true;
            this.deleteId = id;
            this.deleteBatch = null;
        },
        bulkDelete(selected) {
            if (!selected?.length) return;
            this.isDeleted = true;
            this.deleteBatch = selected.map((r) => r.id);
        },
        deleteProductConfirmed() {
            if (this.deleteBatch?.length) {
                // Backend has no batch endpoint yet — fall through to sequential delete.
                this.deleteBatch.forEach((id) => {
                    router.delete(route('admin.product.delete', id), { preserveScroll: true, preserveState: true });
                });
                this.deleteBatch = null;
            } else if (this.deleteId) {
                router.delete(route('admin.product.delete', this.deleteId), { preserveScroll: true });
            }
            this.isDeleted = false;
            this.deleteId = null;
        },
        cancelDelete() {
            this.isDeleted = false;
            this.deleteId = null;
            this.deleteBatch = null;
        },
        formatPrice(v) {
            const n = Number(v || 0);
            return n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        effectiveStock(row) {
            // Key off has_variants, not the row count: a product whose variants
            // were switched off is stocked by its own column again, and counting
            // rows would keep reporting the variant rollup instead.
            const raw = row?.has_variants ? row?.variants_stock_sum : row?.stock;
            return Number(raw || 0);
        },
        stockVariant(stock) {
            const n = Number(stock || 0);
            if (n <= 0) return 'error';
            if (n <= 5) return 'warning';
            return 'success';
        },
    },
};
</script>
