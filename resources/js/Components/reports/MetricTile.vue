<template>
    <div class="group relative rounded-2xl border border-gray-200 dark:border-white/[0.06] bg-white dark:bg-[color:var(--color-surface-dark)] p-4 transition-shadow duration-200 hover:shadow-elevation-2">
        <div class="flex items-start justify-between gap-2">
            <div class="flex items-center gap-2 min-w-0 flex-1">
                <span
                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg"
                    :class="iconBgClass"
                    v-html="icon"
                    style="--w:14px;"
                ></span>
                <div class="text-eyebrow text-gray-500 dark:text-gray-400 truncate">{{ label }}</div>
            </div>
            <span
                v-if="showDelta"
                :class="deltaClass"
                class="inline-flex items-center gap-0.5 rounded-md px-1.5 py-0.5 text-[10.5px] font-semibold num-tabular shrink-0"
            >
                <svg width="9" height="9" viewBox="0 0 12 12" fill="currentColor">
                    <path v-if="deltaValue >= 0" d="M6 2l4 5H2z" />
                    <path v-else d="M6 10L2 5h8z" />
                </svg>
                {{ Math.abs(deltaValue).toFixed(1) }}%
            </span>
        </div>
        <div class="mt-3 num-tabular text-h1 text-gray-900 dark:text-white/95 truncate" :class="valueClass">
            {{ formattedValue }}
        </div>
        <div v-if="hint" class="mt-1 text-[11.5px] text-gray-500 dark:text-gray-400 truncate">{{ hint }}</div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [Number, String], required: true },
    previous: { type: [Number, String, null], default: null },
    format: { type: String, default: 'number' }, // number | money | percent | int
    hint: { type: String, default: '' },
    icon: { type: String, default: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><circle cx="12" cy="12" r="9"/></svg>' },
    tone: { type: String, default: 'neutral' }, // neutral | brand | success | warning | error | indigo | purple
    valueClass: { type: String, default: '' },
});

const showDelta = computed(() => props.previous !== null && props.previous !== undefined && !isNaN(Number(props.previous)));
const deltaValue = computed(() => {
    if (!showDelta.value) return 0;
    const cur = Number(props.value) || 0;
    const prev = Number(props.previous) || 0;
    if (prev === 0) return cur > 0 ? 100 : 0;
    return ((cur - prev) / Math.abs(prev)) * 100;
});
const deltaClass = computed(() => {
    if (deltaValue.value > 0)
        return 'bg-success-50 text-success-700 dark:bg-success-500/12 dark:text-success-400';
    if (deltaValue.value < 0)
        return 'bg-error-50 text-error-700 dark:bg-error-500/12 dark:text-error-400';
    return 'bg-gray-100 text-gray-600 dark:bg-white/[0.06] dark:text-gray-400';
});
const iconBgClass = computed(() => {
    switch (props.tone) {
        case 'success': return 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400';
        case 'warning': return 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400';
        case 'error':   return 'bg-error-50 text-error-700 dark:bg-error-500/10 dark:text-error-400';
        case 'brand':
        case 'purple':
        case 'indigo':  return 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-400';
        default:        return 'bg-gray-100 text-gray-700 dark:bg-white/[0.06] dark:text-gray-300';
    }
});
const formattedValue = computed(() => {
    const n = Number(props.value);
    if (isNaN(n)) return props.value;
    switch (props.format) {
        case 'money':   return '₹' + n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        case 'percent': return n.toFixed(2) + '%';
        case 'int':     return Math.round(n).toLocaleString('en-IN');
        default:        return n.toLocaleString('en-IN');
    }
});
</script>
