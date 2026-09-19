<template>
    <Head title="Profit & loss report" />

    <PageHeader
        title="Profit & loss"
        subtitle="Full P&L for the selected period with revenue, cost, gateway fees, refunds, and margin."
        :crumbs="crumbs"
    >
        <template #actions>
            <Button variant="secondary" size="sm" tag="a" :href="route('admin.expenses.index')">
                <template #leading><Coins :size="14" /></template>
                Operating expenses
            </Button>
            <ExportButtons route-name="admin.reports.pnl" :filters="filters" />
        </template>
    </PageHeader>

    <ReportTabs current="admin.reports.pnl" />

    <FilterBar
        :filters="filters"
        :options="filterOptions"
        route-name="admin.reports.pnl"
        :show="['status', 'category_id', 'group']"
    />

    <!-- Top KPI cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <MetricTile label="Revenue" :value="pnl.revenue" :previous="previousPnl.revenue" format="money" tone="neutral" :icon="ICON.rev" />
        <MetricTile label="Gross profit" :value="pnl.gross_profit" :previous="previousPnl.gross_profit" format="money" tone="brand" :icon="ICON.gross" />
        <MetricTile label="Net profit" :value="pnl.net_profit" :previous="previousPnl.net_profit" format="money"
            :tone="pnl.net_profit >= 0 ? 'success' : 'error'" :icon="ICON.net"
            :value-class="pnl.net_profit >= 0 ? 'text-success-700 dark:text-success-400' : 'text-error-700 dark:text-error-400'" />
        <MetricTile label="Profit margin" :value="pnl.margin_pct" :previous="previousPnl.margin_pct" format="percent"
            :tone="pnl.margin_pct >= 0 ? 'success' : 'error'" :icon="ICON.margin" />
    </div>

    <!-- Cost buckets -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <MetricTile label="COGS" :value="pnl.cogs" :previous="previousPnl.cogs" format="money" tone="warning" :icon="ICON.box" />
        <MetricTile label="Discounts" :value="pnl.discounts" :previous="previousPnl.discounts" format="money" tone="warning" :icon="ICON.disc" />
        <MetricTile label="Gateway fees" :value="pnl.gateway_fees" :previous="previousPnl.gateway_fees" format="money" tone="warning" :icon="ICON.card" />
        <MetricTile label="Refunds" :value="pnl.refunds" :previous="previousPnl.refunds" format="money" tone="error" :icon="ICON.ret" />
    </div>

    <!-- Chart -->
    <Card mode="flat" title="Revenue vs discounts & shipping" :subtitle="`Grouped by ${filters.group || 'day'}.`" class="mb-6">
        <MultiSeriesChart :data="trend" :series="trendSeries" />
    </Card>

    <!-- Breakdown -->
    <PnlBreakdown :pnl="pnl" />

    <!-- Notes -->
    <Alert variant="warning" title="How this P&L is calculated" class="mt-6">
        <ul class="list-disc pl-5 space-y-1 text-[12.5px]">
            <li><b>COGS</b> uses <code class="bg-warning-100 dark:bg-warning-500/20 px-1 rounded text-[11px]">product_variants.cost_price</code> (falls back to <code class="bg-warning-100 dark:bg-warning-500/20 px-1 rounded text-[11px]">products.cost_price</code>, then 0). Add cost prices in the product editor for accurate profit.</li>
            <li><b>Gateway fees</b> uses actual <code class="bg-warning-100 dark:bg-warning-500/20 px-1 rounded text-[11px]">payments.gateway_fee</code>; missing values are estimated at
                <b class="num-tabular">{{ (pnl.assumptions.default_gateway_fee_rate * 100).toFixed(2) }}%</b> of paid amount
                (configurable via <code class="bg-warning-100 dark:bg-warning-500/20 px-1 rounded text-[11px]">services.reports.default_gateway_fee_rate</code>).</li>
            <li><b>Shipping cost</b> = charged shipping × <b class="num-tabular">{{ pnl.assumptions.shipping_cost_ratio }}</b>. Set the ratio via
                <code class="bg-warning-100 dark:bg-warning-500/20 px-1 rounded text-[11px]">services.reports.shipping_cost_ratio</code> when your courier bills differ.</li>
            <li><b>Taxes collected</b> is a pass-through liability — informational only, not deducted from profit.</li>
        </ul>
    </Alert>
</template>

<script>
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import Alert from '@/Components/ui/Alert.vue';
import ReportTabs from '@/Components/reports/ReportTabs.vue';
import FilterBar from '@/Components/reports/FilterBar.vue';
import ExportButtons from '@/Components/reports/ExportButtons.vue';
import MetricTile from '@/Components/reports/MetricTile.vue';
import MultiSeriesChart from '@/Components/reports/MultiSeriesChart.vue';
import PnlBreakdown from '@/Components/reports/PnlBreakdown.vue';
import Layout from '@/Layout/MainLayout.vue';
import { Coins } from '@lucide/vue';

const ICON = {
    rev:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="14" height="14"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>`,
    gross:  `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="14" height="14"><path d="M3 3v18h18M7 15l4-4 3 3 5-6"/></svg>`,
    net:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="14" height="14"><path d="M6 12l4-4 4 4 4-6"/><path d="M2 21h20"/></svg>`,
    margin: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="14" height="14"><path d="M19 5L5 19"/><circle cx="7" cy="7" r="2.5"/><circle cx="17" cy="17" r="2.5"/></svg>`,
    box:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="14" height="14"><path d="M3.5 7.5L12 3l8.5 4.5v9L12 21l-8.5-4.5v-9z"/><path d="M3.5 7.5L12 12l8.5-4.5M12 12v9"/></svg>`,
    disc:   `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="14" height="14"><path d="M20 12l-8 8L2 10V2h8z"/><circle cx="7" cy="7" r="1"/></svg>`,
    card:   `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="14" height="14"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/></svg>`,
    ret:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="14" height="14"><polyline points="1 4 1 10 7 10"/><path d="M3.5 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>`,
};

export default {
    layout: Layout,
    components: {
        Head, Link, PageHeader, Card, Button, Alert,
        ReportTabs, FilterBar, ExportButtons, MetricTile, MultiSeriesChart, PnlBreakdown,
        Coins,
    },
    props: {
        filters: { type: Object, required: true },
        filterOptions: { type: Object, required: true },
        pnl: { type: Object, required: true },
        previousPnl: { type: Object, required: true },
        trend: { type: Array, required: true },
    },
    data() {
        return {
            ICON,
            crumbs: [
                { label: 'Dashboard', href: route('admin.dashboard') },
                { label: 'Reports' },
                { label: 'Profit & loss' },
            ],
            trendSeries: [
                { key: 'revenue',          label: 'Revenue',   color: '#12B76A', type: 'line', format: 'money' },
                { key: 'discounts',        label: 'Discounts', color: '#F79009', type: 'bar',  format: 'money' },
                { key: 'shipping_revenue', label: 'Shipping',  color: '#465FFF', type: 'bar',  format: 'money' },
            ],
        };
    },
};
</script>
