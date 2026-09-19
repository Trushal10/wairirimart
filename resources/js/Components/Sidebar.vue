<template>
    <aside
        :class="[
            'fixed left-0 top-0 z-50 flex h-screen flex-col overflow-hidden bg-white border-r border-gray-200 dark:bg-[color:var(--color-canvas-dark)] dark:border-white/[0.06] transition-[width,transform] duration-200 ease-[cubic-bezier(0.4,0,0.2,1)]',
            // Mobile:  open → visible (260px), closed → off-canvas
            // Desktop: open → full 260px, closed → rail 64px (icons only)
            open
                ? 'w-[260px] translate-x-0'
                : 'w-[260px] -translate-x-full lg:w-16 lg:translate-x-0',
        ]"
    >
        <!-- Workspace header -->
        <div
            :class="[
                'flex items-center h-16 border-b border-gray-100 dark:border-white/[0.06] shrink-0',
                open ? 'justify-between gap-2 px-4' : 'justify-center px-2 lg:justify-center',
            ]"
        >
            <Link
                :href="route('admin.dashboard')"
                :title="storeName"
                :class="[
                    'flex items-center min-w-0 rounded-md py-1 hover:bg-gray-50 dark:hover:bg-white/[0.04] transition-colors',
                    open ? 'gap-2.5 -mx-1 px-1' : 'justify-center',
                ]"
            >
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-900 text-white dark:bg-white dark:text-gray-900">
                    <ShoppingBag :size="16" />
                </span>
                <span v-if="open" class="min-w-0 leading-tight">
                    <span class="block text-[13.5px] font-semibold text-gray-900 dark:text-white/95 truncate">
                        {{ storeName }}
                    </span>
                    <span class="block text-[10.5px] uppercase tracking-wider text-gray-400 dark:text-gray-500">
                        Admin
                    </span>
                </span>
            </Link>
            <button
                v-if="open"
                type="button"
                @click="$emit('close')"
                class="-mr-1 inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-500 hover:text-gray-800 hover:bg-gray-100 dark:hover:text-white/90 dark:hover:bg-white/[0.06] transition-colors focus-ring"
                aria-label="Close sidebar"
            >
                <X :size="16" />
            </button>
        </div>

        <!-- Nav -->
        <nav
            :class="[
                'flex-1 overflow-y-auto no-scrollbar py-4 space-y-5',
                open ? 'px-3' : 'px-2',
            ]"
        >
            <section v-for="group in groups" :key="group.title">
                <h3
                    v-if="open"
                    class="mb-1.5 px-2 text-[10.5px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500"
                >
                    {{ group.title }}
                </h3>
                <!-- Rail-mode divider between groups -->
                <div
                    v-else
                    class="mb-1.5 mx-2 border-t border-gray-100 dark:border-white/[0.06] first-of-type:mt-0 first-of-type:mb-1.5 first-of-type:border-t-0"
                    aria-hidden="true"
                ></div>

                <ul class="space-y-0.5">
                    <li v-for="item in group.items" :key="item.route">
                        <Link
                            :href="route(item.route)"
                            :title="item.label"
                            :class="[
                                'group relative flex items-center h-9 rounded-lg text-[13.5px] font-medium transition-colors',
                                open ? 'gap-2.5 pl-3 pr-2.5' : 'justify-center',
                                isActiveRoute(route(item.route))
                                    ? 'bg-gray-100 text-gray-900 dark:bg-white/[0.08] dark:text-white/95'
                                    : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50 dark:text-gray-400 dark:hover:text-white/95 dark:hover:bg-white/[0.04]',
                            ]"
                        >
                            <span
                                v-if="isActiveRoute(route(item.route))"
                                class="absolute left-0 top-1.5 bottom-1.5 w-[3px] rounded-r-full bg-brand-500"
                                aria-hidden="true"
                            ></span>
                            <component
                                :is="item.icon"
                                :size="16"
                                :class="[
                                    'shrink-0 transition-colors',
                                    isActiveRoute(route(item.route))
                                        ? 'text-brand-500 dark:text-brand-400'
                                        : 'text-gray-400 group-hover:text-gray-600 dark:text-gray-500 dark:group-hover:text-gray-300',
                                ]"
                            />
                            <template v-if="open">
                                <span class="flex-1 truncate">{{ item.label }}</span>
                                <span
                                    v-if="item.badge != null"
                                    :class="[
                                        'inline-flex items-center justify-center h-4 min-w-4 px-1 rounded-[5px] text-[10.5px] font-semibold',
                                        isActiveRoute(route(item.route))
                                            ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
                                            : 'bg-gray-100 text-gray-500 dark:bg-white/[0.08] dark:text-gray-400',
                                    ]"
                                >{{ item.badge }}</span>
                            </template>
                            <!-- Rail-mode: unread dot -->
                            <span
                                v-else-if="item.badge != null"
                                class="absolute top-1.5 right-1.5 h-1.5 w-1.5 rounded-full bg-brand-500"
                                aria-hidden="true"
                            ></span>
                        </Link>
                    </li>
                </ul>
            </section>
        </nav>

        <!-- Bottom cluster -->
        <div
            :class="[
                'mt-auto border-t border-gray-100 dark:border-white/[0.06] shrink-0',
                open ? 'px-3 py-3' : 'px-2 py-3',
            ]"
        >
            <a
                href="https://claude.ai/docs"
                target="_blank"
                rel="noopener"
                :title="'Help & docs'"
                :class="[
                    'group flex items-center h-9 rounded-lg text-[13px] font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-50 dark:text-gray-400 dark:hover:text-white/95 dark:hover:bg-white/[0.04] transition-colors',
                    open ? 'gap-2.5 px-3' : 'justify-center',
                ]"
            >
                <LifeBuoy :size="16" class="shrink-0 text-gray-400 group-hover:text-gray-600 dark:text-gray-500 dark:group-hover:text-gray-300" />
                <template v-if="open">
                    <span class="flex-1">Help &amp; docs</span>
                    <ExternalLink :size="12" class="text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity" />
                </template>
            </a>
            <div
                v-if="open"
                class="mt-1 flex items-center justify-between px-3 py-1.5"
            >
                <span class="text-[10.5px] uppercase tracking-wider text-gray-400 dark:text-gray-500">v{{ appVersion }}</span>
                <span class="inline-flex items-center gap-1 text-[10.5px] text-gray-400 dark:text-gray-500">
                    <span class="h-1.5 w-1.5 rounded-full bg-success-500"></span>
                    All systems normal
                </span>
            </div>
            <!-- Rail-mode: just the health dot -->
            <div
                v-else
                class="mt-2 flex items-center justify-center h-6"
                :title="`v${appVersion} · All systems normal`"
            >
                <span class="h-1.5 w-1.5 rounded-full bg-success-500"></span>
            </div>
        </div>
    </aside>
</template>

<script>
import { Link, usePage } from '@inertiajs/vue3';
import {
    LayoutDashboard,
    Image as ImageIcon,
    Clapperboard,
    FolderTree,
    Package,
    ShoppingCart,
    Undo2,
    CreditCard,
    UsersRound,
    TicketPercent,
    Mail,
    Newspaper,
    LineChart,
    ClipboardList,
    Wallet,
    Truck,
    Scale,
    Coins,
    Landmark,
    DatabaseZap,
    Settings2,
    ShoppingBag,
    LifeBuoy,
    ExternalLink,
    X,
} from '@lucide/vue';

const groups = [
    {
        title: 'Home',
        items: [
            { label: 'Dashboard', route: 'admin.dashboard', icon: LayoutDashboard },
        ],
    },
    {
        title: 'Catalog',
        items: [
            { label: 'Products',   route: 'admin.products',  icon: Package },
            { label: 'Categories', route: 'admin.category',  icon: FolderTree },
            { label: 'Sliders',    route: 'admin.sliders',   icon: ImageIcon },
            { label: 'Home videos', route: 'admin.home-videos', icon: Clapperboard },
        ],
    },
    {
        title: 'Sales',
        items: [
            { label: 'Orders',    route: 'admin.orders',           icon: ShoppingCart },
            { label: 'Customers', route: 'admin.customers.index',  icon: UsersRound },
            { label: 'Returns',   route: 'admin.returns.index',    icon: Undo2 },
            { label: 'Payments',  route: 'admin.payments',         icon: CreditCard },
        ],
    },
    {
        title: 'Marketing',
        items: [
            { label: 'Coupons',    route: 'admin.coupons.index', icon: TicketPercent },
            { label: 'Blog posts', route: 'admin.blogs.index',   icon: Newspaper },
            { label: 'Contacts',   route: 'admin.contacts',      icon: Mail },
        ],
    },
    {
        title: 'Analytics',
        items: [
            { label: 'Sales report',    route: 'admin.reports.sales',    icon: LineChart },
            { label: 'Orders report',   route: 'admin.reports.orders',   icon: ClipboardList },
            { label: 'Payments report', route: 'admin.reports.payments', icon: Wallet },
            { label: 'Shipping report', route: 'admin.reports.shipping', icon: Truck },
            { label: 'Profit & Loss',   route: 'admin.reports.pnl',      icon: Scale },
            { label: 'Expenses',        route: 'admin.expenses.index',   icon: Coins },
        ],
    },
    {
        title: 'Settings',
        items: [
            { label: 'General',          route: 'admin.setting',                 icon: Settings2 },
            { label: 'Payment gateways', route: 'admin.payment_gateways.index',  icon: Landmark },
            { label: 'Couriers',         route: 'admin.couriers.index',          icon: Truck },
            { label: 'Database',         route: 'admin.database.index',          icon: DatabaseZap },
        ],
    },
];

export default {
    components: {
        Link, ShoppingBag, LifeBuoy, ExternalLink, X,
    },
    props: {
        open: { type: Boolean, default: false },
        storeName: { type: String, default: 'Storefront' },
        appVersion: { type: String, default: '1.0' },
    },
    emits: ['close'],
    data() {
        return { groups };
    },
    computed: {
        currentUrl() { return usePage().url; },
    },
    methods: {
        isActiveRoute(url) {
            if (!url) return false;
            let urlPath, currentPath;
            try { urlPath = new URL(url).pathname; }
            catch (e) { urlPath = url; }
            try { currentPath = new URL(this.currentUrl, window.location.origin).pathname; }
            catch (e) { currentPath = this.currentUrl; }
            const norm = p => (p.length > 1 ? p.replace(/\/+$/, '') : p);
            urlPath = norm(urlPath);
            currentPath = norm(currentPath);
            if (urlPath === '/admin/dashboard' || urlPath === '/admin') {
                return currentPath === urlPath;
            }
            return currentPath === urlPath || currentPath.startsWith(urlPath + '/');
        },
    },
};
</script>
