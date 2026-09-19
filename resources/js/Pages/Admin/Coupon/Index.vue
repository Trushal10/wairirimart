<template>
    <Head title="Coupons" />

    <PageHeader
        title="Coupons"
        subtitle="Create and manage discount codes for your storefront."
        :crumbs="crumbs"
    >
        <template #actions>
            <Button variant="primary" size="sm" tag="a" :href="route('admin.coupons.create')">
                <template #leading><Plus :size="14" /></template>
                New coupon
            </Button>
        </template>
    </PageHeader>

    <DataTable
        :columns="columns"
        :rows="coupons.data"
        row-key="id"
        :filter-chips="chips"
        :empty-text="hasFilters ? 'No coupons match your filters.' : 'No coupons yet — create your first discount code.'"
        @remove-filter="onRemoveChip"
        @clear-filters="clearFilters"
    >
        <template #toolbar>
            <div class="flex flex-wrap items-center gap-2 flex-1">
                <SearchInput v-model="search" placeholder="Search by code…" class="max-w-xs" />
                <Select v-model="status" :options="statusOptions" class="max-w-[180px]" />
            </div>
            <span class="text-[12px] text-gray-500 dark:text-gray-400">
                <span class="num-tabular font-semibold text-gray-800 dark:text-white/90">{{ coupons.total ?? coupons.data.length }}</span>
                {{ (coupons.total ?? coupons.data.length) === 1 ? 'coupon' : 'coupons' }}
            </span>
        </template>

        <template #cell-code="{ row }">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-md bg-gray-100 dark:bg-white/[0.06] px-2 py-1 font-mono font-bold uppercase tracking-widest text-[12px] text-gray-900 dark:text-white/95">
                    <TicketPercent :size="12" class="text-gray-400" />
                    {{ row.code }}
                </span>
                <Tooltip content="Copy code">
                    <button
                        type="button"
                        @click="copyCode(row.code, row.id)"
                        class="inline-flex h-6 w-6 items-center justify-center rounded-md text-gray-400 hover:text-gray-900 hover:bg-gray-100 dark:hover:text-white/95 dark:hover:bg-white/[0.06] transition-colors focus-ring"
                        :aria-label="`Copy ${row.code}`"
                    >
                        <Check v-if="copiedId === row.id" :size="12" class="text-success-500" />
                        <Copy v-else :size="12" />
                    </button>
                </Tooltip>
            </div>
        </template>

        <template #cell-discount="{ row }">
            <div class="flex flex-col">
                <span class="num-tabular text-body-strong text-gray-900 dark:text-white/95">
                    {{ row.type === 'percent' ? row.value + '%' : '₹' + formatPrice(row.value) }} off
                </span>
                <span v-if="row.min_order_amount > 0" class="text-[11.5px] text-gray-500 dark:text-gray-400 num-tabular">
                    Min ₹{{ formatPrice(row.min_order_amount) }}
                </span>
                <span v-else class="text-[11.5px] text-gray-400 dark:text-gray-500 italic">No minimum</span>
            </div>
        </template>

        <template #cell-usage="{ row }">
            <div v-if="row.usage_limit" class="w-32">
                <div class="flex items-center justify-between text-[11.5px] mb-1 num-tabular">
                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ row.used_count }} / {{ row.usage_limit }}</span>
                    <span class="text-gray-500 dark:text-gray-400">{{ usagePct(row) }}%</span>
                </div>
                <div class="h-1 rounded-full bg-gray-100 dark:bg-white/[0.06] overflow-hidden">
                    <div
                        class="h-full rounded-full transition-all duration-300"
                        :class="usageBarClass(row)"
                        :style="{ width: usagePct(row) + '%' }"
                    ></div>
                </div>
            </div>
            <span v-else class="inline-flex items-center gap-1 text-[12px] text-gray-500 dark:text-gray-400 num-tabular">
                {{ row.used_count }} used · unlimited
            </span>
        </template>

        <template #cell-expires_at="{ row }">
            <div v-if="row.expires_at" class="flex flex-col">
                <span class="text-[13px] text-gray-700 dark:text-gray-300 num-tabular">
                    {{ formatDate(row.expires_at) }}
                </span>
                <span :class="expiryChipClass(row.expires_at)" class="text-[11px] font-medium num-tabular">
                    {{ expiryLabel(row.expires_at) }}
                </span>
            </div>
            <span v-else class="text-[12px] text-gray-400 dark:text-gray-500 italic">Never</span>
        </template>

        <template #cell-is_active="{ row }">
            <Badge :variant="row.is_active ? 'success' : 'neutral'" dot>
                {{ row.is_active ? 'Active' : 'Inactive' }}
            </Badge>
        </template>

        <template #rowActions="{ row }">
            <div class="flex items-center justify-end gap-0.5">
                <Tooltip :content="row.is_active ? 'Deactivate' : 'Activate'">
                    <button
                        type="button"
                        @click="toggle(row)"
                        :class="[
                            'inline-flex h-8 w-8 items-center justify-center rounded-md transition-colors focus-ring',
                            row.is_active
                                ? 'text-gray-500 hover:text-warning-700 hover:bg-warning-50 dark:text-gray-400 dark:hover:text-warning-400 dark:hover:bg-warning-500/10'
                                : 'text-gray-500 hover:text-success-700 hover:bg-success-50 dark:text-gray-400 dark:hover:text-success-400 dark:hover:bg-success-500/10',
                        ]"
                        :aria-label="row.is_active ? `Deactivate ${row.code}` : `Activate ${row.code}`"
                    >
                        <PowerOff v-if="row.is_active" :size="14" />
                        <Power v-else :size="14" />
                    </button>
                </Tooltip>
                <Tooltip content="Edit">
                    <Link
                        :href="route('admin.coupons.edit', row.id)"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/[0.08] dark:hover:text-white/95 transition-colors focus-ring"
                        :aria-label="`Edit ${row.code}`"
                    >
                        <Pencil :size="14" />
                    </Link>
                </Tooltip>
                <Tooltip content="Delete">
                    <button
                        type="button"
                        @click.prevent="askDelete(row)"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-500 hover:bg-error-50 hover:text-error-600 dark:text-gray-400 dark:hover:bg-error-500/10 dark:hover:text-error-400 transition-colors focus-ring"
                        :aria-label="`Delete ${row.code}`"
                    >
                        <Trash2 :size="14" />
                    </button>
                </Tooltip>
            </div>
        </template>

        <template #footer>
            <Pagination :links="coupons.links" />
        </template>
    </DataTable>

    <DeleteAlert
        v-if="pendingDelete"
        @confirmDelete="confirmDelete"
        @cancelDelete="cancelDelete"
        title="Delete coupon"
        :message="`Delete coupon “${pendingDelete.code}”? This cannot be undone.`"
    />
</template>

<script>
import { Head, Link, router } from '@inertiajs/vue3';
import MainLayout from '../../../Layout/MainLayout.vue';
import Pagination from '@/Components/common/Pagination.vue';
import DeleteAlert from '@/Components/common/DeleteAlert.vue';
import { PageHeader, Button, Badge, DataTable, SearchInput, Select, Tooltip } from '@/Components/ui';
import { Plus, Pencil, Trash2, Power, PowerOff, TicketPercent, Copy, Check } from '@lucide/vue';

export default {
    layout: MainLayout,
    components: {
        Head, Link, Pagination, DeleteAlert,
        PageHeader, Button, Badge, DataTable, SearchInput, Select, Tooltip,
        Plus, Pencil, Trash2, Power, PowerOff, TicketPercent, Copy, Check,
    },
    props: {
        coupons: Object,
        filters: Object,
    },
    data() {
        return {
            crumbs: [
                { label: 'Dashboard', href: route('admin.dashboard') },
                { label: 'Coupons' },
            ],
            columns: [
                { key: 'code', label: 'Code' },
                { key: 'discount', label: 'Discount' },
                { key: 'usage', label: 'Usage', headerClass: 'w-40' },
                { key: 'expires_at', label: 'Expires', headerClass: 'w-36' },
                { key: 'is_active', label: 'Status', headerClass: 'w-28' },
            ],
            statusOptions: [
                { value: '', label: 'All statuses' },
                { value: 'active', label: 'Active' },
                { value: 'inactive', label: 'Inactive' },
            ],
            search: this.filters?.search || '',
            status: this.filters?.status || '',
            searchTimer: null,
            pendingDelete: null,
            copiedId: null,
            copiedTimer: null,
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
        if (this.copiedTimer) clearTimeout(this.copiedTimer);
    },
    methods: {
        debounceFilter() {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => this.applyFilters(), 400);
        },
        applyFilters() {
            router.get(route('admin.coupons.index'), {
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
        formatPrice(v) {
            const n = Number(v || 0);
            return n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        usagePct(row) {
            const used = Number(row.used_count || 0);
            const limit = Number(row.usage_limit || 0);
            if (!limit) return 0;
            return Math.min(100, Math.round((used / limit) * 100));
        },
        usageBarClass(row) {
            const pct = this.usagePct(row);
            if (pct >= 90) return 'bg-error-500';
            if (pct >= 70) return 'bg-warning-500';
            return 'bg-gray-900 dark:bg-white';
        },
        expiryLabel(expiresAt) {
            const now = new Date();
            const then = new Date(expiresAt);
            const diffMs = then - now;
            const diffDays = Math.round(diffMs / (1000 * 60 * 60 * 24));
            if (diffDays < 0) return `Expired ${-diffDays}d ago`;
            if (diffDays === 0) return 'Expires today';
            if (diffDays === 1) return 'Expires tomorrow';
            if (diffDays <= 30) return `In ${diffDays}d`;
            const months = Math.round(diffDays / 30);
            return `In ${months}mo`;
        },
        expiryChipClass(expiresAt) {
            const now = new Date();
            const then = new Date(expiresAt);
            const diffDays = Math.round((then - now) / (1000 * 60 * 60 * 24));
            if (diffDays < 0) return 'text-error-600 dark:text-error-400';
            if (diffDays <= 7) return 'text-warning-700 dark:text-warning-400';
            return 'text-gray-500 dark:text-gray-400';
        },
        toggle(c) {
            router.post(route('admin.coupons.toggle', c.id), {}, { preserveScroll: true });
        },
        askDelete(c) { this.pendingDelete = c; },
        confirmDelete() {
            if (this.pendingDelete) {
                router.delete(route('admin.coupons.delete', this.pendingDelete.id), { preserveScroll: true });
            }
            this.pendingDelete = null;
        },
        cancelDelete() { this.pendingDelete = null; },
        async copyCode(code, id) {
            try {
                await navigator.clipboard.writeText(code);
                this.copiedId = id;
                clearTimeout(this.copiedTimer);
                this.copiedTimer = setTimeout(() => { this.copiedId = null; }, 1500);
            } catch (_) { /* ignore */ }
        },
    },
};
</script>
