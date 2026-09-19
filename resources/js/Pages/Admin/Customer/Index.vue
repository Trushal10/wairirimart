<template>
    <Head title="Customers" />

    <PageHeader
        title="Customers"
        subtitle="View shoppers, review order history, and block abusive accounts."
        :crumbs="crumbs"
    />

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 mb-6">
        <StatCard label="Total" :value="stats.total" tone="neutral" />
        <StatCard label="Active" :value="stats.active" tone="success" />
        <StatCard label="Blocked" :value="stats.blocked" tone="error" />
        <StatCard label="New (7 days)" :value="stats.new_7d" tone="brand" />
    </div>

    <DataTable
        :columns="columns"
        :rows="customers.data"
        row-key="id"
        :filter-chips="chips"
        :empty-text="hasFilters ? 'No customers match your filters.' : 'No customers yet.'"
        @remove-filter="onRemoveChip"
        @clear-filters="clearFilters"
    >
        <template #toolbar>
            <div class="flex flex-wrap items-center gap-2 flex-1">
                <SearchInput v-model="search" placeholder="Search name, email, phone…" class="max-w-xs" />
                <Select v-model="status" :options="statusOptions" class="max-w-[180px]" />
                <Select v-model="verified" :options="verifiedOptions" class="max-w-[200px]" />
            </div>
            <span class="text-[12px] text-gray-500 dark:text-gray-400">
                <span class="num-tabular font-semibold text-gray-800 dark:text-white/90">{{ customers.total ?? customers.data.length }}</span>
                {{ (customers.total ?? customers.data.length) === 1 ? 'customer' : 'customers' }}
            </span>
        </template>

        <template #cell-name="{ row }">
            <div class="flex items-center gap-3 min-w-0">
                <div class="h-9 w-9 shrink-0 rounded-full overflow-hidden bg-gray-900 text-white flex items-center justify-center font-semibold text-[13px]">
                    <img v-if="row.image || row.avatar" :src="row.image ? `/storage/customer/${row.image}` : row.avatar" :alt="row.name" class="h-full w-full object-cover" />
                    <span v-else>{{ initials(row.name) }}</span>
                </div>
                <div class="min-w-0">
                    <div class="text-body-strong text-gray-900 dark:text-white/95 truncate">{{ row.name }}</div>
                    <div class="text-[11.5px] text-gray-500 dark:text-gray-400 truncate">
                        {{ row.email || row.phone || 'No contact' }}
                    </div>
                </div>
            </div>
        </template>

        <template #cell-verified="{ row }">
            <div class="flex flex-wrap items-center gap-1">
                <Badge v-if="row.email_verified_at" variant="success" size="sm" dot>Email</Badge>
                <Badge v-if="row.phone_verified_at" variant="success" size="sm" dot>Phone</Badge>
                <Badge v-if="!row.email_verified_at && !row.phone_verified_at" variant="warning" size="sm" dot>Unverified</Badge>
            </div>
        </template>

        <template #cell-orders_count="{ row }">
            <span class="num-tabular text-[13px] text-gray-700 dark:text-gray-300">{{ row.orders_count ?? 0 }}</span>
        </template>

        <template #cell-spent_total="{ row }">
            <span class="num-tabular text-body-strong text-gray-900 dark:text-white/95">
                ₹{{ formatPrice(row.spent_total || 0) }}
            </span>
        </template>

        <template #cell-created_at="{ row }">
            <span class="text-[13px] text-gray-700 dark:text-gray-300 num-tabular">{{ formatDate(row.created_at) }}</span>
        </template>

        <template #cell-blocked_at="{ row }">
            <Badge :variant="row.blocked_at ? 'error' : 'success'" dot>
                {{ row.blocked_at ? 'Blocked' : 'Active' }}
            </Badge>
        </template>

        <template #rowActions="{ row }">
            <div class="flex items-center justify-end gap-0.5">
                <Tooltip :content="row.blocked_at ? 'Unblock' : 'Block'">
                    <button
                        type="button"
                        @click="row.blocked_at ? unblock(row) : askBlock(row)"
                        :class="[
                            'inline-flex h-8 w-8 items-center justify-center rounded-md transition-colors focus-ring',
                            row.blocked_at
                                ? 'text-gray-500 hover:text-success-700 hover:bg-success-50 dark:text-gray-400 dark:hover:text-success-400 dark:hover:bg-success-500/10'
                                : 'text-gray-500 hover:text-error-700 hover:bg-error-50 dark:text-gray-400 dark:hover:text-error-400 dark:hover:bg-error-500/10',
                        ]"
                        :aria-label="row.blocked_at ? `Unblock ${row.name}` : `Block ${row.name}`"
                    >
                        <ShieldCheck v-if="row.blocked_at" :size="14" />
                        <ShieldOff v-else :size="14" />
                    </button>
                </Tooltip>
                <Tooltip content="View">
                    <Link
                        :href="route('admin.customers.show', row.id)"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/[0.08] dark:hover:text-white/95 transition-colors focus-ring"
                        :aria-label="`View ${row.name}`"
                    >
                        <Eye :size="14" />
                    </Link>
                </Tooltip>
            </div>
        </template>

        <template #footer>
            <Pagination :links="customers.links" />
        </template>
    </DataTable>

    <!-- Block modal -->
    <div v-if="pendingBlock" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 p-4" @click.self="pendingBlock = null">
        <div class="w-full max-w-md rounded-xl bg-white dark:bg-[color:var(--color-surface-dark)] p-6 shadow-elevation-3">
            <h3 class="text-body-strong text-gray-900 dark:text-white/95 mb-1">Block {{ pendingBlock.name }}?</h3>
            <p class="text-[13px] text-gray-500 dark:text-gray-400 mb-4">
                They'll be signed out immediately and won't be able to sign in until unblocked.
            </p>
            <FormField label="Reason (optional)" hint="Only visible to admins.">
                <Textarea v-model="blockReason" rows="3" placeholder="e.g. Chargeback dispute #4211" />
            </FormField>
            <div class="mt-4 flex justify-end gap-2">
                <Button variant="secondary" size="sm" @click="pendingBlock = null">Cancel</Button>
                <Button variant="danger" size="sm" @click="confirmBlock">Block customer</Button>
            </div>
        </div>
    </div>
</template>

<script>
import { Head, Link, router } from '@inertiajs/vue3';
import MainLayout from '../../../Layout/MainLayout.vue';
import Pagination from '@/Components/common/Pagination.vue';
import { PageHeader, Badge, DataTable, SearchInput, Select, Tooltip, Button, FormField, Textarea } from '@/Components/ui';
import { Eye, ShieldOff, ShieldCheck } from '@lucide/vue';

const StatCard = {
    props: { label: String, value: [Number, String], tone: { type: String, default: 'neutral' } },
    template: `
        <div class="rounded-xl border border-gray-200 dark:border-white/[0.06] bg-white dark:bg-[color:var(--color-surface-dark)] px-4 py-3">
            <div class="text-eyebrow text-gray-500 dark:text-gray-400">{{ label }}</div>
            <div class="mt-1 num-tabular font-semibold text-[22px]" :class="{
                'text-gray-900 dark:text-white/95': tone === 'neutral',
                'text-success-700 dark:text-success-400': tone === 'success',
                'text-error-700 dark:text-error-400': tone === 'error',
                'text-brand-600 dark:text-brand-400': tone === 'brand',
            }">{{ value }}</div>
        </div>
    `,
};

export default {
    layout: MainLayout,
    components: {
        Head, Link, Pagination, StatCard,
        PageHeader, Badge, DataTable, SearchInput, Select, Tooltip, Button, FormField, Textarea,
        Eye, ShieldOff, ShieldCheck,
    },
    props: {
        customers: Object,
        filters: Object,
        stats: Object,
    },
    data() {
        return {
            crumbs: [
                { label: 'Dashboard', href: route('admin.dashboard') },
                { label: 'Customers' },
            ],
            columns: [
                { key: 'name', label: 'Customer' },
                { key: 'verified', label: 'Verified', headerClass: 'w-32' },
                { key: 'orders_count', label: 'Orders', headerClass: 'w-20' },
                { key: 'spent_total', label: 'Spent', headerClass: 'w-28' },
                { key: 'created_at', label: 'Joined', headerClass: 'w-32' },
                { key: 'blocked_at', label: 'Status', headerClass: 'w-24' },
            ],
            statusOptions: [
                { value: '', label: 'All statuses' },
                { value: 'active', label: 'Active' },
                { value: 'blocked', label: 'Blocked' },
            ],
            verifiedOptions: [
                { value: '', label: 'All verification' },
                { value: 'email', label: 'Email verified' },
                { value: 'phone', label: 'Phone verified' },
                { value: 'unverified', label: 'Unverified' },
            ],
            search: this.filters?.search || '',
            status: this.filters?.status || '',
            verified: this.filters?.verified || '',
            searchTimer: null,
            pendingBlock: null,
            blockReason: '',
        };
    },
    computed: {
        hasFilters() { return !!(this.search || this.status || this.verified); },
        chips() {
            const list = [];
            if (this.search) list.push({ key: 'search', label: `“${this.search}”` });
            if (this.status) {
                const opt = this.statusOptions.find((o) => o.value === this.status);
                if (opt) list.push({ key: 'status', label: opt.label });
            }
            if (this.verified) {
                const opt = this.verifiedOptions.find((o) => o.value === this.verified);
                if (opt) list.push({ key: 'verified', label: opt.label });
            }
            return list;
        },
    },
    watch: {
        search() { this.debounceFilter(); },
        status() { this.applyFilters(); },
        verified() { this.applyFilters(); },
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
            router.get(route('admin.customers.index'), {
                search: this.search || undefined,
                status: this.status || undefined,
                verified: this.verified || undefined,
            }, { preserveState: true, preserveScroll: true, replace: true });
        },
        clearFilters() {
            this.search = ''; this.status = ''; this.verified = '';
        },
        onRemoveChip(key) {
            if (key === 'search') this.search = '';
            if (key === 'status') this.status = '';
            if (key === 'verified') this.verified = '';
        },
        formatDate(d) {
            return d ? new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
        },
        formatPrice(v) {
            const n = Number(v || 0);
            return n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        initials(name) {
            if (!name) return '?';
            return name.trim().split(/\s+/).slice(0, 2).map(w => w[0]?.toUpperCase() || '').join('');
        },
        askBlock(c) {
            this.pendingBlock = c;
            this.blockReason = '';
        },
        confirmBlock() {
            if (!this.pendingBlock) return;
            const id = this.pendingBlock.id;
            router.post(route('admin.customers.block', id), { reason: this.blockReason }, {
                preserveScroll: true,
                onSuccess: () => { this.pendingBlock = null; this.blockReason = ''; },
            });
        },
        unblock(c) {
            router.post(route('admin.customers.unblock', c.id), {}, { preserveScroll: true });
        },
    },
};
</script>
