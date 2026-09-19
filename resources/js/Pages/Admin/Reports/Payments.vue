<template>
    <Head title="Payment report" />

    <PageHeader
        title="Payment report"
        subtitle="Gross collections, refunds, gateway fees, and every payment transaction."
        :crumbs="crumbs"
    >
        <template #actions>
            <ExportButtons route-name="admin.reports.payments" :filters="filters" />
        </template>
    </PageHeader>

    <ReportTabs current="admin.reports.payments" />

    <FilterBar
        :filters="filters"
        :options="filterOptions"
        route-name="admin.reports.payments"
        :show="['payment_status', 'payment_type', 'status', 'group', 'search']"
        search-placeholder="Order no, customer name…"
    />

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <MetricTile label="Gross" :value="summary.gross" :previous="previousSummary.gross" format="money" tone="neutral" :icon="I.money" />
        <MetricTile label="Refunds" :value="summary.refunds" :previous="previousSummary.refunds" format="money" tone="error" :icon="I.refund" />
        <MetricTile label="Gateway fees" :value="summary.fees" :previous="previousSummary.fees" format="money" tone="warning" :icon="I.fees" />
        <MetricTile label="Net" :value="summary.net" :previous="previousSummary.net" format="money" tone="success" :icon="I.net" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <Card mode="flat" title="Cash flow trend" :subtitle="`Gross vs refunds vs fees by ${filters.group || 'day'}.`" class="lg:col-span-2">
            <MultiSeriesChart :data="trend" :series="trendSeries" />
        </Card>
        <Card mode="flat" title="By gateway">
            <HBarList
                :rows="summary.by_type"
                label-key="type"
                value-key="amount"
                sub-key="count"
                format="money"
                empty-text="No payments in range."
            />
            <div class="mt-5 pt-5 border-t border-gray-100 dark:border-white/[0.06] grid grid-cols-3 gap-3 text-center">
                <div>
                    <div class="text-eyebrow text-gray-500 dark:text-gray-400">Paid</div>
                    <div class="mt-1 text-h2 num-tabular text-success-700 dark:text-success-400">{{ summary.paid_count }}</div>
                </div>
                <div>
                    <div class="text-eyebrow text-gray-500 dark:text-gray-400">Failed</div>
                    <div class="mt-1 text-h2 num-tabular text-error-700 dark:text-error-400">{{ summary.failed_count }}</div>
                </div>
                <div>
                    <div class="text-eyebrow text-gray-500 dark:text-gray-400">Pending</div>
                    <div class="mt-1 text-h2 num-tabular text-warning-700 dark:text-warning-400">{{ summary.pending_count }}</div>
                </div>
            </div>
        </Card>
    </div>

    <Card mode="flat" title="Transactions" padding="none">
        <template #actions>
            <div class="flex items-center gap-3 text-[12px] text-gray-500 dark:text-gray-400">
                <span class="num-tabular"><strong class="text-gray-800 dark:text-white/90">{{ rows.total || 0 }}</strong> results</span>
                <div class="w-32">
                    <SelectDropdown
                        v-model="pp"
                        :options="perPageOptions"
                        :nullable="false"
                        :searchable="false"
                        @update:modelValue="changePerPage"
                    />
                </div>
            </div>
        </template>

        <div class="overflow-x-auto custom-scrollbar">
            <table class="min-w-full text-left text-[13.5px]">
                <thead class="bg-gray-50/60 dark:bg-white/[0.02] text-eyebrow text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-white/[0.06]">
                    <tr>
                        <th class="px-4 py-3">Payment ID</th>
                        <th class="px-4 py-3">Order</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3 cursor-pointer" @click="sortBy('type')">
                            <span class="inline-flex items-center gap-1">Gateway <component :is="sortIconFor('type')" :size="11" /></span>
                        </th>
                        <th class="px-4 py-3 cursor-pointer" @click="sortBy('status')">
                            <span class="inline-flex items-center gap-1">Status <component :is="sortIconFor('status')" :size="11" /></span>
                        </th>
                        <th class="px-4 py-3 text-right cursor-pointer" @click="sortBy('amount')">
                            <span class="inline-flex items-center gap-1">Amount <component :is="sortIconFor('amount')" :size="11" /></span>
                        </th>
                        <th class="px-4 py-3 text-right">Fee</th>
                        <th class="px-4 py-3 text-right">Refunded</th>
                        <th class="px-4 py-3 cursor-pointer" @click="sortBy('created_at')">
                            <span class="inline-flex items-center gap-1">Date <component :is="sortIconFor('created_at')" :size="11" /></span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04] text-gray-700 dark:text-gray-300">
                    <tr v-if="!rows.data || !rows.data.length">
                        <td colspan="9" class="text-center py-14 text-body text-gray-500 dark:text-gray-400">
                            No transactions match the current filters.
                        </td>
                    </tr>
                    <tr v-for="p in rows.data" :key="p.id" class="hover:bg-gray-50 dark:hover:bg-white/[0.02] transition-colors">
                        <td class="px-4 py-3.5 font-mono text-[12px] text-gray-700 dark:text-gray-300 num-tabular">{{ p.payment_id || '—' }}</td>
                        <td class="px-4 py-3.5">
                            <Link v-if="p.order" :href="route('admin.order.detail', p.order.id)" class="num-tabular text-body-strong text-gray-900 dark:text-white/95 hover:text-brand-600 dark:hover:text-brand-400">
                                #{{ p.order.order_no }}
                            </Link>
                            <span v-else class="text-[12px] text-gray-400 italic">—</span>
                        </td>
                        <td class="px-4 py-3.5">
                            <span class="truncate max-w-[180px] block text-body text-gray-800 dark:text-white/90">{{ p.order?.shipping_name || '—' }}</span>
                        </td>
                        <td class="px-4 py-3.5 text-eyebrow text-gray-600 dark:text-gray-400">{{ p.type }}</td>
                        <td class="px-4 py-3.5">
                            <Badge :variant="paymentVariant(p.status)" dot size="sm">{{ prettyStatus(p.status) }}</Badge>
                        </td>
                        <td class="px-4 py-3.5 text-right num-tabular text-body-strong text-gray-900 dark:text-white/95">₹{{ money(p.amount) }}</td>
                        <td class="px-4 py-3.5 text-right num-tabular text-warning-700 dark:text-warning-400">₹{{ money(p.gateway_fee) }}</td>
                        <td class="px-4 py-3.5 text-right num-tabular text-error-700 dark:text-error-400">₹{{ money(p.refunded_amount) }}</td>
                        <td class="px-4 py-3.5 whitespace-nowrap num-tabular text-[12.5px]">{{ formatDate(p.created_at) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <template #footer>
            <span class="text-[12px] text-gray-500 dark:text-gray-400 num-tabular">
                Page {{ rows.current_page }} of {{ rows.last_page }}
            </span>
            <Pagination :links="rows.links" />
        </template>
    </Card>
</template>

<script>
import { Head, Link, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Badge from '@/Components/ui/Badge.vue';
import Pagination from '@/Components/common/Pagination.vue';
import ReportTabs from '@/Components/reports/ReportTabs.vue';
import FilterBar from '@/Components/reports/FilterBar.vue';
import ExportButtons from '@/Components/reports/ExportButtons.vue';
import MetricTile from '@/Components/reports/MetricTile.vue';
import MultiSeriesChart from '@/Components/reports/MultiSeriesChart.vue';
import HBarList from '@/Components/reports/HBarList.vue';
import SelectDropdown from '@/Components/reports/SelectDropdown.vue';
import Layout from '@/Layout/MainLayout.vue';
import { ChevronUp, ChevronDown, ChevronsUpDown } from '@lucide/vue';

const I = {
    money:  `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="14" height="14"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>`,
    refund: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="14" height="14"><polyline points="1 4 1 10 7 10"/><path d="M3.5 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>`,
    fees:   `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="14" height="14"><path d="M6 3v18M6 7h12M6 15h9"/></svg>`,
    net:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="14" height="14"><path d="M3 3v18h18M7 15l4-4 3 3 5-6"/></svg>`,
};

export default {
    layout: Layout,
    components: {
        Head, Link, PageHeader, Card, Badge, Pagination,
        ReportTabs, FilterBar, ExportButtons, MetricTile, MultiSeriesChart, HBarList, SelectDropdown,
    },
    props: {
        filters: { type: Object, required: true },
        filterOptions: { type: Object, required: true },
        summary: { type: Object, required: true },
        previousSummary: { type: Object, required: true },
        trend: { type: Array, required: true },
        rows: { type: Object, required: true },
        sort: { type: Object, required: true },
        perPage: { type: Number, required: true },
    },
    data() {
        return {
            I,
            pp: this.perPage,
            crumbs: [
                { label: 'Dashboard', href: route('admin.dashboard') },
                { label: 'Reports' },
                { label: 'Payments' },
            ],
            trendSeries: [
                { key: 'gross',   label: 'Gross',   color: '#465FFF', type: 'line', format: 'money' },
                { key: 'refunds', label: 'Refunds', color: '#F04438', type: 'line', format: 'money', fill: false },
                { key: 'fees',    label: 'Fees',    color: '#F79009', type: 'line', format: 'money', fill: false },
            ],
            perPageOptions: [10, 25, 50, 100].map((n) => ({ value: n, label: `${n} / page` })),
        };
    },
    methods: {
        money(v) {
            return Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        formatDate(v) {
            if (!v) return '—';
            const d = new Date(v);
            return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' })
                + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        },
        prettyStatus(s) {
            if (!s) return '—';
            const raw = String(s).replaceAll('_', ' ');
            return raw.charAt(0).toUpperCase() + raw.slice(1);
        },
        paymentVariant(status) {
            return {
                paid: 'success',
                failed: 'error',
                pending: 'warning',
                partially_refunded: 'warning',
                refunded: 'neutral',
            }[status] || 'neutral';
        },
        sortIconFor(field) {
            if (this.sort.field !== field) return ChevronsUpDown;
            return this.sort.dir === 'asc' ? ChevronUp : ChevronDown;
        },
        sortBy(field) {
            const dir = this.sort.field === field && this.sort.dir === 'asc' ? 'desc' : 'asc';
            router.get(route('admin.reports.payments'), { ...this.filters, sort: field, dir, per_page: this.pp }, {
                preserveState: true, preserveScroll: true, replace: true,
            });
        },
        changePerPage() {
            router.get(route('admin.reports.payments'), { ...this.filters, per_page: this.pp, sort: this.sort.field, dir: this.sort.dir }, {
                preserveState: true, preserveScroll: true, replace: true,
            });
        },
    },
};
</script>
