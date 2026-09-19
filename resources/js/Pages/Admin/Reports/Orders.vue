<template>
    <Head title="Orders report" />

    <PageHeader
        title="Orders report"
        subtitle="Every order in the selected period with its status, payment, and shipment."
        :crumbs="crumbs"
    >
        <template #actions>
            <ExportButtons route-name="admin.reports.orders" :filters="filters" />
        </template>
    </PageHeader>

    <ReportTabs current="admin.reports.orders" />

    <FilterBar
        :filters="filters"
        :options="filterOptions"
        route-name="admin.reports.orders"
        :show="['status', 'payment_status', 'payment_type', 'courier_id', 'category_id', 'group', 'search']"
        search-placeholder="Order no, customer, phone, email…"
    />

    <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5 mb-6">
        <MetricTile label="Total orders" :value="summary.total" :previous="previousSummary.total" format="int" tone="neutral" :icon="I.total" />
        <MetricTile label="Pending" :value="summary.pending" :previous="previousSummary.pending" format="int" tone="warning" :icon="I.pending" />
        <MetricTile label="Confirmed" :value="summary.confirmed" :previous="previousSummary.confirmed" format="int" tone="brand" :icon="I.confirmed" />
        <MetricTile label="Delivered" :value="summary.delivered" :previous="previousSummary.delivered" format="int" tone="success" :icon="I.delivered" />
        <MetricTile label="Cancelled" :value="summary.canceled" :previous="previousSummary.canceled" format="int" tone="error" :icon="I.canceled" />
    </div>

    <Card mode="flat" title="Orders & revenue trend" :subtitle="`Grouped by ${filters.group || 'day'}.`" class="mb-6">
        <MultiSeriesChart :data="trend" :series="trendSeries" />
    </Card>

    <Card mode="flat" title="Orders" padding="none">
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
                        <th class="px-4 py-3 cursor-pointer" @click="sortBy('order_no')">
                            <span class="inline-flex items-center gap-1">Order # <component :is="sortIconFor('order_no')" :size="11" /></span>
                        </th>
                        <th class="px-4 py-3 cursor-pointer" @click="sortBy('created_at')">
                            <span class="inline-flex items-center gap-1">Placed <component :is="sortIconFor('created_at')" :size="11" /></span>
                        </th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3 text-right">Items</th>
                        <th class="px-4 py-3 text-right cursor-pointer" @click="sortBy('total')">
                            <span class="inline-flex items-center gap-1">Total <component :is="sortIconFor('total')" :size="11" /></span>
                        </th>
                        <th class="px-4 py-3 cursor-pointer" @click="sortBy('status')">
                            <span class="inline-flex items-center gap-1">Status <component :is="sortIconFor('status')" :size="11" /></span>
                        </th>
                        <th class="px-4 py-3">Payment</th>
                        <th class="px-4 py-3">Shipment</th>
                        <th class="px-4 py-3 text-right"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04] text-gray-700 dark:text-gray-300">
                    <tr v-if="!rows.data || !rows.data.length">
                        <td colspan="9" class="text-center py-14 text-body text-gray-500 dark:text-gray-400">
                            No orders match the current filters.
                        </td>
                    </tr>
                    <tr v-for="o in rows.data" :key="o.id" class="hover:bg-gray-50 dark:hover:bg-white/[0.02] transition-colors">
                        <td class="px-4 py-3.5">
                            <Link :href="route('admin.order.detail', o.id)" class="num-tabular text-body-strong text-gray-900 dark:text-white/95 hover:text-brand-600 dark:hover:text-brand-400">
                                #{{ o.order_no }}
                            </Link>
                        </td>
                        <td class="px-4 py-3.5 whitespace-nowrap num-tabular text-[12.5px]">{{ formatDate(o.created_at) }}</td>
                        <td class="px-4 py-3.5">
                            <div class="truncate max-w-[220px] text-body text-gray-900 dark:text-white/90">{{ o.shipping_name }}</div>
                            <div class="text-[11.5px] text-gray-500 dark:text-gray-400 num-tabular">{{ o.shipping_phone }}</div>
                        </td>
                        <td class="px-4 py-3.5 text-right num-tabular">{{ o.order_items_count }}</td>
                        <td class="px-4 py-3.5 text-right num-tabular text-body-strong text-gray-900 dark:text-white/95">₹{{ money(o.total) }}</td>
                        <td class="px-4 py-3.5">
                            <Badge :variant="statusVariant(o.status)" dot size="sm">{{ prettyStatus(o.status) }}</Badge>
                        </td>
                        <td class="px-4 py-3.5">
                            <div v-if="o.payment" class="flex flex-col gap-1">
                                <Badge :variant="paymentVariant(o.payment.status)" size="sm">{{ prettyStatus(o.payment.status) }}</Badge>
                                <span class="text-eyebrow text-gray-500 dark:text-gray-400">{{ o.payment.type }}</span>
                            </div>
                            <span v-else class="text-[12px] text-gray-400 italic">—</span>
                        </td>
                        <td class="px-4 py-3.5">
                            <div v-if="o.latest_shipment" class="flex flex-col gap-0.5">
                                <span class="text-[12.5px] text-gray-700 dark:text-gray-300 truncate">
                                    {{ o.latest_shipment.courier_name || o.latest_shipment.provider || '—' }}
                                </span>
                                <span v-if="o.latest_shipment.awb_code" class="text-[11.5px] text-gray-500 dark:text-gray-400 num-tabular">
                                    AWB · {{ o.latest_shipment.awb_code }}
                                </span>
                            </div>
                            <span v-else class="text-[12px] text-gray-400 italic">—</span>
                        </td>
                        <td class="px-4 py-3.5 text-right">
                            <Link :href="route('admin.order.detail', o.id)" class="text-[12px] font-medium text-brand-600 dark:text-brand-400 hover:underline">
                                Open →
                            </Link>
                        </td>
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
import SelectDropdown from '@/Components/reports/SelectDropdown.vue';
import Layout from '@/Layout/MainLayout.vue';
import { ChevronUp, ChevronDown, ChevronsUpDown } from '@lucide/vue';

const I = {
    total:     `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="14" height="14"><path d="M6 2l1.5 4h9L18 2M6 6h12l-1 15H7z"/></svg>`,
    pending:   `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="14" height="14"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>`,
    confirmed: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="14" height="14"><path d="M5 12l5 5L20 7"/></svg>`,
    delivered: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="14" height="14"><path d="M3 8h11v9H3zM14 11h4l3 3v3h-7"/></svg>`,
    canceled:  `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="14" height="14"><circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/></svg>`,
};

export default {
    layout: Layout,
    components: {
        Head, Link, PageHeader, Card, Badge, Pagination,
        ReportTabs, FilterBar, ExportButtons, MetricTile, MultiSeriesChart, SelectDropdown,
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
                { label: 'Orders' },
            ],
            trendSeries: [
                { key: 'revenue', label: 'Revenue', color: '#465FFF', type: 'line', format: 'money' },
                { key: 'orders_count', label: 'Orders', color: '#12B76A', type: 'bar', format: 'int' },
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
            return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
                + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        },
        prettyStatus(s) {
            if (!s) return '—';
            const raw = String(s).replaceAll('_', ' ');
            return raw.charAt(0).toUpperCase() + raw.slice(1);
        },
        statusVariant(status) {
            return {
                pending: 'warning',
                confirmed: 'brand',
                delivered: 'success',
                canceled: 'error',
                cancelled: 'error',
            }[status] || 'neutral';
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
            router.get(route('admin.reports.orders'), { ...this.filters, sort: field, dir, per_page: this.pp }, {
                preserveState: true, preserveScroll: true, replace: true,
            });
        },
        changePerPage() {
            router.get(route('admin.reports.orders'), { ...this.filters, per_page: this.pp, sort: this.sort.field, dir: this.sort.dir }, {
                preserveState: true, preserveScroll: true, replace: true,
            });
        },
    },
};
</script>
