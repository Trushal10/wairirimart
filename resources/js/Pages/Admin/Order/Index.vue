<template>
    <Head title="Orders" />

    <PageHeader
        title="Orders"
        subtitle="Manage customer orders, fulfilment, and shipments."
        :crumbs="crumbs"
    >
        <template #actions>
            <Button variant="secondary" size="sm" tag="a" :href="exportUrl" title="Download orders as CSV (respects filters)">
                <template #leading><Download :size="14" /></template>
                Export CSV
            </Button>
        </template>
    </PageHeader>

    <div class="space-y-6">
        <!-- KPI strip -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <button
                v-for="k in kpis"
                :key="k.key"
                type="button"
                @click="statusFilter = k.filter"
                :class="[
                    'group text-left rounded-2xl border p-4 transition-all',
                    isActiveKpi(k.filter)
                        ? 'border-gray-900 bg-white dark:border-white dark:bg-[color:var(--color-surface-dark)] shadow-elevation-2'
                        : 'border-gray-200 bg-white dark:border-white/[0.06] dark:bg-[color:var(--color-surface-dark)] hover:border-gray-300 dark:hover:border-white/[0.14] hover:shadow-elevation-1',
                ]"
            >
                <div class="flex items-center justify-between gap-2">
                    <div class="text-eyebrow text-gray-500 dark:text-gray-400">{{ k.label }}</div>
                    <Badge :variant="k.variant" size="sm" dot />
                </div>
                <div class="mt-2 num-tabular text-h1 text-gray-900 dark:text-white/95">{{ k.value }}</div>
            </button>
        </div>

        <DataTable
            :columns="columns"
            :rows="orders.data"
            row-key="id"
            :filter-chips="chips"
            :empty-text="hasFilters ? 'No orders match your filters.' : 'No orders yet.'"
            @remove-filter="onRemoveChip"
            @clear-filters="clearFilters"
        >
            <template #toolbar>
                <div class="flex flex-wrap items-center gap-2 flex-1">
                    <SearchInput v-model="search" placeholder="Order no, customer, phone…" class="max-w-md" />
                    <Select v-model="statusFilter" :options="statusOptions" class="max-w-[180px]" />
                </div>
                <span class="text-[12px] text-gray-500 dark:text-gray-400">
                    <span class="num-tabular font-semibold text-gray-800 dark:text-white/90">{{ orders.total ?? orders.data.length }}</span>
                    {{ (orders.total ?? orders.data.length) === 1 ? 'order' : 'orders' }}
                </span>
            </template>

            <template #cell-order_no="{ row }">
                <Link
                    :href="route('admin.order.detail', row.id)"
                    class="inline-flex items-center gap-1 num-tabular text-body-strong text-gray-900 dark:text-white/95 hover:text-brand-600 dark:hover:text-brand-400 transition-colors"
                >
                    #{{ row.order_no }}
                </Link>
            </template>

            <template #cell-customer="{ row }">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span
                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-white text-[10.5px] font-semibold"
                        :style="{ background: avatarColor(row.shipping_name) }"
                        aria-hidden="true"
                    >{{ initials(row.shipping_name) }}</span>
                    <div class="min-w-0">
                        <div class="text-body text-gray-900 dark:text-white/90 truncate">{{ row.shipping_name || '—' }}</div>
                        <div v-if="row.shipping_phone" class="text-[11.5px] text-gray-500 dark:text-gray-400 num-tabular truncate">
                            {{ row.shipping_phone }}
                        </div>
                    </div>
                </div>
            </template>

            <template #cell-created_at="{ row }">
                <span class="text-[12.5px] text-gray-600 dark:text-gray-400 num-tabular">
                    {{ formatDate(row.created_at) }}
                </span>
            </template>

            <template #cell-order_items_count="{ row }">
                <span class="text-[13px] text-gray-700 dark:text-gray-300 num-tabular">{{ row.order_items_count }}</span>
            </template>

            <template #cell-total="{ row }">
                <span class="text-body-strong num-tabular text-gray-900 dark:text-white/95">
                    ₹{{ money(row.total) }}
                </span>
            </template>

            <template #cell-status="{ row }">
                <div v-if="!row.editing">
                    <button
                        type="button"
                        @click="startEditing(row)"
                        class="rounded-full focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/25 transition-shadow"
                        :title="`Change status (currently ${row.status})`"
                    >
                        <Badge :variant="statusVariant(row.status)" size="md" dot>
                            {{ prettyStatus(row.status) }}
                        </Badge>
                    </button>
                </div>
                <div v-else class="max-w-[160px]">
                    <Select
                        v-model="row.newStatus"
                        :options="editableStatusOptions"
                        size="sm"
                        :default-open="true"
                        @change="submitStatus(row)"
                        @close="onEditorClose(row)"
                    />
                </div>
            </template>

            <template #cell-shipment="{ row }">
                <div v-if="row.latest_shipment" class="flex flex-col gap-1">
                    <Badge :variant="shipmentVariant(row.latest_shipment.status)" size="sm" dot>
                        {{ prettyStatus(row.latest_shipment.status) }}
                    </Badge>
                    <span v-if="row.latest_shipment.awb_code" class="text-[11.5px] text-gray-500 dark:text-gray-400 num-tabular">
                        AWB · {{ row.latest_shipment.awb_code }}
                    </span>
                    <span v-else-if="row.latest_shipment.courier_name" class="text-[11.5px] text-gray-500 dark:text-gray-400 truncate">
                        {{ row.latest_shipment.courier_name }}
                    </span>
                </div>
                <span v-else class="text-[12px] text-gray-400 dark:text-gray-500 italic">Not shipped</span>
            </template>

            <template #rowActions="{ row }">
                <div class="flex items-center justify-end gap-0.5">
                    <Tooltip content="View order">
                        <Link
                            :href="route('admin.order.detail', row.id)"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/[0.08] dark:hover:text-white/95 transition-colors focus-ring"
                            :aria-label="`Open order ${row.order_no}`"
                        >
                            <Eye :size="14" />
                        </Link>
                    </Tooltip>
                    <Tooltip content="Print label">
                        <a
                            :href="route('admin.order.label', row.id)"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/[0.08] dark:hover:text-white/95 transition-colors focus-ring"
                            aria-label="Print label"
                        >
                            <Printer :size="14" />
                        </a>
                    </Tooltip>
                </div>
            </template>

            <template #footer>
                <Pagination :links="orders.links" />
            </template>
        </DataTable>
    </div>
</template>

<script>
import Layout from '@/Layout/MainLayout.vue';
import { Link, Head, router } from '@inertiajs/vue3';
import Pagination from '@/Components/common/Pagination.vue';
import { PageHeader, Button, Badge, DataTable, SearchInput, Select, Tooltip } from '@/Components/ui';
import { Download, Eye, Printer } from '@lucide/vue';

const AVATAR_COLORS = ['#3641F5', '#12B76A', '#F79009', '#0BA5EC', '#7A5AF8', '#EE46BC', '#F04438'];
function hashString(s = '') {
    let h = 0;
    for (let i = 0; i < s.length; i++) h = ((h << 5) - h + s.charCodeAt(i)) | 0;
    return Math.abs(h);
}

export default {
    layout: Layout,
    components: {
        Link, Head, Pagination,
        PageHeader, Button, Badge, DataTable, SearchInput, Select, Tooltip,
        Download, Eye, Printer,
    },
    props: {
        orders: { type: Object, required: true },
        filters: { type: Object, default: () => ({}) },
        counts: { type: Object, default: () => ({}) },
    },
    data() {
        return {
            crumbs: [
                { label: 'Dashboard', href: route('admin.dashboard') },
                { label: 'Orders' },
            ],
            columns: [
                { key: 'order_no', label: 'Order', headerClass: 'w-28' },
                { key: 'customer', label: 'Customer' },
                { key: 'created_at', label: 'Date' },
                { key: 'order_items_count', label: 'Items', headerClass: 'w-16' },
                { key: 'total', label: 'Total', align: 'right', headerClass: 'w-28' },
                { key: 'status', label: 'Status', headerClass: 'w-36' },
                { key: 'shipment', label: 'Shipment', headerClass: 'w-40' },
            ],
            statusOptions: [
                { value: '', label: 'All statuses' },
                { value: 'pending', label: 'Pending' },
                { value: 'confirmed', label: 'Confirmed' },
                { value: 'delivered', label: 'Delivered' },
                { value: 'canceled', label: 'Cancelled' },
            ],
            editableStatusOptions: [
                { value: 'pending', label: 'Pending' },
                { value: 'confirmed', label: 'Confirmed' },
                { value: 'delivered', label: 'Delivered' },
                { value: 'canceled', label: 'Cancelled' },
            ],
            search: this.filters?.search || '',
            statusFilter: this.filters?.status || '',
            searchTimer: null,
        };
    },
    computed: {
        hasFilters() { return !!(this.search || this.statusFilter); },
        kpis() {
            return [
                { key: 'pending',   filter: 'pending',   label: 'Pending',   value: this.counts.pending   || 0, variant: 'warning' },
                { key: 'confirmed', filter: 'confirmed', label: 'Confirmed', value: this.counts.confirmed || 0, variant: 'success' },
                { key: 'delivered', filter: 'delivered', label: 'Delivered', value: this.counts.delivered || 0, variant: 'info' },
                { key: 'canceled',  filter: 'canceled',  label: 'Cancelled', value: this.counts.canceled  || 0, variant: 'error' },
            ];
        },
        chips() {
            const list = [];
            if (this.search) list.push({ key: 'search', label: `“${this.search}”` });
            if (this.statusFilter) {
                const opt = this.statusOptions.find((o) => o.value === this.statusFilter);
                if (opt) list.push({ key: 'status', label: opt.label });
            }
            return list;
        },
        exportUrl() {
            const params = new URLSearchParams();
            if (this.search) params.set('search', this.search);
            if (this.statusFilter) params.set('status', this.statusFilter);
            const query = params.toString();
            return route('admin.reports.orders') + (query ? '?' + query : '');
        },
    },
    watch: {
        search() { this.debouncedRefresh(); },
        statusFilter() { this.debouncedRefresh(); },
    },
    beforeUnmount() {
        if (this.searchTimer) clearTimeout(this.searchTimer);
    },
    methods: {
        isActiveKpi(filter) { return this.statusFilter === filter; },
        onRemoveChip(key) {
            if (key === 'search') this.search = '';
            if (key === 'status') this.statusFilter = '';
        },
        debouncedRefresh() {
            if (this.searchTimer) clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => {
                router.get(route('admin.orders'), {
                    search: this.search || undefined,
                    status: this.statusFilter || undefined,
                }, { preserveState: true, preserveScroll: true, replace: true });
            }, 350);
        },
        clearFilters() {
            this.search = '';
            this.statusFilter = '';
        },
        money(v) {
            const n = Number(v || 0);
            return n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        formatDate(v) {
            if (!v) return '—';
            const d = new Date(v);
            if (Number.isNaN(d.getTime())) return v;
            return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        },
        prettyStatus(s) {
            const raw = String(s || '').replaceAll('_', ' ');
            return raw.charAt(0).toUpperCase() + raw.slice(1);
        },
        statusVariant(status) {
            return {
                pending: 'warning',
                confirmed: 'brand',
                delivered: 'success',
                shipped: 'info',
                canceled: 'error',
                cancelled: 'error',
                refunded: 'neutral',
            }[status] || 'neutral';
        },
        shipmentVariant(status) {
            if (status === 'delivered') return 'success';
            if (['cancelled', 'rto', 'failed'].includes(status)) return 'error';
            if (['pending'].includes(status)) return 'warning';
            return 'info';
        },
        initials(name) {
            const n = (name || '').trim();
            if (!n) return '·';
            const parts = n.split(/\s+/).filter(Boolean);
            const first = parts[0]?.[0] || '';
            const last = parts.length > 1 ? parts[parts.length - 1][0] : '';
            return (first + last).toUpperCase() || first.toUpperCase();
        },
        avatarColor(name) { return AVATAR_COLORS[hashString(name || '') % AVATAR_COLORS.length]; },
        startEditing(order) {
            order.editing = true;
            order.newStatus = order.status;
        },
        onEditorClose(order) {
            if (order.newStatus === order.status) order.editing = false;
        },
        submitStatus(order) {
            const url = route('admin.order.update', order.id);
            router.put(url, { status: order.newStatus }, {
                preserveScroll: true,
                onSuccess: () => {
                    order.status = order.newStatus;
                    order.editing = false;
                },
                onError: () => {
                    order.newStatus = order.status;
                    order.editing = false;
                },
            });
        },
    },
};
</script>
