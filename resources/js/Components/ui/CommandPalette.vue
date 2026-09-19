<template>
    <teleport to="body">
        <div
            v-if="state.open"
            class="fixed inset-0 z-99999 flex items-start justify-center pt-[10vh] px-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="cmdk-title"
            @click.self="close"
        >
            <div
                class="absolute inset-0 bg-gray-900/50 dark:bg-black/70 backdrop-blur-sm"
                style="animation: cmdk-backdrop-in 0.2s ease-out;"
                @click="close"
            />
            <div
                class="relative w-full max-w-[640px] rounded-2xl bg-white dark:bg-[color:var(--color-surface-dark)] border border-gray-200 dark:border-white/[0.08] shadow-command overflow-hidden flex flex-col max-h-[70vh]"
                style="animation: cmdk-in 0.28s cubic-bezier(0.16, 1, 0.3, 1);"
                @click.stop
            >
                <!-- Input -->
                <div class="flex items-center gap-3 border-b border-gray-100 dark:border-white/[0.06] px-4 py-3.5">
                    <svg class="shrink-0 text-gray-400" width="18" height="18" viewBox="0 0 24 24" fill="none">
                        <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.8"/>
                        <path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                    <input
                        ref="inputRef"
                        v-model="query"
                        type="text"
                        placeholder="Search pages, orders, products, categories…"
                        class="flex-1 bg-transparent border-none text-[14px] text-gray-900 dark:text-white/95 placeholder:text-gray-400 focus:outline-none focus:ring-0"
                        autocomplete="off"
                        spellcheck="false"
                        @keydown.down.prevent="move(1)"
                        @keydown.up.prevent="move(-1)"
                        @keydown.enter.prevent="select"
                        @keydown.escape.prevent="close"
                        @keydown.tab.prevent="move(1)"
                    />
                    <kbd class="hidden sm:inline-flex items-center rounded border border-gray-200 dark:border-white/[0.12] bg-gray-50 dark:bg-white/[0.04] px-1.5 py-0.5 text-[10.5px] font-medium text-gray-500 dark:text-gray-400">
                        ESC
                    </kbd>
                </div>

                <!-- Results -->
                <div ref="listRef" class="overflow-y-auto custom-scrollbar flex-1 py-2">
                    <div v-if="loading" class="px-4 py-3 flex items-center gap-2 text-theme-xs text-gray-500 dark:text-gray-400">
                        <svg class="animate-spin h-3 w-3" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                        </svg>
                        Searching…
                    </div>

                    <div v-if="!query && state.recent.length" class="px-2 pb-1">
                        <div class="flex items-center justify-between px-2 pt-2 pb-1">
                            <span class="text-[10px] font-semibold uppercase text-gray-400 tracking-wider">Recent</span>
                            <button
                                type="button"
                                class="text-[10px] text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 focus-ring rounded"
                                @click="clearRecent"
                            >
                                Clear
                            </button>
                        </div>
                        <button
                            v-for="(item, i) in state.recent"
                            :key="'recent-' + item.id"
                            type="button"
                            :ref="el => setItemRef(el, itemsFlat.indexOf(item))"
                            :class="itemClass(itemsFlat.indexOf(item))"
                            @click="onItemClick(item)"
                            @mousemove="setActive(itemsFlat.indexOf(item))"
                        >
                            <span class="shrink-0 text-gray-400" v-html="iconSvg(item.icon || 'clock')"></span>
                            <span class="min-w-0 flex-1 text-left">
                                <span class="block text-theme-sm text-gray-800 dark:text-white/90 truncate">{{ item.title }}</span>
                                <span v-if="item.subtitle" class="block text-theme-xs text-gray-500 dark:text-gray-400 truncate">{{ item.subtitle }}</span>
                            </span>
                            <span class="shrink-0 text-[10px] uppercase text-gray-400 tracking-wider">Recent</span>
                        </button>
                    </div>

                    <div v-for="group in groupedResults" :key="group.name" class="px-2 pb-1">
                        <div class="px-2 pt-2 pb-1">
                            <span class="text-[10px] font-semibold uppercase text-gray-400 tracking-wider">{{ group.name }}</span>
                        </div>
                        <button
                            v-for="item in group.items"
                            :key="`${group.name}-${item.id}`"
                            type="button"
                            :ref="el => setItemRef(el, itemsFlat.indexOf(item))"
                            :class="itemClass(itemsFlat.indexOf(item))"
                            @click="onItemClick(item)"
                            @mousemove="setActive(itemsFlat.indexOf(item))"
                        >
                            <span class="shrink-0" :class="iconTint(item)" v-html="iconSvg(item.icon)"></span>
                            <span class="min-w-0 flex-1 text-left">
                                <span class="block text-theme-sm text-gray-800 dark:text-white/90 truncate">{{ item.title }}</span>
                                <span v-if="item.subtitle" class="block text-theme-xs text-gray-500 dark:text-gray-400 truncate">{{ item.subtitle }}</span>
                            </span>
                            <span v-if="item.shortcut" class="shrink-0 flex items-center gap-0.5">
                                <kbd
                                    v-for="k in item.shortcut.split(' ')"
                                    :key="k"
                                    class="inline-flex items-center rounded border border-gray-200 dark:border-white/[0.12] bg-gray-50 dark:bg-white/[0.04] px-1.5 py-0.5 text-[10px] font-medium text-gray-500 dark:text-gray-400"
                                >
                                    {{ k }}
                                </kbd>
                            </span>
                        </button>
                    </div>

                    <div v-if="!loading && itemsFlat.length === 0" class="py-8 text-center">
                        <p class="text-theme-sm text-gray-500 dark:text-gray-400">No matches for "{{ query }}"</p>
                        <p class="mt-1 text-theme-xs text-gray-400">Try a page name, order number, or SKU.</p>
                    </div>
                </div>

                <!-- Footer legend -->
                <div class="flex items-center gap-4 border-t border-gray-100 dark:border-white/[0.06] px-4 py-2 text-[10px] text-gray-500 dark:text-gray-400">
                    <span class="flex items-center gap-1">
                        <kbd class="inline-flex items-center rounded border border-gray-200 dark:border-white/[0.12] bg-gray-50 dark:bg-white/[0.04] px-1.5 py-0.5 font-medium">↑↓</kbd>
                        Navigate
                    </span>
                    <span class="flex items-center gap-1">
                        <kbd class="inline-flex items-center rounded border border-gray-200 dark:border-white/[0.12] bg-gray-50 dark:bg-white/[0.04] px-1.5 py-0.5 font-medium">↵</kbd>
                        Select
                    </span>
                    <span class="flex items-center gap-1">
                        <kbd class="inline-flex items-center rounded border border-gray-200 dark:border-white/[0.12] bg-gray-50 dark:bg-white/[0.04] px-1.5 py-0.5 font-medium">ESC</kbd>
                        Close
                    </span>
                    <span class="ml-auto">Powered by ⌘K</span>
                </div>
            </div>
        </div>
    </teleport>
</template>

<script>
import { router } from '@inertiajs/vue3';
import { useCommandPalette } from '@/Composables/useCommandPalette';
import axios from 'axios';

const CMD_ICONS = {
    dashboard: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M3 12l9-9 9 9M5 10v10a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1V10" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
    slider: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.6"/><circle cx="8.5" cy="10.5" r="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M21 15l-4.5-4.5a2 2 0 00-2.83 0L4 20" stroke="currentColor" stroke-width="1.6"/></svg>`,
    category: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.6"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.6"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.6"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.6"/></svg>`,
    product: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M3.5 7.5L12 3l8.5 4.5v9L12 21l-8.5-4.5v-9z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M3.5 7.5L12 12l8.5-4.5M12 12v9" stroke="currentColor" stroke-width="1.6"/></svg>`,
    order: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M6 7V6a2 2 0 012-2h8a2 2 0 012 2v1m-12 0h12l1 13a2 2 0 01-2 2H7a2 2 0 01-2-2L6 7z" stroke="currentColor" stroke-width="1.6"/></svg>`,
    return: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M9 14L4 9l5-5M4 9h11a5 5 0 015 5v6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
    payment: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><rect x="2.5" y="5.5" width="19" height="13" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M2.5 10h19M6 15h3" stroke="currentColor" stroke-width="1.6"/></svg>`,
    coupon: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M20 12a2 2 0 010-4V6a2 2 0 00-2-2H6a2 2 0 00-2 2v2a2 2 0 010 4v2a2 2 0 010 4v2a2 2 0 002 2h12a2 2 0 002-2v-2a2 2 0 010-4v-2z" stroke="currentColor" stroke-width="1.5"/></svg>`,
    contact: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><rect x="4" y="4" width="16" height="16" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M8 13a4 4 0 018 0M12 9.5a1.75 1.75 0 100-3.5 1.75 1.75 0 000 3.5z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>`,
    gateway: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M3 10h18M7 15h4" stroke="currentColor" stroke-width="1.6"/></svg>`,
    courier: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M3 7.5A1.5 1.5 0 014.5 6h9A1.5 1.5 0 0115 7.5v9H3v-9z" stroke="currentColor" stroke-width="1.6"/><path d="M15 10h3.5a1.5 1.5 0 011.25.67L21 12.5v4H15v-6.5z" stroke="currentColor" stroke-width="1.6"/><circle cx="7" cy="18" r="1.75" stroke="currentColor" stroke-width="1.6"/><circle cx="17.5" cy="18" r="1.75" stroke="currentColor" stroke-width="1.6"/></svg>`,
    settings: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 11-4 0v-.09a1.65 1.65 0 00-1-1.51 1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.6 15" stroke="currentColor" stroke-width="1.4"/></svg>`,
    plus: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>`,
    clock: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6"/><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>`,
};

function buildStaticItems() {
    const nav = [
        { id: 'nav.dashboard',        title: 'Dashboard',          subtitle: 'Overview and KPIs',       href: route('admin.dashboard'),                 icon: 'dashboard', group: 'Navigate', shortcut: 'G D' },
        { id: 'nav.orders',           title: 'Orders',             subtitle: 'All customer orders',     href: route('admin.orders'),                    icon: 'order',     group: 'Navigate', shortcut: 'G O' },
        { id: 'nav.products',         title: 'Products',           subtitle: 'Catalog inventory',       href: route('admin.products'),                  icon: 'product',   group: 'Navigate', shortcut: 'G P' },
        { id: 'nav.categories',       title: 'Categories',         subtitle: 'Product categories',      href: route('admin.category'),                  icon: 'category',  group: 'Navigate', shortcut: 'G C' },
        { id: 'nav.sliders',          title: 'Sliders',            subtitle: 'Homepage banners',        href: route('admin.sliders'),                   icon: 'slider',    group: 'Navigate' },
        { id: 'nav.home-videos',      title: 'Home videos',        subtitle: 'Homepage video reel',     href: route('admin.home-videos'),               icon: 'slider',    group: 'Navigate' },
        { id: 'nav.returns',          title: 'Returns',            subtitle: 'Return requests',         href: route('admin.returns.index'),             icon: 'return',    group: 'Navigate', shortcut: 'G R' },
        { id: 'nav.payments',         title: 'Payments',           subtitle: 'Payment transactions',    href: route('admin.payments'),                  icon: 'payment',   group: 'Navigate' },
        { id: 'nav.coupons',          title: 'Coupons',            subtitle: 'Discount codes',          href: route('admin.coupons.index'),             icon: 'coupon',    group: 'Navigate' },
        { id: 'nav.contacts',         title: 'Contacts',           subtitle: 'Customer messages',       href: route('admin.contacts'),                  icon: 'contact',   group: 'Navigate' },
        { id: 'nav.payment_gateways', title: 'Payment Gateways',   subtitle: 'Provider settings',       href: route('admin.payment_gateways.index'),    icon: 'gateway',   group: 'Settings' },
        { id: 'nav.couriers',         title: 'Courier Partners',   subtitle: 'Shipping providers',      href: route('admin.couriers.index'),            icon: 'courier',   group: 'Settings' },
        { id: 'nav.settings',         title: 'General Settings',   subtitle: 'Store info + branding',   href: route('admin.setting'),                   icon: 'settings',  group: 'Settings' },
    ];
    const actions = [
        { id: 'action.new_product',  title: 'Create product',   subtitle: 'Add a new product',   href: route('admin.products.create'),      icon: 'plus', group: 'Quick actions' },
        { id: 'action.new_category', title: 'Create category',  subtitle: 'Add a new category',  href: route('admin.category.create'),      icon: 'plus', group: 'Quick actions' },
        { id: 'action.new_slider',   title: 'Create slider',    subtitle: 'Add a new slider',    href: route('admin.slider.create'),        icon: 'plus', group: 'Quick actions' },
        { id: 'action.new_coupon',   title: 'Create coupon',    subtitle: 'Add a new coupon',    href: route('admin.coupons.create'),       icon: 'plus', group: 'Quick actions' },
    ];
    return [...nav, ...actions];
}

function fuzzyMatch(item, q) {
    if (!q) return { score: 0, matched: true };
    const hay = `${item.title} ${item.subtitle || ''} ${item.group || ''}`.toLowerCase();
    const needle = q.toLowerCase().trim();
    if (!needle) return { score: 0, matched: true };
    if (hay.includes(needle)) {
        // stronger score for title-start match
        return { score: item.title.toLowerCase().startsWith(needle) ? 100 : 60, matched: true };
    }
    // simple subsequence match
    let hi = 0, ni = 0;
    while (hi < hay.length && ni < needle.length) {
        if (hay[hi] === needle[ni]) ni++;
        hi++;
    }
    return { score: ni === needle.length ? 30 : 0, matched: ni === needle.length };
}

export default {
    setup() {
        return { ...useCommandPalette() };
    },
    data() {
        return {
            query: '',
            activeIndex: 0,
            loading: false,
            asyncResults: [],
            searchDebounce: null,
            itemRefs: [],
        };
    },
    computed: {
        staticItems() {
            return buildStaticItems();
        },
        filteredStatic() {
            if (!this.query) return this.staticItems;
            return this.staticItems
                .map(item => ({ item, ...fuzzyMatch(item, this.query) }))
                .filter(r => r.matched)
                .sort((a, b) => b.score - a.score)
                .map(r => r.item);
        },
        groupedResults() {
            const groups = new Map();
            const push = (item) => {
                const g = item.group || 'Other';
                if (!groups.has(g)) groups.set(g, []);
                groups.get(g).push(item);
            };
            // Async results first (if any)
            this.asyncResults.forEach(push);
            // Static
            this.filteredStatic.forEach(push);
            const order = ['Orders', 'Products', 'Categories', 'Navigate', 'Quick actions', 'Settings', 'Other'];
            return order
                .filter(name => groups.has(name))
                .map(name => ({ name, items: groups.get(name) }))
                .concat(
                    [...groups.keys()]
                        .filter(name => !order.includes(name))
                        .map(name => ({ name, items: groups.get(name) }))
                );
        },
        itemsFlat() {
            if (!this.query) {
                // When there's no query, include recent items so keyboard nav works
                const flat = this.state.recent.slice();
                this.groupedResults.forEach(g => flat.push(...g.items));
                return flat;
            }
            return this.groupedResults.flatMap(g => g.items);
        },
    },
    watch: {
        'state.open'(open) {
            if (open) {
                this.query = '';
                this.activeIndex = 0;
                this.asyncResults = [];
                this.$nextTick(() => this.$refs.inputRef?.focus());
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        },
        query() {
            this.activeIndex = 0;
            this.debouncedSearch();
        },
        activeIndex() {
            this.$nextTick(() => {
                const el = this.itemRefs[this.activeIndex];
                if (el && this.$refs.listRef) {
                    const rect = el.getBoundingClientRect();
                    const parentRect = this.$refs.listRef.getBoundingClientRect();
                    if (rect.bottom > parentRect.bottom) {
                        el.scrollIntoView({ block: 'nearest' });
                    } else if (rect.top < parentRect.top) {
                        el.scrollIntoView({ block: 'nearest' });
                    }
                }
            });
        },
    },
    beforeUpdate() {
        this.itemRefs = [];
    },
    methods: {
        setItemRef(el, idx) {
            if (el) this.itemRefs[idx] = el;
        },
        setActive(i) {
            if (i >= 0) this.activeIndex = i;
        },
        move(dir) {
            const n = this.itemsFlat.length;
            if (!n) return;
            this.activeIndex = (this.activeIndex + dir + n) % n;
        },
        select() {
            const item = this.itemsFlat[this.activeIndex];
            if (item) this.onItemClick(item);
        },
        onItemClick(item) {
            if (item.href) {
                this.trackVisit(item);
                this.close();
                router.visit(item.href);
            } else if (typeof item.onSelect === 'function') {
                item.onSelect();
                this.close();
            }
        },
        iconSvg(key) {
            return CMD_ICONS[key] || CMD_ICONS.settings;
        },
        iconTint(item) {
            const map = {
                'Quick actions': 'text-brand-500 dark:text-brand-400',
                'Settings':      'text-gray-500 dark:text-gray-400',
                'Orders':        'text-warning-500 dark:text-warning-400',
                'Products':      'text-success-500 dark:text-success-400',
                'Categories':    'text-blue-light-500 dark:text-blue-light-400',
            };
            return map[item.group] || 'text-gray-400 dark:text-gray-500';
        },
        itemClass(index) {
            return [
                'w-full flex items-center gap-3 rounded-lg px-3 py-2 focus-ring transition-colors',
                index === this.activeIndex
                    ? 'bg-gray-100 dark:bg-white/[0.06]'
                    : 'hover:bg-gray-50 dark:hover:bg-white/[0.04]',
            ];
        },
        debouncedSearch() {
            clearTimeout(this.searchDebounce);
            const q = this.query.trim();
            if (!q || q.length < 2) {
                this.asyncResults = [];
                this.loading = false;
                return;
            }
            this.loading = true;
            this.searchDebounce = setTimeout(() => this.doAsyncSearch(q), 250);
        },
        async doAsyncSearch(q) {
            try {
                const { data } = await axios.get(route('admin.search'), { params: { q } });
                if (this.query.trim() !== q) return; // stale
                const results = [];
                (data.orders || []).forEach(o => results.push({
                    id: 'order-' + o.id,
                    title: `#${o.order_no}`,
                    subtitle: `${o.customer_name || o.shipping_name || 'Customer'} · ₹${Number(o.total || 0).toLocaleString('en-IN')}`,
                    href: o.href,
                    icon: 'order',
                    group: 'Orders',
                }));
                (data.products || []).forEach(p => results.push({
                    id: 'product-' + p.id,
                    title: p.name,
                    subtitle: p.sku ? `SKU: ${p.sku}` : 'Product',
                    href: p.href,
                    icon: 'product',
                    group: 'Products',
                }));
                (data.categories || []).forEach(c => results.push({
                    id: 'category-' + c.id,
                    title: c.name,
                    subtitle: 'Category',
                    href: c.href,
                    icon: 'category',
                    group: 'Categories',
                }));
                this.asyncResults = results;
            } catch (e) {
                this.asyncResults = [];
            } finally {
                this.loading = false;
            }
        },
    },
};
</script>
