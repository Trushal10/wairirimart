<template>
    <div class="animate-pulse">
        <div class="border-b border-gray-100 dark:border-white/[0.06] bg-gray-50/60 dark:bg-white/[0.02] px-4 py-3">
            <div class="grid gap-4" :style="gridStyle">
                <div
                    v-for="i in columns"
                    :key="'h' + i"
                    class="h-2.5 rounded bg-gray-200/70 dark:bg-white/[0.06]"
                    :style="{ width: 60 + (i * 13) % 30 + '%' }"
                />
            </div>
        </div>
        <div
            v-for="r in rows"
            :key="'r' + r"
            class="border-b border-gray-100 dark:border-white/[0.04] px-4 py-4 last:border-0"
        >
            <div class="grid gap-4 items-center" :style="gridStyle">
                <div
                    v-for="i in columns"
                    :key="`c${r}-${i}`"
                    class="h-3 rounded bg-gray-200/60 dark:bg-white/[0.05]"
                    :style="{ width: cellWidth(r, i) + '%' }"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    columns: { type: Number, default: 5 },
    rows: { type: Number, default: 6 },
});

const gridStyle = computed(() => ({
    gridTemplateColumns: `repeat(${props.columns}, minmax(0, 1fr))`,
}));

function cellWidth(r, i) {
    const seed = (r * 13 + i * 7) % 100;
    return 45 + (seed % 45);
}
</script>
