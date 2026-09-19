<template>
    <div class="relative">
        <div class="mb-3 flex flex-wrap items-center gap-4" v-if="series && series.length > 0">
            <span v-for="s in series" :key="s.key" class="inline-flex items-center gap-1.5 text-[12px] text-gray-600 dark:text-gray-400">
                <span class="inline-block h-2 w-3 rounded-sm" :style="{ background: s.color }"></span>
                {{ s.label }}
            </span>
        </div>

        <svg :viewBox="`0 0 ${W} ${H}`" preserveAspectRatio="none"
             class="w-full h-64" @mousemove="onMove" @mouseleave="hoverIdx = -1">
            <!-- grid -->
            <line v-for="i in 4" :key="'g' + i"
                :x1="pad" :x2="W - pad"
                :y1="pad + ((H - pad * 2) / 4) * i" :y2="pad + ((H - pad * 2) / 4) * i"
                stroke="currentColor" stroke-width="1" class="text-gray-100 dark:text-white/[0.05]"/>

            <!-- bars for bar series -->
            <template v-for="s in barSeries" :key="s.key">
                <rect v-for="(pt, i) in data" :key="s.key + i"
                    :x="xForIdx(i) - barW / 2" :y="yForSeries(s, pt[s.key])"
                    :width="barW" :height="Math.max(0, H - pad - yForSeries(s, pt[s.key]))"
                    :fill="s.color" opacity=".7" rx="1.5"/>
            </template>

            <!-- area + line for line series -->
            <template v-for="s in lineSeries" :key="s.key + 'l'">
                <path v-if="showFill(s)" :d="areaPath(s)" :fill="`url(#grad-${s.key})`"/>
                <path :d="linePath(s)" fill="none" :stroke="s.color" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <circle v-for="(pt, i) in data" :key="s.key + 'p' + i"
                    :cx="xForIdx(i)" :cy="yForSeries(s, pt[s.key])"
                    :r="hoverIdx === i ? 4.5 : 0" :fill="s.color" stroke="var(--color-white)" stroke-width="2"
                    class="transition-[r] duration-150"/>
            </template>

            <!-- labels -->
            <text v-for="(pt, i) in data" :key="'lb' + i"
                v-show="i % xLabelStep === 0 || i === data.length - 1"
                :x="xForIdx(i)" :y="H - 4"
                text-anchor="middle" font-size="9.5"
                class="fill-gray-400 dark:fill-gray-500 num-tabular">
                {{ pt.label || pt.bucket }}
            </text>

            <line v-if="hoverIdx >= 0"
                :x1="xForIdx(hoverIdx)" :x2="xForIdx(hoverIdx)"
                :y1="pad" :y2="H - pad"
                stroke="currentColor" stroke-width="1" stroke-dasharray="3 3"
                class="text-gray-300 dark:text-white/20"/>

            <defs>
                <linearGradient v-for="s in lineSeries" :key="'grd' + s.key" :id="`grad-${s.key}`" x1="0" x2="0" y1="0" y2="1">
                    <stop offset="0%" :stop-color="s.color" stop-opacity=".22"/>
                    <stop offset="100%" :stop-color="s.color" stop-opacity="0"/>
                </linearGradient>
            </defs>
        </svg>

        <div v-if="hoverIdx >= 0"
            class="absolute pointer-events-none z-10 rounded-lg bg-gray-900 dark:bg-white text-white dark:text-gray-900 shadow-tooltip px-3 py-2 text-[11.5px] min-w-[140px]"
            :style="tooltipStyle">
            <div class="mb-1 text-[10.5px] uppercase tracking-wider opacity-70">{{ data[hoverIdx].label || data[hoverIdx].bucket }}</div>
            <div v-for="s in series" :key="'tt' + s.key" class="flex items-center justify-between gap-3 py-0.5">
                <span class="inline-flex items-center gap-1.5">
                    <span class="inline-block h-2 w-2 rounded-full" :style="{ background: s.color }"></span>
                    {{ s.label }}
                </span>
                <span class="num-tabular font-semibold">{{ fmt(data[hoverIdx][s.key], s.format) }}</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    data: { type: Array, required: true },      // [{ bucket, label?, key1, key2, ... }]
    series: { type: Array, required: true },    // [{ key, label, color, type: 'line'|'bar', format?, axis? 'left'|'right' }]
});

const W = 900, H = 260, pad = 30;
const hoverIdx = ref(-1);

const barSeries = computed(() => props.series.filter(s => s.type === 'bar'));
const lineSeries = computed(() => props.series.filter(s => s.type !== 'bar'));

const xLabelStep = computed(() => {
    const n = props.data.length || 1;
    if (n <= 8) return 1;
    if (n <= 20) return 2;
    if (n <= 40) return 4;
    return Math.ceil(n / 10);
});

const barW = computed(() => {
    const n = Math.max(1, props.data.length);
    return Math.max(2, Math.min(14, ((W - pad * 2) / n) * 0.5));
});

const seriesMax = computed(() => {
    const out = {};
    for (const s of props.series) {
        out[s.key] = Math.max(1, ...props.data.map(p => Number(p[s.key]) || 0));
    }
    return out;
});

function xForIdx(i) {
    const n = Math.max(1, props.data.length - 1);
    return pad + ((W - pad * 2) / n) * i;
}
function yForSeries(s, v) {
    const usable = H - pad * 2;
    const max = seriesMax.value[s.key];
    return pad + usable - usable * ((Number(v) || 0) / max);
}
function linePath(s) {
    if (!props.data.length) return '';
    return props.data.map((pt, i) => `${i === 0 ? 'M' : 'L'}${xForIdx(i)},${yForSeries(s, pt[s.key])}`).join(' ');
}
function areaPath(s) {
    if (!props.data.length) return '';
    const line = linePath(s);
    const first = xForIdx(0);
    const last = xForIdx(props.data.length - 1);
    return `${line} L${last},${H - pad} L${first},${H - pad} Z`;
}
function showFill(s) { return s.fill !== false; }
function onMove(e) {
    if (!props.data.length) return;
    const svg = e.currentTarget;
    const box = svg.getBoundingClientRect();
    const px = ((e.clientX - box.left) / box.width) * W;
    const n = props.data.length - 1;
    const step = (W - pad * 2) / Math.max(1, n);
    let idx = Math.round((px - pad) / step);
    idx = Math.max(0, Math.min(props.data.length - 1, idx));
    hoverIdx.value = idx;
}
const tooltipStyle = computed(() => {
    if (hoverIdx.value < 0) return {};
    const line = lineSeries.value[0] || props.series[0];
    const y = yForSeries(line, props.data[hoverIdx.value][line.key]);
    return {
        left: `${(xForIdx(hoverIdx.value) / W) * 100}%`,
        top: `${((y - 8) / H) * 100}%`,
        transform: 'translate(-50%, -100%)',
    };
});
function fmt(v, format) {
    const n = Number(v || 0);
    if (format === 'money') return '₹' + n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    if (format === 'int') return Math.round(n).toLocaleString('en-IN');
    return n.toLocaleString('en-IN');
}
</script>
