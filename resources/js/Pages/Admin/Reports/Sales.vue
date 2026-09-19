<template>
    <Head title="Sales report" />

    <PageHeader
        title="Sales report"
        subtitle="Revenue, orders, and top performers across your selected period."
        :crumbs="crumbs"
    >
        <template #actions>
            <ExportButtons route-name="admin.reports.orders" :filters="filters" />
        </template>
    </PageHeader>

    <ReportTabs current="admin.reports.sales" />

    <FilterBar
        :filters="filters"
        :options="filterOptions"
        route-name="admin.reports.sales"
        :show="['status', 'payment_type', 'category_id', 'group', 'search']"
        search-placeholder="Order no, customer name or email…"
    />

    <!-- KPI Row -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4 mb-6">
        <MetricTile label="Revenue" :value="summary.revenue" :previous="previousSummary.revenue" format="money" tone="neutral" :icon="ICONS.money" />
        <MetricTile label="Orders" :value="summary.orders_count" :previous="previousSummary.orders_count" format="int" tone="brand" :icon="ICONS.orders" />
        <MetricTile label="Avg. order value" :value="summary.aov" :previous="previousSummary.aov" format="money" tone="neutral" :icon="ICONS.aov" />
        <MetricTile label="Customers" :value="summary.customers" :previous="previousSummary.customers" format="int" tone="success" :icon="ICONS.users" />
    </div>

    <!-- Chart -->
    <Card mode="flat" title="Revenue & orders over time" :subtitle="`Grouped by ${filters.group || 'day'}.`" class="mb-6">
        <MultiSeriesChart :data="trend" :series="trendSeries" />
    </Card>

    <!-- Sub-tables -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <Card mode="flat" title="Top products" subtitle="By revenue">
            <HBarList :rows="topProducts" label-key="name" value-key="revenue" sub-key="sku" format="money" />
        </Card>
        <Card mode="flat" title="Top categories" subtitle="By revenue">
            <HBarList :rows="topCategories" label-key="name" value-key="revenue" format="money" />
        </Card>
        <Card mode="flat" title="Top customers" subtitle="By revenue">
            <HBarList :rows="topCustomers" label-key="name" value-key="revenue" sub-key="email" format="money" />
        </Card>
    </div>
</template>

<script>
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import ReportTabs from '@/Components/reports/ReportTabs.vue';
import FilterBar from '@/Components/reports/FilterBar.vue';
import ExportButtons from '@/Components/reports/ExportButtons.vue';
import MetricTile from '@/Components/reports/MetricTile.vue';
import MultiSeriesChart from '@/Components/reports/MultiSeriesChart.vue';
import HBarList from '@/Components/reports/HBarList.vue';
import Layout from '@/Layout/MainLayout.vue';

const ICONS = {
    money: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="14" height="14"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>`,
    orders: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="14" height="14"><path d="M6 2l1.5 4h9L18 2M6 6h12l-1 15H7z"/></svg>`,
    aov: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="14" height="14"><path d="M3 3v18h18M7 15l4-4 3 3 5-6"/></svg>`,
    users: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="14" height="14"><circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0114 0M17 11a4 4 0 010-8M15 21a5 5 0 019-3"/></svg>`,
};

export default {
    layout: Layout,
    components: { Head, PageHeader, Card, ReportTabs, FilterBar, ExportButtons, MetricTile, MultiSeriesChart, HBarList },
    props: {
        filters: { type: Object, required: true },
        filterOptions: { type: Object, required: true },
        summary: { type: Object, required: true },
        previousSummary: { type: Object, required: true },
        trend: { type: Array, required: true },
        topProducts: { type: Array, required: true },
        topCategories: { type: Array, required: true },
        topCustomers: { type: Array, required: true },
    },
    data() {
        return {
            ICONS,
            crumbs: [
                { label: 'Dashboard', href: route('admin.dashboard') },
                { label: 'Reports' },
                { label: 'Sales' },
            ],
            trendSeries: [
                { key: 'revenue', label: 'Revenue', color: '#465FFF', type: 'line', format: 'money' },
                { key: 'orders_count', label: 'Orders', color: '#FDB022', type: 'bar', format: 'int' },
            ],
        };
    },
};
</script>
