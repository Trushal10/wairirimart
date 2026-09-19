<template>
    <div class="relative">
        <svg
            :viewBox="`0 0 ${W} ${H}`"
            preserveAspectRatio="none"
            class="w-full h-64 text-brand-500 dark:text-brand-400"
            @mousemove="onMove"
            @mouseleave="hoverIdx = -1"
            role="img"
            :aria-label="`Revenue and orders trend, ${series.length} data points`"
        >
            <!-- Horizontal grid lines -->
            <line
                v-for="i in 4"
                :key="'g' + i"
                :x1="pad"
                :x2="W - pad"
                :y1="pad + ((H - pad * 2) / 4) * i"
                :y2="pad + ((H - pad * 2) / 4) * i"
                stroke="currentColor"
                stroke-width="1"
                class="text-gray-100 dark:text-white/[0.05]"
            />

            <!-- Order-count bars (soft warning) -->
            <template v-for="(pt, i) in series" :key="'b' + i">
                <rect
                    :x="xForIdx(i) - barW / 2"
                    :y="yForOrders(pt.orders)"
                    :width="barW"
                    :height="Math.max(0, H - pad - yForOrders(pt.orders))"
                    class="fill-warning-300 dark:fill-warning-400/60"
                    opacity="0.75"
                    rx="1.5"
                />
            </template>

            <!-- Revenue area gradient + line -->
            <path :d="areaPath" fill="url(#revGradient)" />
            <path
                :d="linePath"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            />

            <!-- Data points -->
            <circle
                v-for="(pt, i) in series"
                :key="'p' + i"
                :cx="xForIdx(i)"
                :cy="yForRevenue(pt.revenue)"
                :r="hoverIdx === i ? 4.5 : 0"
                fill="currentColor"
                stroke="var(--color-white)"
                stroke-width="2"
                class="transition-[r] duration-150"
            />

            <!-- X-axis labels (thinned) -->
            <text
                v-for="(pt, i) in series"
                :key="'l' + i"
                v-show="i % 4 === 0 || i === series.length - 1"
                :x="xForIdx(i)"
                :y="H - 4"
                text-anchor="middle"
                font-size="9.5"
                class="fill-gray-400 dark:fill-gray-500 num-tabular"
            >
                {{ pt.label }}
            </text>

            <!-- Hover crosshair -->
            <line
                v-if="hoverIdx >= 0"
                :x1="xForIdx(hoverIdx)"
                :x2="xForIdx(hoverIdx)"
                :y1="pad"
                :y2="H - pad"
                stroke="currentColor"
                stroke-width="1"
                stroke-dasharray="3 3"
                class="text-gray-300 dark:text-white/20"
            />

            <defs>
                <linearGradient id="revGradient" x1="0" x2="0" y1="0" y2="1">
                    <stop offset="0%" stop-color="currentColor" stop-opacity="0.22" />
                    <stop offset="100%" stop-color="currentColor" stop-opacity="0" />
                </linearGradient>
            </defs>
        </svg>

        <!-- Tooltip -->
        <div
            v-if="hoverIdx >= 0"
            class="absolute pointer-events-none z-10 rounded-lg bg-gray-900 dark:bg-white text-white dark:text-gray-900 shadow-tooltip px-3 py-2 text-[11.5px] whitespace-nowrap"
            :style="tooltipStyle"
        >
            <div class="text-[10.5px] uppercase tracking-wider opacity-70 mb-0.5">
                {{ series[hoverIdx].label }}
            </div>
            <div class="flex items-center gap-3 num-tabular">
                <span class="inline-flex items-center gap-1.5">
                    <span class="h-2 w-2 rounded-full bg-brand-400 dark:bg-brand-500"></span>
                    <span class="font-semibold">₹{{ money(series[hoverIdx].revenue) }}</span>
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="h-2 w-2 rounded-full bg-warning-400"></span>
                    <span>{{ series[hoverIdx].orders }} orders</span>
                </span>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    props: {
        series: { type: Array, required: true },
    },
    data() {
        return {
            W: 900,
            H: 260,
            pad: 20,
            hoverIdx: -1,
        };
    },
    computed: {
        maxRevenue() {
            return Math.max(1, ...this.series.map((s) => s.revenue));
        },
        maxOrders() {
            return Math.max(1, ...this.series.map((s) => s.orders));
        },
        barW() {
            const n = Math.max(1, this.series.length);
            const usable = this.W - this.pad * 2;
            return Math.max(3, Math.min(14, (usable / n) * 0.6));
        },
        linePath() {
            if (!this.series.length) return '';
            return this.series
                .map(
                    (pt, i) =>
                        `${i === 0 ? 'M' : 'L'}${this.xForIdx(i)},${this.yForRevenue(pt.revenue)}`
                )
                .join(' ');
        },
        areaPath() {
            if (!this.series.length) return '';
            const line = this.linePath;
            const first = this.xForIdx(0);
            const last = this.xForIdx(this.series.length - 1);
            const bottom = this.H - this.pad;
            return `${line} L${last},${bottom} L${first},${bottom} Z`;
        },
        tooltipStyle() {
            if (this.hoverIdx < 0) return {};
            return {
                left: `${(this.xForIdx(this.hoverIdx) / this.W) * 100}%`,
                top: `${((this.yForRevenue(this.series[this.hoverIdx].revenue) - 12) / this.H) * 100}%`,
                transform: 'translate(-50%, -100%)',
            };
        },
    },
    methods: {
        money(v) {
            const n = Number(v || 0);
            return n.toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },
        xForIdx(i) {
            const n = Math.max(1, this.series.length - 1);
            return this.pad + ((this.W - this.pad * 2) / n) * i;
        },
        yForRevenue(v) {
            const usable = this.H - this.pad * 2;
            return this.pad + usable - usable * (v / this.maxRevenue);
        },
        yForOrders(v) {
            const usable = this.H - this.pad * 2;
            return this.pad + usable - usable * (v / this.maxOrders);
        },
        onMove(e) {
            if (!this.series.length) return;
            const svg = e.currentTarget;
            const box = svg.getBoundingClientRect();
            const px = ((e.clientX - box.left) / box.width) * this.W;
            const n = this.series.length - 1;
            const step = (this.W - this.pad * 2) / Math.max(1, n);
            let idx = Math.round((px - this.pad) / step);
            idx = Math.max(0, Math.min(this.series.length - 1, idx));
            this.hoverIdx = idx;
        },
    },
};
</script>
