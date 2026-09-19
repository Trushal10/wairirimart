<template>
    <PageHeader :title="title" :subtitle="subtitle" :crumbs="mergedCrumbs">
        <template v-if="$slots.titleBadge" #titleBadge>
            <slot name="titleBadge" />
        </template>
        <template v-if="$slots.actions" #actions>
            <slot name="actions" />
        </template>
    </PageHeader>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[220px_1fr]">
        <!-- Secondary nav -->
        <aside class="lg:sticky lg:top-20 lg:self-start">
            <nav class="flex flex-col gap-0.5" aria-label="Settings sections">
                <Link
                    v-for="item in items"
                    :key="item.route"
                    :href="route(item.route)"
                    :class="[
                        'group relative flex items-center gap-2.5 rounded-lg pl-3 pr-2.5 h-9 text-[13.5px] font-medium transition-colors',
                        isActive(item.route)
                            ? 'bg-gray-100 text-gray-900 dark:bg-white/[0.08] dark:text-white/95'
                            : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50 dark:text-gray-400 dark:hover:text-white/95 dark:hover:bg-white/[0.04]',
                    ]"
                >
                    <span
                        v-if="isActive(item.route)"
                        class="absolute left-0 top-1.5 bottom-1.5 w-[3px] rounded-r-full bg-brand-500"
                        aria-hidden="true"
                    ></span>
                    <component
                        :is="item.icon"
                        :size="15"
                        :class="[
                            'shrink-0 transition-colors',
                            isActive(item.route)
                                ? 'text-brand-500 dark:text-brand-400'
                                : 'text-gray-400 group-hover:text-gray-600 dark:text-gray-500 dark:group-hover:text-gray-300',
                        ]"
                    />
                    <span class="flex-1 truncate">{{ item.label }}</span>
                </Link>
            </nav>

            <p class="mt-4 hidden lg:block text-[11.5px] text-gray-400 dark:text-gray-500 px-3">
                Settings apply to the entire storefront.
            </p>
        </aside>

        <div class="min-w-0">
            <slot />
        </div>
    </div>
</template>

<script>
import { Link, usePage } from '@inertiajs/vue3';
import { PageHeader } from '@/Components/ui';
import { SlidersHorizontal, Landmark, Truck } from '@lucide/vue';

const NAV_ITEMS = [
    { route: 'admin.setting',                 label: 'General',          icon: SlidersHorizontal },
    { route: 'admin.payment_gateways.index',  label: 'Payment gateways', icon: Landmark },
    { route: 'admin.couriers.index',          label: 'Couriers',         icon: Truck },
];

export default {
    components: { Link, PageHeader },
    props: {
        title: { type: String, required: true },
        subtitle: { type: String, default: '' },
        crumbs: { type: Array, default: () => [] },
    },
    data() {
        return { items: NAV_ITEMS };
    },
    computed: {
        mergedCrumbs() {
            if (this.crumbs.length) return this.crumbs;
            return [
                { label: 'Dashboard', href: route('admin.dashboard') },
                { label: 'Settings' },
                { label: this.title },
            ];
        },
        currentUrl() { return usePage().url; },
    },
    methods: {
        isActive(routeName) {
            try {
                const target = new URL(route(routeName), window.location.origin).pathname.replace(/\/+$/, '');
                const current = new URL(this.currentUrl, window.location.origin).pathname.replace(/\/+$/, '');
                return current === target || current.startsWith(target + '/');
            } catch (e) {
                return false;
            }
        },
    },
};
</script>
