<template>
    <Head title="Payments" />

    <PageHeader
        title="Payments"
        subtitle="Payment transactions across all gateways."
        :crumbs="crumbs"
    />

    <DataTable
        :columns="columns"
        :rows="payments.data"
        row-key="id"
        :filter-chips="chips"
        :empty-text="filters.search ? 'No payments match your search.' : 'No payments yet.'"
        @remove-filter="onRemoveChip"
        @clear-filters="clearFilters"
    >
        <template #toolbar>
            <SearchInput v-model="search" placeholder="Search by payment ID…" class="max-w-xs" />
            <span class="text-[12px] text-gray-500 dark:text-gray-400">
                <span class="num-tabular font-semibold text-gray-800 dark:text-white/90">{{ payments.total ?? payments.data.length }}</span>
                {{ (payments.total ?? payments.data.length) === 1 ? 'payment' : 'payments' }}
            </span>
        </template>

        <template #cell-sr="{ index }">
            <span class="num-tabular text-gray-500 dark:text-gray-400">
                {{ (payments.current_page - 1) * (payments.per_page || 10) + index + 1 }}
            </span>
        </template>

        <template #cell-payment_id="{ row }">
            <span v-if="row.payment_id" class="font-mono text-[12px] text-gray-700 dark:text-gray-300 num-tabular">{{ row.payment_id }}</span>
            <span v-else class="text-[12px] text-gray-400 dark:text-gray-500 italic">—</span>
        </template>

        <template #cell-created_at="{ row }">
            <span class="text-[12.5px] text-gray-600 dark:text-gray-400 num-tabular">{{ formatDate(row.created_at) }}</span>
        </template>

        <template #cell-type="{ row }">
            <Badge :variant="typeVariant(row.type)" size="sm">
                <component :is="typeIcon(row.type)" :size="10" class="mr-1" />
                {{ prettyType(row.type) }}
            </Badge>
        </template>

        <template #cell-amount="{ row }">
            <span class="num-tabular text-body-strong text-gray-900 dark:text-white/95">₹{{ money(row.amount) }}</span>
        </template>

        <template #cell-status="{ row }">
            <Badge :variant="statusVariant(row.status)" dot>{{ prettyStatus(row.status) }}</Badge>
        </template>

        <template #rowActions="{ row }">
            <Tooltip content="Delete record">
                <button
                    type="button"
                    @click.prevent="deletePayment(row.id)"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-500 hover:bg-error-50 hover:text-error-600 dark:text-gray-400 dark:hover:bg-error-500/10 dark:hover:text-error-400 transition-colors focus-ring"
                    aria-label="Delete payment record"
                >
                    <Trash2 :size="14" />
                </button>
            </Tooltip>
        </template>

        <template #footer>
            <Pagination :links="payments.links" />
        </template>
    </DataTable>

    <DeleteAlert
        v-if="isDeleted"
        @confirmDelete="deletePaymentConfirmed"
        @cancelDelete="cancelDelete"
        title="Delete payment"
        message="Delete this payment record? The underlying transaction with the gateway is not affected."
    />
</template>

<script>
import Layout from '@/Layout/MainLayout.vue';
import { Link, Head, router } from '@inertiajs/vue3';
import DeleteAlert from '@/Components/common/DeleteAlert.vue';
import Pagination from '@/Components/common/Pagination.vue';
import { PageHeader, Badge, DataTable, SearchInput, Tooltip } from '@/Components/ui';
import { Trash2, Banknote, CreditCard, Wallet } from '@lucide/vue';

export default {
    layout: Layout,
    components: {
        Link, Head, DeleteAlert, Pagination,
        PageHeader, Badge, DataTable, SearchInput, Tooltip,
        Trash2,
    },
    props: {
        payments: Object,
        filters: { type: Object, default: () => ({}) },
    },
    data() {
        return {
            crumbs: [
                { label: 'Dashboard', href: route('admin.dashboard') },
                { label: 'Payments' },
            ],
            columns: [
                { key: 'sr', label: '#', headerClass: 'w-16' },
                { key: 'payment_id', label: 'Payment ID' },
                { key: 'created_at', label: 'Date', headerClass: 'w-32' },
                { key: 'type', label: 'Type', headerClass: 'w-32' },
                { key: 'amount', label: 'Amount', align: 'right', headerClass: 'w-32' },
                { key: 'status', label: 'Status', headerClass: 'w-36' },
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
    watch: {
        search(value) {
            clearTimeout(this.searchTimeOut);
            this.searchTimeOut = setTimeout(() => {
                router.get(route('admin.payments'), { search: value }, {
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
        prettyStatus(status) {
            if (!status) return '—';
            const s = String(status).replaceAll('_', ' ');
            return s.charAt(0).toUpperCase() + s.slice(1);
        },
        prettyType(type) {
            const map = { cod: 'COD', razorpay: 'Razorpay', stripe: 'Stripe', paypal: 'PayPal' };
            return map[type] || (type ? type.charAt(0).toUpperCase() + type.slice(1) : '—');
        },
        typeVariant(type) {
            return {
                cod: 'neutral',
                razorpay: 'info',
                stripe: 'brand',
                paypal: 'brand',
            }[type] || 'neutral';
        },
        typeIcon(type) {
            return {
                cod: Banknote,
                razorpay: CreditCard,
                stripe: CreditCard,
                paypal: Wallet,
            }[type] || CreditCard;
        },
        statusVariant(status) {
            return {
                pending: 'warning',
                paid: 'success',
                failed: 'error',
                refunded: 'neutral',
                partially_refunded: 'warning',
            }[status] || 'neutral';
        },
        deletePayment(id) {
            this.isDeleted = true;
            this.deleteId = id;
        },
        deletePaymentConfirmed() {
            if (this.deleteId) {
                router.delete(route('admin.payment.delete', this.deleteId), { preserveScroll: true });
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
