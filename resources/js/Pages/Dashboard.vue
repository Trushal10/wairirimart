<template>
    <Head title="Dashboard" />

    <PageHeader :title="heroTitle" :subtitle="heroSubtitle">
        <template #titleBadge>
            <Badge variant="success" size="sm" dot>Live</Badge>
        </template>
        <template #actions>
            <Button variant="secondary" size="sm" tag="a" :href="route('admin.reports.sales')">
                <template #leading><LineChart :size="15" /></template>
                View reports
            </Button>
            <Button variant="primary" size="sm" tag="a" :href="route('admin.orders')">
                <template #leading><ShoppingCart :size="15" /></template>
                All orders
            </Button>
        </template>
    </PageHeader>

    <div class="space-y-6">
        <!-- KPI grid -->
        <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <KpiCard
                label="Revenue · 30 days"
                :value="`₹${money(kpis.revenue_last_30)}`"
                :delta="kpis.revenue_delta_pct"
                icon="revenue"
                :series="revenueSparkData"
                hint="Confirmed & delivered orders"
            />
            <KpiCard
                label="Orders · 30 days"
                :value="kpis.orders_last_30"
                :delta="kpis.orders_delta_pct"
                icon="cart"
                :series="ordersSparkData"
                hint="All order statuses"
            />
            <KpiCard
                label="Avg. order value"
                :value="`₹${money(kpis.avg_order_value)}`"
                icon="tag"
                hint="Revenue ÷ orders (30d)"
            />
            <KpiCard
                label="Refunded · 30 days"
                :value="`₹${money(kpis.refunded_last_30)}`"
                icon="refund"
                hint="Confirmed refunds on payments"
            />
        </section>

        <!-- Revenue chart + status donut -->
        <section class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            <Card mode="flat" title="Revenue & orders" subtitle="Last 30 days · confirmed / delivered only" class="xl:col-span-2">
                <template #actions>
                    <div class="flex items-center gap-4 text-[12px]">
                        <span class="inline-flex items-center gap-1.5 text-gray-600 dark:text-gray-400">
                            <span class="inline-block h-2 w-3 rounded-sm bg-brand-500"></span>
                            Revenue
                        </span>
                        <span class="inline-flex items-center gap-1.5 text-gray-600 dark:text-gray-400">
                            <span class="inline-block h-2 w-3 rounded-sm bg-warning-400"></span>
                            Orders
                        </span>
                    </div>
                </template>
                <div v-if="revenueSeries.length">
                    <RevenueChart :series="revenueSeries" />
                </div>
                <EmptyState
                    v-else
                    size="sm"
                    title="No revenue data yet"
                    description="Once orders start flowing in, the trend will render here."
                />
            </Card>

            <Card mode="flat" title="Order status" subtitle="All-time breakdown">
                <StatusDonut v-if="hasStatusData" :breakdown="statusBreakdown" />
                <EmptyState
                    v-else
                    size="sm"
                    title="Nothing to show"
                    description="No orders yet — this donut fills once orders come in."
                />
            </Card>
        </section>

        <!-- Low-stock notice (compact) -->
        <section v-if="lowStock.length">
            <Card mode="flat" padding="none" class="border-warning-200/70 dark:border-warning-500/25 bg-warning-50/40 dark:bg-warning-500/[0.06]">
                <div class="flex flex-col gap-4 p-5 md:flex-row md:items-start md:justify-between">
                    <div class="flex items-start gap-3 min-w-0">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-300">
                            <AlertTriangle :size="17" />
                        </span>
                        <div class="min-w-0">
                            <div class="text-h3 text-gray-900 dark:text-white/95">
                                {{ lowStock.length }} product{{ lowStock.length !== 1 ? 's' : '' }} running low
                            </div>
                            <p class="mt-0.5 text-[13px] text-gray-600 dark:text-gray-400">
                                Restock soon to avoid oversells. Threshold applies to active products only.
                            </p>
                        </div>
                    </div>
                    <Button variant="secondary" size="sm" tag="a" :href="route('admin.products')" class="shrink-0">
                        Manage inventory
                        <template #trailing><ArrowRight :size="14" /></template>
                    </Button>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 border-t border-warning-200/70 dark:border-warning-500/20 divide-y sm:divide-y-0 sm:divide-x divide-warning-200/70 dark:divide-warning-500/20">
                    <Link
                        v-for="p in lowStock.slice(0, 4)"
                        :key="p.id"
                        :href="route('admin.product.edit', p.id)"
                        class="group flex items-center justify-between gap-3 px-5 py-3.5 hover:bg-warning-100/50 dark:hover:bg-warning-500/10 transition-colors"
                    >
                        <div class="min-w-0">
                            <div class="text-[13.5px] font-medium text-gray-900 dark:text-white/90 truncate">{{ p.name }}</div>
                            <div class="text-[11.5px] text-gray-500 dark:text-gray-400 mt-0.5 truncate">SKU · {{ p.sku || '—' }}</div>
                        </div>
                        <div class="shrink-0 text-right">
                            <Badge :variant="stockVariant(p.level)" size="sm" dot>
                                {{ p.stock }} left
                            </Badge>
                        </div>
                    </Link>
                </div>
            </Card>
        </section>

        <!-- Recent orders + Top products -->
        <section class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            <Card mode="flat" title="Recent orders" subtitle="Latest activity across your store" class="xl:col-span-2" padding="none">
                <template #actions>
                    <Link :href="route('admin.orders')" class="inline-flex items-center gap-1 text-[12.5px] font-medium text-gray-700 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white/95 transition-colors">
                        View all
                        <ArrowRight :size="12" />
                    </Link>
                </template>
                <div v-if="recentOrders.length" class="divide-y divide-gray-100 dark:divide-white/[0.05]">
                    <Link
                        v-for="o in recentOrders"
                        :key="o.id"
                        :href="route('admin.order.detail', o.id)"
                        class="group flex items-center gap-3 px-5 py-3.5 hover:bg-gray-50 dark:hover:bg-white/[0.02] transition-colors"
                    >
                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-white text-[11px] font-semibold"
                            :style="{ background: avatarColor(o.name) }"
                            aria-hidden="true"
                        >
                            {{ initials(o.name) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="text-body-strong text-gray-900 dark:text-white/95 truncate">
                                    {{ o.name || 'Unknown customer' }}
                                </span>
                                <Badge :variant="statusVariant(o.status)" size="sm" dot>{{ prettyStatus(o.status) }}</Badge>
                            </div>
                            <div class="mt-0.5 flex items-center gap-2 text-[12px] text-gray-500 dark:text-gray-400">
                                <span class="font-medium text-gray-600 dark:text-gray-300 num-tabular">#{{ o.order_no }}</span>
                                <span>·</span>
                                <span>{{ timeAgo(o.created_at) }}</span>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="text-body-strong num-tabular text-gray-900 dark:text-white/95">₹{{ money(o.total) }}</div>
                        </div>
                        <ChevronRight :size="14" class="shrink-0 text-gray-300 dark:text-gray-600 group-hover:text-gray-500 dark:group-hover:text-gray-400 transition-colors" />
                    </Link>
                </div>
                <EmptyState
                    v-else
                    size="sm"
                    title="No orders yet"
                    description="New orders will appear here as they come in."
                />
            </Card>

            <Card mode="flat" title="Top products" subtitle="By units sold on paid orders" padding="none">
                <template #actions>
                    <Link :href="route('admin.reports.orders')" class="inline-flex items-center gap-1 text-[12.5px] font-medium text-gray-700 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white/95 transition-colors">
                        Report
                        <ArrowRight :size="12" />
                    </Link>
                </template>
                <div v-if="topProducts.length" class="p-2">
                    <div
                        v-for="(p, i) in topProducts"
                        :key="p.id"
                        class="flex items-center gap-3 rounded-lg px-3 py-2.5 hover:bg-gray-50 dark:hover:bg-white/[0.02] transition-colors"
                    >
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-gray-100 dark:bg-white/[0.06] text-[11.5px] font-semibold text-gray-600 dark:text-gray-400 num-tabular">
                            {{ i + 1 }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="text-[13.5px] font-medium text-gray-900 dark:text-white/95 truncate">{{ p.name }}</div>
                            <div class="mt-1.5 flex items-center gap-3">
                                <div class="flex-1 h-1 bg-gray-100 dark:bg-white/[0.06] rounded-full overflow-hidden">
                                    <div class="h-full bg-gray-900 dark:bg-white rounded-full transition-[width] duration-500" :style="{ width: unitsPct(p) + '%' }"></div>
                                </div>
                                <span class="text-[11.5px] text-gray-500 dark:text-gray-400 num-tabular shrink-0">{{ p.units_sold }} sold</span>
                            </div>
                        </div>
                    </div>
                </div>
                <EmptyState
                    v-else
                    size="sm"
                    title="No sales yet"
                    description="Your best-selling products will show here."
                />
            </Card>
        </section>

        <!-- Quick actions -->
        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <QuickAction
                title="Add product"
                subtitle="Grow your catalog"
                :icon="Package"
                :href="route('admin.products.create')"
            />
            <QuickAction
                title="Create coupon"
                subtitle="Launch a promo"
                :icon="TicketPercent"
                :href="route('admin.coupons.create')"
            />
            <QuickAction
                title="Add category"
                subtitle="Organize catalog"
                :icon="FolderTree"
                :href="route('admin.category.create')"
            />
            <QuickAction
                title="P&L report"
                subtitle="See profitability"
                :icon="Scale"
                :href="route('admin.reports.pnl')"
            />
        </section>
    </div>
</template>

<script>
import { Head, Link } from '@inertiajs/vue3';
import Layout from '@/Layout/MainLayout.vue';
import KpiCard from '@/Components/dashboard/KpiCard.vue';
import RevenueChart from '@/Components/dashboard/RevenueChart.vue';
import StatusDonut from '@/Components/dashboard/StatusDonut.vue';
import { PageHeader, Card, Button, Badge, EmptyState } from '@/Components/ui';
import {
    LineChart, ShoppingCart, ArrowRight, ChevronRight,
    AlertTriangle, Package, TicketPercent, FolderTree, Scale,
} from '@lucide/vue';

// Lightweight quick-action tile used only on this page.
const QuickAction = {
    props: {
        title: String,
        subtitle: String,
        icon: { type: [Object, Function], required: true },
        href: String,
    },
    components: { Link, ArrowRight },
    template: `
        <Link
            :href="href"
            class="group flex items-center gap-3 rounded-2xl border border-gray-200 dark:border-white/[0.06] bg-white dark:bg-[color:var(--color-surface-dark)] p-4 hover:border-gray-300 dark:hover:border-white/[0.14] hover:shadow-elevation-2 transition-all"
        >
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gray-100 dark:bg-white/[0.06] text-gray-700 dark:text-gray-300 group-hover:bg-gray-900 group-hover:text-white dark:group-hover:bg-white dark:group-hover:text-gray-900 transition-colors">
                <component :is="icon" :size="18" />
            </span>
            <div class="min-w-0 flex-1">
                <div class="text-[13.5px] font-semibold text-gray-900 dark:text-white/95 truncate">{{ title }}</div>
                <div class="text-[11.5px] text-gray-500 dark:text-gray-400 truncate">{{ subtitle }}</div>
            </div>
            <ArrowRight :size="14" class="shrink-0 text-gray-300 dark:text-gray-600 group-hover:text-gray-700 dark:group-hover:text-gray-300 transition-colors" />
        </Link>
    `,
};

const AVATAR_COLORS = [
    '#3641F5', // brand-600
    '#12B76A', // success-500
    '#F79009', // warning-500
    '#0BA5EC', // blue-light-500
    '#7A5AF8', // theme-purple-500
    '#EE46BC', // theme-pink-500
    '#F04438', // error-500
];

function hashString(s = '') {
    let h = 0;
    for (let i = 0; i < s.length; i++) h = ((h << 5) - h + s.charCodeAt(i)) | 0;
    return Math.abs(h);
}

export default {
    layout: Layout,
    components: {
        Head, Link,
        KpiCard, RevenueChart, StatusDonut,
        PageHeader, Card, Button, Badge, EmptyState,
        LineChart, ShoppingCart, ArrowRight, ChevronRight, AlertTriangle,
        QuickAction,
    },
    props: {
        data: Object,
        user: { type: Object, default: () => ({}) },
    },
    setup() {
        // Provided to template scope for QuickAction icon slot bindings.
        return { Package, TicketPercent, FolderTree, Scale };
    },
    computed: {
        kpis() { return this.data?.kpis || {}; },
        revenueSeries() { return this.data?.revenue_series || []; },
        revenueSparkData() {
            return (this.data?.revenue_series || []).map((p) => Number(p.revenue ?? 0));
        },
        ordersSparkData() {
            return (this.data?.revenue_series || []).map((p) => Number(p.orders ?? 0));
        },
        statusBreakdown() { return this.data?.status_breakdown || {}; },
        hasStatusData() {
            return Object.values(this.statusBreakdown).reduce((s, v) => s + Number(v || 0), 0) > 0;
        },
        topProducts() { return this.data?.top_products || []; },
        recentOrders() { return this.data?.recent_orders || []; },
        lowStock() { return this.data?.low_stock || []; },
        topSoldUnits() {
            return Math.max(1, ...this.topProducts.map((p) => p.units_sold || 0));
        },
        userName() {
            const n = this.user?.name || '';
            return n.split(' ')[0];
        },
        heroTitle() {
            const g = this.greeting();
            return this.userName ? `${g}, ${this.userName}` : g;
        },
        heroSubtitle() {
            return `Here's what's happening in your store today · ${this.todayLabel()}`;
        },
    },
    methods: {
        greeting() {
            const h = new Date().getHours();
            if (h < 5) return 'Working late';
            if (h < 12) return 'Good morning';
            if (h < 17) return 'Good afternoon';
            if (h < 21) return 'Good evening';
            return 'Good night';
        },
        todayLabel() {
            return new Date().toLocaleDateString('en-IN', {
                weekday: 'long', day: '2-digit', month: 'long', year: 'numeric',
            });
        },
        money(v) {
            const n = Number(v || 0);
            return n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        unitsPct(p) {
            return Math.round(((p.units_sold || 0) / this.topSoldUnits) * 100);
        },
        statusVariant(status) {
            const map = {
                pending: 'warning',
                confirmed: 'brand',
                shipped: 'info',
                delivered: 'success',
                completed: 'success',
                canceled: 'error',
                cancelled: 'error',
                refunded: 'neutral',
            };
            return map[status] || 'neutral';
        },
        prettyStatus(s) {
            if (!s) return '—';
            const map = { canceled: 'Cancelled' };
            return map[s] || s.charAt(0).toUpperCase() + s.slice(1);
        },
        stockVariant(level) {
            return level === 'out' ? 'error' : level === 'critical' ? 'warning' : 'warning';
        },
        initials(name) {
            const n = (name || '').trim();
            if (!n) return '·';
            const parts = n.split(/\s+/).filter(Boolean);
            const first = parts[0]?.[0] || '';
            const last = parts.length > 1 ? parts[parts.length - 1][0] : '';
            return (first + last).toUpperCase() || first.toUpperCase();
        },
        avatarColor(name) {
            return AVATAR_COLORS[hashString(name || '') % AVATAR_COLORS.length];
        },
        timeAgo(iso) {
            if (!iso) return '';
            const then = new Date(iso).getTime();
            const now = Date.now();
            const s = Math.floor((now - then) / 1000);
            if (s < 60) return `${s}s ago`;
            const m = Math.floor(s / 60);
            if (m < 60) return `${m}m ago`;
            const h = Math.floor(m / 60);
            if (h < 24) return `${h}h ago`;
            const d = Math.floor(h / 24);
            if (d < 7) return `${d}d ago`;
            return new Date(iso).toLocaleDateString('en-IN', { day: '2-digit', month: 'short' });
        },
    },
};
</script>
