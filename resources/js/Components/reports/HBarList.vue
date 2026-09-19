<template>
    <div class="space-y-3">
        <div v-if="!rows.length" class="py-8 text-center text-body text-gray-500 dark:text-gray-400">
            {{ emptyText }}
        </div>
        <div v-for="(row, i) in normalisedRows" :key="i" class="group">
            <div class="mb-1.5 flex items-center justify-between gap-2 text-[13px]">
                <div class="min-w-0 flex items-center gap-2">
                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md bg-gray-100 dark:bg-white/[0.06] text-[10.5px] font-semibold text-gray-600 dark:text-gray-300 num-tabular">
                        {{ i + 1 }}
                    </span>
                    <span class="truncate text-body-strong text-gray-900 dark:text-white/95">{{ row.label }}</span>
                    <span v-if="row.sub" class="text-[11.5px] text-gray-400 dark:text-gray-500 truncate">· {{ row.sub }}</span>
                </div>
                <span class="num-tabular text-body-strong text-gray-900 dark:text-white/95 shrink-0">{{ fmt(row.value) }}</span>
            </div>
            <div class="relative h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/[0.05]">
                <div
                    class="absolute inset-y-0 left-0 rounded-full bg-gray-900 dark:bg-white transition-all duration-500 ease-out-smooth"
                    :style="{ width: pctFor(row.value) + '%', opacity: opacityFor(i) }"
                ></div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    rows: { type: Array, default: () => [] },
    labelKey: { type: String, default: 'name' },
    valueKey: { type: String, default: 'revenue' },
    subKey: { type: String, default: '' },
    format: { type: String, default: 'money' }, // money | int | number
    emptyText: { type: String, default: 'No data for the selected filters.' },
});

const normalisedRows = computed(() =>
    (props.rows || []).map((r) => ({
        label: r[props.labelKey] ?? '—',
        value: Number(r[props.valueKey]) || 0,
        sub: props.subKey && r[props.subKey] != null ? String(r[props.subKey]) : '',
    })),
);

const max = computed(() => Math.max(1, ...normalisedRows.value.map((r) => r.value)));
function pctFor(v) { return Math.max(2, (v / max.value) * 100); }
function fmt(v) {
    const n = Number(v || 0);
    if (props.format === 'money')
        return '₹' + n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    if (props.format === 'int') return Math.round(n).toLocaleString('en-IN');
    return n.toLocaleString('en-IN');
}
// Progressive fade — top rank = full opacity, then decays.
function opacityFor(i) {
    const total = Math.max(1, normalisedRows.value.length);
    return Math.max(0.4, 1 - (i / total) * 0.55);
}
</script>
