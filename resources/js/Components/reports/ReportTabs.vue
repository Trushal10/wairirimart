<template>
    <div class="mb-6 overflow-x-auto no-scrollbar border-b border-gray-200 dark:border-white/[0.06]">
        <nav class="inline-flex items-center gap-5" role="tablist" aria-label="Reports">
            <Link
                v-for="t in tabs"
                :key="t.name"
                :href="route(t.name)"
                role="tab"
                :aria-selected="current === t.name"
                :class="[
                    'inline-flex items-center gap-1.5 whitespace-nowrap py-2.5 px-0.5 -mb-px border-b-2 text-[13.5px] font-medium transition-colors focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/25 rounded-t-md',
                    current === t.name
                        ? 'text-gray-900 dark:text-white/95 border-gray-900 dark:border-white'
                        : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 border-transparent',
                ]"
            >
                <component :is="t.icon" :size="15" />
                {{ t.label }}
            </Link>
        </nav>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { LineChart, ClipboardList, Wallet, Truck, Scale } from '@lucide/vue';

defineProps({ current: { type: String, required: true } });

const tabs = [
    { name: 'admin.reports.sales',    label: 'Sales',    icon: LineChart },
    { name: 'admin.reports.orders',   label: 'Orders',   icon: ClipboardList },
    { name: 'admin.reports.payments', label: 'Payments', icon: Wallet },
    { name: 'admin.reports.shipping', label: 'Shipping', icon: Truck },
    { name: 'admin.reports.pnl',      label: 'P&L',      icon: Scale },
];
</script>
