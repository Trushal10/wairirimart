<template>
    <div class="flex flex-col items-center">
        <div class="relative">
            <svg :width="size" :height="size" :viewBox="`0 0 ${size} ${size}`" role="img" aria-label="Order status breakdown">
                <circle
                    :cx="size / 2"
                    :cy="size / 2"
                    :r="radius"
                    fill="none"
                    class="stroke-gray-100 dark:stroke-white/[0.06]"
                    :stroke-width="stroke"
                />
                <circle
                    v-for="seg in segments"
                    :key="seg.status"
                    :cx="size / 2"
                    :cy="size / 2"
                    :r="radius"
                    fill="none"
                    :stroke="seg.color"
                    :stroke-width="stroke"
                    :stroke-dasharray="`${seg.arc} ${circumference}`"
                    :stroke-dashoffset="seg.offset"
                    :transform="`rotate(-90 ${size / 2} ${size / 2})`"
                    stroke-linecap="butt"
                    class="transition-[stroke-dasharray] duration-500"
                />
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
                <div class="text-h1 num-tabular text-gray-900 dark:text-white/95">{{ total }}</div>
                <div class="text-eyebrow text-gray-500 dark:text-gray-400 mt-0.5">Total orders</div>
            </div>
        </div>

        <ul class="w-full mt-5 space-y-2">
            <li
                v-for="s in labels"
                :key="s.status"
                class="flex items-center justify-between text-[13px] py-1"
            >
                <span class="inline-flex items-center gap-2 text-gray-700 dark:text-gray-300">
                    <span class="inline-block h-2 w-2 rounded-full" :style="{ background: s.color }" aria-hidden="true"></span>
                    {{ prettyStatus(s.status) }}
                </span>
                <span class="inline-flex items-baseline gap-1.5 num-tabular">
                    <span class="text-body-strong text-gray-900 dark:text-white/95">{{ s.count }}</span>
                    <span class="text-caption">{{ s.pct }}%</span>
                </span>
            </li>
        </ul>
    </div>
</template>

<script>
// Design-system colors
const COLORS = {
    pending:   '#F79009', // warning-500
    confirmed: '#465FFF', // brand-500
    completed: '#12B76A', // success-500
    delivered: '#12B76A',
    shipped:   '#0BA5EC', // blue-light-500
    canceled:  '#F04438', // error-500
    cancelled: '#F04438',
    refunded:  '#98A2B3', // gray-400
};
const LABEL_ORDER = ['pending', 'confirmed', 'shipped', 'delivered', 'completed', 'cancelled', 'canceled', 'refunded'];

export default {
    props: {
        breakdown: { type: Object, required: true },
    },
    data() {
        return { size: 168, stroke: 22 };
    },
    computed: {
        radius() { return (this.size - this.stroke) / 2; },
        circumference() { return 2 * Math.PI * this.radius; },
        total() {
            return Object.values(this.breakdown || {}).reduce((s, v) => s + Number(v || 0), 0);
        },
        labels() {
            const seen = new Set();
            const out = [];
            for (const k of LABEL_ORDER) {
                if (seen.has(k)) continue;
                if (!(k in (this.breakdown || {}))) continue;
                seen.add(k);
                const count = Number(this.breakdown[k] || 0);
                out.push({
                    status: k,
                    count,
                    color: COLORS[k] || '#98A2B3',
                    pct: this.total > 0 ? Math.round((count / this.total) * 100) : 0,
                });
            }
            // Include any statuses not in our known order at the end.
            for (const k of Object.keys(this.breakdown || {})) {
                if (seen.has(k)) continue;
                const count = Number(this.breakdown[k] || 0);
                out.push({
                    status: k,
                    count,
                    color: COLORS[k] || '#98A2B3',
                    pct: this.total > 0 ? Math.round((count / this.total) * 100) : 0,
                });
            }
            return out.filter(l => l.count > 0 || this.total === 0);
        },
        segments() {
            if (this.total === 0) return [];
            const out = [];
            let prefix = 0;
            for (const l of this.labels) {
                if (l.count <= 0) continue;
                const portion = l.count / this.total;
                out.push({
                    status: l.status,
                    color: l.color,
                    arc: this.circumference * portion,
                    offset: -this.circumference * prefix,
                });
                prefix += portion;
            }
            return out;
        },
    },
    methods: {
        prettyStatus(s) {
            const map = { canceled: 'Cancelled' };
            return map[s] || s.charAt(0).toUpperCase() + s.slice(1);
        },
    },
};
</script>
