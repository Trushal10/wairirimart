<template>
    <Head title="Shipping report" />

    <PageHeader
        title="Shipping report"
        subtitle="Shipment throughput, delivery performance, and courier mix."
        :crumbs="crumbs"
    >
        <template #actions>
            <ExportButtons route-name="admin.reports.shipping" :filters="filters" />
        </template>
    </PageHeader>

    <ReportTabs current="admin.reports.shipping" />

    <FilterBar
        :filters="filters"
        :options="filterOptions"
        route-name="admin.reports.shipping"
        :show="['courier_id', 'status', 'group', 'search']"
        search-placeholder="Order no, customer, phone…"
    />

    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4 mb-6">
        <MetricTile label="Shipments" :value="summary.shipments" :previous="previousSummary.shipments" format="int" tone="neutral" :icon="I.ship" />
        <MetricTile label="Delivered" :value="summary.delivered" :previous="previousSummary.delivered" format="int" tone="success" :icon="I.done" />
        <MetricTile label="In transit" :value="summary.in_transit" :previous="previousSummary.in_transit" format="int" tone="brand" :icon="I.trans" />
        <MetricTile label="Pending" :value="summary.pending" :previous="previousSummary.pending" format="int" tone="warning" :icon="I.pend" />
        <MetricTile label="RTO" :value="summary.rto" :previous="previousSummary.rto" format="int" tone="error" :icon="I.rto" />
        <MetricTile label="Weight (kg)" :value="summary.weight_kg" :previous="previousSummary.weight_kg" format="number" tone="neutral" :icon="I.weight" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <Card mode="flat" title="Dispatch & delivery trend" :subtitle="`Grouped by ${filters.group || 'day'}.`" class="lg:col-span-2">
            <MultiSeriesChart :data="trend" :series="trendSeries" />
        </Card>

        <Card mode="flat" title="By courier">
            <div v-if="!byCourier.length" class="py-8 text-center text-body text-gray-500 dark:text-gray-400">
                No shipments in range.
            </div>
            <div v-for="c in byCourier" :key="c.provider" class="mb-4 last:mb-0">
                <div class="mb-1.5 flex items-center justify-between text-[13px]">
                    <span class="text-body-strong text-gray-900 dark:text-white/95 capitalize">{{ c.courier_name || c.provider }}</span>
                    <span class="num-tabular text-body-strong text-gray-900 dark:text-white/95">{{ c.total }}</span>
                </div>
                <div class="grid grid-cols-2 gap-2 text-[11.5px] num-tabular">
                    <span class="inline-flex items-center gap-1.5 text-gray-600 dark:text-gray-400">
                        <span class="h-1.5 w-1.5 rounded-full bg-success-500"></span>
                        Delivered · {{ c.delivered }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-gray-600 dark:text-gray-400">
                        <span class="h-1.5 w-1.5 rounded-full bg-error-500"></span>
                        RTO · {{ c.rto }}
                    </span>
                </div>
                <div class="relative mt-2 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-white/[0.05]">
                    <div class="absolute inset-y-0 left-0 bg-success-500 rounded-full transition-all duration-500" :style="{ width: pctDelivered(c) + '%' }"></div>
                </div>
            </div>
        </Card>
    </div>

    <Card mode="flat" title="Shipments" padding="none">
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
                        <th class="px-4 py-3">Order</th>
                        <th class="px-4 py-3">Courier</th>
                        <th class="px-4 py-3">AWB</th>
                        <th class="px-4 py-3 cursor-pointer" @click="sortBy('status')">
                            <span class="inline-flex items-center gap-1">Status <component :is="sortIconFor('status')" :size="11" /></span>
                        </th>
                        <th class="px-4 py-3">Destination</th>
                        <th class="px-4 py-3 text-right">Weight</th>
                        <th class="px-4 py-3 cursor-pointer" @click="sortBy('shipped_at')">
                            <span class="inline-flex items-center gap-1">Shipped <component :is="sortIconFor('shipped_at')" :size="11" /></span>
                        </th>
                        <th class="px-4 py-3 cursor-pointer" @click="sortBy('delivered_at')">
                            <span class="inline-flex items-center gap-1">Delivered <component :is="sortIconFor('delivered_at')" :size="11" /></span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04] text-gray-700 dark:text-gray-300">
                    <tr v-if="!rows.data || !rows.data.length">
                        <td colspan="8" class="text-center py-14 text-body text-gray-500 dark:text-gray-400">
                            No shipments match the current filters.
                        </td>
                    </tr>
                    <tr v-for="s in rows.data" :key="s.id" class="hover:bg-gray-50 dark:hover:bg-white/[0.02] transition-colors">
                        <td class="px-4 py-3.5">
                            <Link v-if="s.order" :href="route('admin.order.detail', s.order.id)" class="num-tabular text-body-strong text-gray-900 dark:text-white/95 hover:text-brand-600 dark:hover:text-brand-400">
                                #{{ s.order.order_no }}
                            </Link>
                            <span v-else class="text-[12px] text-gray-400 italic">—</span>
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="text-body text-gray-800 dark:text-white/90">{{ s.courier_name || s.provider || '—' }}</div>
                            <div class="text-eyebrow text-gray-500 dark:text-gray-400">{{ s.provider }}</div>
                        </td>
                        <td class="px-4 py-3.5 font-mono text-[12px] num-tabular">
                            <a v-if="s.tracking_url" :href="s.tracking_url" target="_blank" rel="noopener" class="text-brand-600 dark:text-brand-400 hover:underline">{{ s.awb_code || '—' }}</a>
                            <span v-else class="text-gray-700 dark:text-gray-300">{{ s.awb_code || '—' }}</span>
                        </td>
                        <td class="px-4 py-3.5">
                            <Badge :variant="statusVariant(s.status)" dot size="sm">{{ prettyStatus(s.status) }}</Badge>
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="truncate max-w-[160px] text-body text-gray-800 dark:text-white/90">{{ s.order?.shipping_city || '—' }}</div>
                            <div class="text-[11.5px] text-gray-400 num-tabular">{{ s.order?.shipping_pincode || '' }}</div>
                        </td>
                        <td class="px-4 py-3.5 text-right num-tabular">{{ s.weight ? Number(s.weight).toFixed(2) : '—' }}</td>
                        <td class="px-4 py-3.5 whitespace-nowrap num-tabular text-[12.5px]">{{ formatDate(s.shipped_at) }}</td>
                        <td class="px-4 py-3.5 whitespace-nowrap num-tabular text-[12.5px]">{{ formatDate(s.delivered_at) }}</td>
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
    ship:   `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="14" height="14"><path d="M3 8h11v9H3zM14 11h4l3 3v3h-7"/><circle cx="7" cy="19" r="1.6"/><circle cx="18" cy="19" r="1.6"/></svg>`,
    done:   `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="14" height="14"><path d="M5 12l5 5L20 7"/></svg>`,
    trans:  `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="14" height="14"><path d="M3 12h18M13 6l6 6-6 6"/></svg>`,
    pend:   `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="14" height="14"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>`,
    rto:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="14" height="14"><path d="M9 14l-5-5 5-5M4 9h11a5 5 0 015 5v6"/></svg>`,
    weight: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="14" height="14"><path d="M6 2h12l3 20H3zM8 8h8"/></svg>`,
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
        byCourier: { type: Array, required: true },
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
                { label: 'Shipping' },
            ],
            trendSeries: [
                { key: 'c', label: 'Total', color: '#465FFF', type: 'bar', format: 'int' },
                { key: 'delivered', label: 'Delivered', color: '#12B76A', type: 'line', format: 'int' },
            ],
            perPageOptions: [10, 25, 50, 100].map((n) => ({ value: n, label: `${n} / page` })),
        };
    },
    methods: {
        pctDelivered(c) {
            const t = Number(c.total) || 0;
            if (!t) return 0;
            return Math.round((Number(c.delivered) || 0) / t * 100);
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
        statusVariant(status) {
            return {
                delivered: 'success',
                in_transit: 'info',
                out_for_delivery: 'brand',
                picked_up: 'brand',
                pickup_scheduled: 'brand',
                awb_assigned: 'brand',
                pending: 'warning',
                rto_initiated: 'error',
                rto_delivered: 'error',
                undelivered: 'error',
                cancelled: 'neutral',
            }[status] || 'neutral';
        },
        sortIconFor(field) {
            if (this.sort.field !== field) return ChevronsUpDown;
            return this.sort.dir === 'asc' ? ChevronUp : ChevronDown;
        },
        sortBy(field) {
            const dir = this.sort.field === field && this.sort.dir === 'asc' ? 'desc' : 'asc';
            router.get(route('admin.reports.shipping'), { ...this.filters, sort: field, dir, per_page: this.pp }, {
                preserveState: true, preserveScroll: true, replace: true,
            });
        },
        changePerPage() {
            router.get(route('admin.reports.shipping'), { ...this.filters, per_page: this.pp, sort: this.sort.field, dir: this.sort.dir }, {
                preserveState: true, preserveScroll: true, replace: true,
            });
        },
    },
};
</script>
