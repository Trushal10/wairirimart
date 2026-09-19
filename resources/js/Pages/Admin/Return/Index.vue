<template>
    <Head title="Returns" />

    <PageHeader
        title="Returns"
        subtitle="Review return requests, approve or reject, and issue refunds."
        :crumbs="crumbs"
    />

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
            :rows="returns.data"
            row-key="id"
            :filter-chips="chips"
            :empty-text="hasFilters ? 'No returns match your filters.' : 'No returns yet.'"
            @remove-filter="onRemoveChip"
            @clear-filters="clearFilters"
        >
            <template #toolbar>
                <div class="flex flex-wrap items-center gap-2 flex-1">
                    <SearchInput v-model="search" placeholder="Return #, order #, customer…" class="max-w-md" />
                    <Select v-model="statusFilter" :options="statusOptions" class="max-w-[180px]" />
                </div>
                <span class="text-[12px] text-gray-500 dark:text-gray-400">
                    <span class="num-tabular font-semibold text-gray-800 dark:text-white/90">{{ returns.total ?? returns.data.length }}</span>
                    {{ (returns.total ?? returns.data.length) === 1 ? 'return' : 'returns' }}
                </span>
            </template>

            <template #cell-return_no="{ row }">
                <Link
                    :href="route('admin.returns.show', row.id)"
                    class="num-tabular text-body-strong text-gray-900 dark:text-white/95 hover:text-brand-600 dark:hover:text-brand-400 transition-colors"
                >{{ row.return_no }}</Link>
            </template>

            <template #cell-order="{ row }">
                <Link
                    v-if="row.order"
                    :href="route('admin.order.detail', row.order.id)"
                    class="num-tabular text-[13px] text-gray-700 dark:text-gray-300 hover:text-brand-600 dark:hover:text-brand-400 transition-colors"
                >#{{ row.order.order_no }}</Link>
                <span v-else class="text-[12px] text-gray-400 italic">—</span>
            </template>

            <template #cell-customer="{ row }">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span
                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-white text-[10.5px] font-semibold"
                        :style="{ background: avatarColor(row.customer?.name || row.order?.shipping_name) }"
                        aria-hidden="true"
                    >{{ initials(row.customer?.name || row.order?.shipping_name) }}</span>
                    <div class="min-w-0">
                        <div class="text-body text-gray-900 dark:text-white/90 truncate">
                            {{ row.customer?.name || row.order?.shipping_name || '—' }}
                        </div>
                        <div class="text-[11.5px] text-gray-500 dark:text-gray-400 truncate">
                            {{ row.customer?.email || row.order?.shipping_email }}
                        </div>
                    </div>
                </div>
            </template>

            <template #cell-items_count="{ row }">
                <span class="text-[13px] text-gray-700 dark:text-gray-300 num-tabular">
                    {{ row.items_count }}
                </span>
            </template>

            <template #cell-refund_amount="{ row }">
                <span class="num-tabular text-body-strong text-gray-900 dark:text-white/95">
                    ₹{{ money(row.refund_amount) }}
                </span>
            </template>

            <template #cell-status="{ row }">
                <Badge :variant="statusVariant(row.status)" dot>
                    {{ prettyStatus(row.status) }}
                </Badge>
            </template>

            <template #cell-requested_at="{ row }">
                <span class="text-[12.5px] text-gray-600 dark:text-gray-400 num-tabular">
                    {{ formatDate(row.requested_at || row.created_at) }}
                </span>
            </template>

            <template #rowActions="{ row }">
                <Tooltip content="Open return">
                    <Link
                        :href="route('admin.returns.show', row.id)"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/[0.08] dark:hover:text-white/95 transition-colors focus-ring"
                        :aria-label="`Open return ${row.return_no}`"
                    >
                        <Eye :size="14" />
                    </Link>
                </Tooltip>
            </template>

            <template #footer>
                <Pagination :links="returns.links" />
            </template>
        </DataTable>
    </div>
</template>

<script>
import Layout from '@/Layout/MainLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Pagination from '@/Components/common/Pagination.vue';
import { PageHeader, Button, Badge, DataTable, SearchInput, Select, Tooltip } from '@/Components/ui';
import { Eye } from '@lucide/vue';

const AVATAR_COLORS = ['#3641F5', '#12B76A', '#F79009', '#0BA5EC', '#7A5AF8', '#EE46BC', '#F04438'];
function hashString(s = '') {
    let h = 0;
    for (let i = 0; i < s.length; i++) h = ((h << 5) - h + s.charCodeAt(i)) | 0;
    return Math.abs(h);
}

export default {
    layout: Layout,
    components: {
        Head, Link, Pagination,
        PageHeader, Button, Badge, DataTable, SearchInput, Select, Tooltip,
        Eye,
    },
    props: {
        returns: { type: Object, required: true },
        filters: { type: Object, default: () => ({}) },
        counts: { type: Object, default: () => ({}) },
    },
    data() {
        return {
            crumbs: [
                { label: 'Dashboard', href: route('admin.dashboard') },
                { label: 'Returns' },
            ],
            columns: [
                { key: 'return_no', label: 'Return #', headerClass: 'w-32' },
                { key: 'order', label: 'Order', headerClass: 'w-28' },
                { key: 'customer', label: 'Customer' },
                { key: 'items_count', label: 'Items', headerClass: 'w-16' },
                { key: 'refund_amount', label: 'Amount', align: 'right', headerClass: 'w-32' },
                { key: 'status', label: 'Status', headerClass: 'w-32' },
                { key: 'requested_at', label: 'Requested', headerClass: 'w-32' },
            ],
            statusOptions: [
                { value: '', label: 'All statuses' },
                { value: 'requested', label: 'Requested' },
                { value: 'approved', label: 'Approved' },
                { value: 'rejected', label: 'Rejected' },
                { value: 'received', label: 'Received' },
                { value: 'refunded', label: 'Refunded' },
                { value: 'cancelled', label: 'Cancelled' },
            ],
            search: this.filters?.search || '',
            statusFilter: this.filters?.status || '',
            searchTimer: null,
        };
    },
    computed: {
        kpis() {
            return [
                { key: 'requested', filter: 'requested', label: 'Awaiting decision', value: this.counts.requested || 0, variant: 'info' },
                { key: 'approved',  filter: 'approved',  label: 'Awaiting package',  value: this.counts.approved  || 0, variant: 'warning' },
                { key: 'received',  filter: 'received',  label: 'Awaiting refund',   value: this.counts.received  || 0, variant: 'brand' },
                { key: 'refunded',  filter: 'refunded',  label: 'Completed',          value: this.counts.refunded  || 0, variant: 'success' },
            ];
        },
        hasFilters() { return !!(this.search || this.statusFilter); },
        chips() {
            const list = [];
            if (this.search) list.push({ key: 'search', label: `“${this.search}”` });
            if (this.statusFilter) {
                const opt = this.statusOptions.find((o) => o.value === this.statusFilter);
                if (opt) list.push({ key: 'status', label: opt.label });
            }
            return list;
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
                router.get(route('admin.returns.index'), {
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
            if (!s) return '—';
            const raw = String(s).replaceAll('_', ' ');
            return raw.charAt(0).toUpperCase() + raw.slice(1);
        },
        statusVariant(status) {
            return {
                requested: 'info',
                approved: 'warning',
                received: 'brand',
                refunded: 'success',
                rejected: 'error',
                cancelled: 'neutral',
            }[status] || 'neutral';
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
    },
};
</script>
