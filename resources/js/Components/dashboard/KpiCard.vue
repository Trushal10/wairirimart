<template>
    <div class="group relative rounded-2xl border border-gray-200 dark:border-white/[0.06] bg-white dark:bg-[color:var(--color-surface-dark)] p-5 transition-shadow duration-200 hover:shadow-elevation-2">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-100 dark:bg-white/[0.06] text-gray-700 dark:text-gray-300">
                        <component :is="iconComponent" :size="16" />
                    </span>
                    <div class="text-eyebrow text-gray-500 dark:text-gray-400 truncate">{{ label }}</div>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <div class="text-h1 num-tabular text-gray-900 dark:text-white/95 truncate">
                        {{ value }}
                    </div>
                    <span
                        v-if="delta !== null && delta !== undefined"
                        :class="deltaClass"
                        class="inline-flex items-center gap-0.5 rounded-md px-1.5 py-0.5 text-[11px] font-semibold num-tabular"
                    >
                        <component :is="delta >= 0 ? ArrowUpRight : ArrowDownRight" :size="12" class="shrink-0" />
                        {{ Math.abs(delta).toFixed(1) }}%
                    </span>
                </div>
                <p v-if="hint" class="mt-1 text-[12px] text-gray-500 dark:text-gray-400 truncate">{{ hint }}</p>
            </div>
            <Sparkline
                v-if="series && series.length > 1"
                :data="series"
                :color="sparkColor"
                :width="88"
                :height="36"
                class="shrink-0 opacity-90 group-hover:opacity-100 transition-opacity mt-1"
                :aria-label="`${label} trend`"
            />
        </div>
    </div>
</template>

<script>
import Sparkline from '@/Components/ui/Sparkline.vue';
import {
    DollarSign, ShoppingBag, Tag, RotateCcw,
    Users, Package, PackageCheck, PackageX,
    TrendingUp, ArrowUpRight, ArrowDownRight,
} from '@lucide/vue';

const ICONS = {
    revenue: DollarSign,
    cart: ShoppingBag,
    tag: Tag,
    refund: RotateCcw,
    users: Users,
    product: Package,
    delivered: PackageCheck,
    cancelled: PackageX,
    trend: TrendingUp,
};

export default {
    components: { Sparkline },
    props: {
        label: { type: String, required: true },
        value: { type: [String, Number], required: true },
        delta: { type: Number, default: null },
        icon: { type: String, default: 'revenue' },
        series: { type: Array, default: () => [] },
        hint: { type: String, default: '' },
    },
    setup() {
        return { ArrowUpRight, ArrowDownRight };
    },
    computed: {
        iconComponent() {
            return ICONS[this.icon] || ICONS.revenue;
        },
        deltaClass() {
            if (this.delta > 0)
                return 'bg-success-50 text-success-700 dark:bg-success-500/12 dark:text-success-400';
            if (this.delta < 0)
                return 'bg-error-50 text-error-700 dark:bg-error-500/12 dark:text-error-400';
            return 'bg-gray-100 text-gray-600 dark:bg-white/[0.06] dark:text-gray-400';
        },
        sparkColor() {
            if (this.delta === null || this.delta === undefined) return 'brand';
            if (this.delta > 0) return 'success';
            if (this.delta < 0) return 'error';
            return 'brand';
        },
    },
};
</script>
