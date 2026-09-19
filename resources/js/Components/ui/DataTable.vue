<template>
    <div class="overflow-hidden rounded-2xl border border-gray-200 dark:border-white/[0.06] bg-white dark:bg-[color:var(--color-surface-dark)]">
        <div v-if="$slots.toolbar" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-b border-gray-100 dark:border-white/[0.06]">
            <slot name="toolbar" :selected="selectedRows" :clear="clearSelection" />
        </div>

        <div v-if="filterChips.length" class="flex flex-wrap items-center gap-2 px-4 py-2.5 border-b border-gray-100 dark:border-white/[0.06] bg-gray-50/50 dark:bg-white/[0.02]">
            <span class="text-eyebrow text-gray-500 dark:text-gray-400">Filters</span>
            <button
                v-for="chip in filterChips"
                :key="chip.key"
                type="button"
                class="inline-flex items-center gap-1.5 rounded-md bg-gray-900 text-white dark:bg-white dark:text-gray-900 px-2 py-1 text-[12px] font-medium hover:bg-gray-800 dark:hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40 transition-colors"
                :aria-label="`Remove filter ${chip.label}`"
                @click="$emit('remove-filter', chip.key)"
            >
                <span>{{ chip.label }}</span>
                <X :size="12" />
            </button>
            <button
                v-if="filterChips.length > 1"
                type="button"
                class="text-[12px] font-medium text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white ml-1"
                @click="$emit('clear-filters')"
            >
                Clear all
            </button>
        </div>

        <transition name="bulk-fade">
            <div
                v-if="selectable && selectedRows.length"
                class="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-gray-100 dark:border-white/[0.06] bg-gray-900 dark:bg-white/[0.06]"
                role="region"
                aria-label="Bulk actions"
            >
                <div class="flex items-center gap-3">
                    <span class="text-[13px] font-medium text-white dark:text-white/90">
                        {{ selectedRows.length }} selected
                    </span>
                    <button
                        type="button"
                        class="text-[12px] font-medium text-white/70 hover:text-white dark:text-gray-300 dark:hover:text-white"
                        @click="clearSelection"
                    >Clear selection</button>
                </div>
                <div class="flex items-center gap-2">
                    <slot name="bulkActions" :selected="selectedRows" :clear="clearSelection" />
                </div>
            </div>
        </transition>

        <div
            class="relative custom-scrollbar"
            :class="[
                stickyHeader ? 'overflow-auto' : 'overflow-x-auto',
                maxHeight ? '' : '',
            ]"
            :style="maxHeight ? `max-height:${maxHeight}` : ''"
        >
            <div v-if="responsive" class="md:hidden">
                <div v-if="loading" class="p-4">
                    <SkeletonTable :columns="1" :rows="4" />
                </div>
                <div v-else-if="!rows.length" class="p-6">
                    <slot name="empty">
                        <div class="py-10 text-center text-body text-gray-500 dark:text-gray-400">{{ emptyText }}</div>
                    </slot>
                </div>
                <ul v-else class="divide-y divide-gray-100 dark:divide-white/[0.04]">
                    <li
                        v-for="(row, idx) in rows"
                        :key="rowKey ? row[rowKey] : idx"
                        class="p-4"
                    >
                        <div v-if="selectable" class="flex items-center gap-2 mb-3">
                            <Checkbox :model-value="isSelected(row)" @change="toggleRow(row)" />
                            <span class="text-eyebrow text-gray-500 dark:text-gray-400">Select</span>
                        </div>
                        <dl class="grid grid-cols-1 gap-2">
                            <div v-for="col in columns" :key="col.key" class="grid grid-cols-3 gap-3 items-baseline">
                                <dt class="col-span-1 text-eyebrow text-gray-500 dark:text-gray-400">{{ col.label }}</dt>
                                <dd class="col-span-2 text-body text-gray-800 dark:text-white/85">
                                    <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]" :index="idx">
                                        {{ formatValue(row, col) }}
                                    </slot>
                                </dd>
                            </div>
                        </dl>
                        <div v-if="$slots.rowActions" class="mt-3 pt-3 border-t border-gray-100 dark:border-white/[0.06] flex justify-end">
                            <slot name="rowActions" :row="row" :index="idx" />
                        </div>
                    </li>
                </ul>
            </div>

            <table :class="['min-w-full text-left text-[13.5px]', responsive ? 'hidden md:table' : '']">
                <thead
                    :class="[
                        'bg-gray-50/60 dark:bg-white/[0.02] text-gray-500 dark:text-gray-400 text-[11.5px] uppercase tracking-wider font-semibold',
                        stickyHeader ? 'sticky top-0 z-10 backdrop-blur-sm' : '',
                    ]"
                >
                    <tr>
                        <th v-if="selectable" scope="col" class="px-4 py-3 w-10">
                            <Checkbox :model-value="allSelected" :indeterminate="someSelected" @change="toggleAll" />
                        </th>
                        <th
                            v-for="col in columns"
                            :key="col.key"
                            scope="col"
                            :aria-sort="col.sortable ? (sortBy === col.key ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none') : undefined"
                            :class="[
                                'px-4 py-3 whitespace-nowrap',
                                col.sortable ? 'cursor-pointer select-none hover:text-gray-900 dark:hover:text-gray-100 transition-colors' : '',
                                col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left',
                                col.headerClass || '',
                            ]"
                            @click="col.sortable && handleSort(col.key)"
                        >
                            <span class="inline-flex items-center gap-1">
                                {{ col.label }}
                                <span
                                    v-if="col.sortable"
                                    :class="{
                                        'text-gray-900 dark:text-white': sortBy === col.key,
                                        'text-gray-300 dark:text-gray-600': sortBy !== col.key,
                                    }"
                                >
                                    <ChevronUp v-if="sortBy === col.key && sortDir === 'asc'" :size="12" />
                                    <ChevronDown v-else-if="sortBy === col.key && sortDir === 'desc'" :size="12" />
                                    <ChevronsUpDown v-else :size="12" />
                                </span>
                            </span>
                        </th>
                        <th v-if="$slots.rowActions" scope="col" class="px-4 py-3 text-right w-24">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04] text-gray-700 dark:text-gray-300">
                    <tr v-if="loading">
                        <td :colspan="totalCols" class="p-0">
                            <SkeletonTable :columns="skeletonColumns" :rows="skeletonRows" />
                        </td>
                    </tr>
                    <tr v-else-if="!rows.length">
                        <td :colspan="totalCols" class="p-0">
                            <slot name="empty">
                                <div class="py-14 text-center">
                                    <p class="text-body text-gray-500 dark:text-gray-400">{{ emptyText }}</p>
                                </div>
                            </slot>
                        </td>
                    </tr>
                    <tr
                        v-else
                        v-for="(row, idx) in rows"
                        :key="rowKey ? row[rowKey] : idx"
                        :class="[
                            'group relative transition-colors',
                            isSelected(row)
                                ? 'bg-gray-900/[0.03] dark:bg-white/[0.04]'
                                : 'hover:bg-gray-50 dark:hover:bg-white/[0.025]',
                            rowClass ? rowClass(row) : '',
                        ]"
                        @click="$emit('row-click', row, idx)"
                    >
                        <td v-if="selectable" class="px-4 py-3.5" @click.stop>
                            <Checkbox
                                :model-value="isSelected(row)"
                                @change="toggleRow(row)"
                            />
                        </td>
                        <td
                            v-for="col in columns"
                            :key="col.key"
                            :class="[
                                'px-4 py-3.5 text-gray-800 dark:text-white/85',
                                col.wrap ? '' : 'whitespace-nowrap',
                                col.align === 'right' ? 'text-right num-tabular' : col.align === 'center' ? 'text-center' : 'text-left',
                                col.class || '',
                            ]"
                        >
                            <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]" :index="idx">
                                {{ formatValue(row, col) }}
                            </slot>
                        </td>
                        <td v-if="$slots.rowActions" class="px-4 py-3.5 text-right" @click.stop>
                            <slot name="rowActions" :row="row" :index="idx" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="$slots.footer" class="flex items-center justify-between gap-3 px-4 py-3 border-t border-gray-100 dark:border-white/[0.06] bg-gray-50/40 dark:bg-white/[0.02]">
            <slot name="footer" />
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { ChevronDown, ChevronsUpDown, ChevronUp, X } from '@lucide/vue';
import Checkbox from './Checkbox.vue';
import SkeletonTable from './SkeletonTable.vue';

const props = defineProps({
    columns: { type: Array, required: true },
    rows: { type: Array, default: () => [] },
    rowKey: { type: String, default: 'id' },
    sortBy: { type: String, default: '' },
    sortDir: { type: String, default: 'asc' },
    selectable: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    emptyText: { type: String, default: 'No records found.' },
    stickyHeader: { type: Boolean, default: false },
    maxHeight: { type: String, default: '' },
    responsive: { type: Boolean, default: true },
    filterChips: { type: Array, default: () => [] },
    rowClass: { type: Function, default: null },
});
const emit = defineEmits(['update:sortBy', 'update:sortDir', 'sort', 'selection-change', 'row-click', 'remove-filter', 'clear-filters']);

const selectedRows = ref([]);

const totalCols = computed(() => {
    let n = props.columns.length;
    if (props.selectable) n += 1;
    return n + 1;
});

const skeletonColumns = computed(() => Math.max(props.columns.length, 3));
const skeletonRows = computed(() => 6);

const allSelected = computed(() =>
    props.rows.length > 0 && selectedRows.value.length === props.rows.length
);
const someSelected = computed(() =>
    selectedRows.value.length > 0 && selectedRows.value.length < props.rows.length
);

function idOf(row) {
    return props.rowKey ? row[props.rowKey] : row;
}
function isSelected(row) {
    return selectedRows.value.some(r => idOf(r) === idOf(row));
}
function toggleRow(row) {
    if (isSelected(row)) {
        selectedRows.value = selectedRows.value.filter(r => idOf(r) !== idOf(row));
    } else {
        selectedRows.value = [...selectedRows.value, row];
    }
}
function toggleAll() {
    selectedRows.value = allSelected.value ? [] : [...props.rows];
}
function clearSelection() {
    selectedRows.value = [];
}
function handleSort(key) {
    if (props.sortBy === key) {
        const next = props.sortDir === 'asc' ? 'desc' : 'asc';
        emit('update:sortDir', next);
        emit('sort', { key, dir: next });
    } else {
        emit('update:sortBy', key);
        emit('update:sortDir', 'asc');
        emit('sort', { key, dir: 'asc' });
    }
}
function formatValue(row, col) {
    const raw = row[col.key];
    if (typeof col.format === 'function') return col.format(raw, row);
    return raw ?? '';
}

watch(selectedRows, (v) => emit('selection-change', v));
watch(() => props.rows, () => clearSelection());

defineExpose({ clearSelection });
</script>

<style scoped>
.bulk-fade-enter-active,
.bulk-fade-leave-active { transition: opacity 0.18s ease, transform 0.18s ease; }
.bulk-fade-enter-from,
.bulk-fade-leave-to { opacity: 0; transform: translateY(-4px); }
</style>
