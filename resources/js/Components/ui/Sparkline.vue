<template>
    <svg
        :width="width"
        :height="height"
        :viewBox="`0 0 ${width} ${height}`"
        preserveAspectRatio="none"
        role="img"
        :aria-label="ariaLabel"
    >
        <defs>
            <linearGradient :id="gradientId" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" :stop-color="strokeColor" stop-opacity="0.35" />
                <stop offset="100%" :stop-color="strokeColor" stop-opacity="0" />
            </linearGradient>
        </defs>

        <path
            v-if="fill && areaD"
            :d="areaD"
            :fill="`url(#${gradientId})`"
            stroke="none"
        />
        <path
            v-if="lineD"
            :d="lineD"
            :stroke="strokeColor"
            :stroke-width="strokeWidth"
            stroke-linejoin="round"
            stroke-linecap="round"
            fill="none"
        />
        <circle
            v-if="lastPoint"
            :cx="lastPoint.x"
            :cy="lastPoint.y"
            :r="dotRadius"
            :fill="strokeColor"
        />
    </svg>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    data: { type: Array, default: () => [] },
    width: { type: Number, default: 120 },
    height: { type: Number, default: 36 },
    color: { type: String, default: 'brand' },
    strokeWidth: { type: Number, default: 1.5 },
    fill: { type: Boolean, default: true },
    dotRadius: { type: Number, default: 2 },
    ariaLabel: { type: String, default: 'Sparkline' },
});

const colorMap = {
    brand: '#465fff',
    success: '#12b76a',
    warning: '#f79009',
    error: '#f04438',
    info: '#0ba5ec',
    neutral: '#667085',
};

const strokeColor = computed(() => colorMap[props.color] || props.color);
const gradientId = computed(() => `spark-${Math.random().toString(36).slice(2, 8)}`);

const points = computed(() => {
    const values = props.data
        .map((d) => Number(typeof d === 'object' ? (d.value ?? d.y) : d))
        .filter((n) => Number.isFinite(n));
    if (values.length < 2) return [];

    const min = Math.min(...values);
    const max = Math.max(...values);
    const range = max - min || 1;
    const pad = props.strokeWidth + 1;
    const w = props.width;
    const h = props.height - pad * 2;

    return values.map((v, i) => ({
        x: (i / (values.length - 1)) * w,
        y: pad + h - ((v - min) / range) * h,
    }));
});

const lineD = computed(() => {
    const pts = points.value;
    if (!pts.length) return '';
    return pts.reduce((d, p, i) => d + (i === 0 ? `M${p.x},${p.y}` : ` L${p.x},${p.y}`), '');
});

const areaD = computed(() => {
    const pts = points.value;
    if (!pts.length) return '';
    const line = lineD.value;
    const first = pts[0];
    const last = pts[pts.length - 1];
    return `${line} L${last.x},${props.height} L${first.x},${props.height} Z`;
});

const lastPoint = computed(() => {
    const pts = points.value;
    return pts.length ? pts[pts.length - 1] : null;
});
</script>
