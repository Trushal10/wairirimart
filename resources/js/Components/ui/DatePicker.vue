<template>
    <div class="relative" ref="root">
        <button
            type="button"
            :id="id"
            :disabled="disabled"
            :aria-expanded="open"
            aria-haspopup="dialog"
            :class="[
                'w-full flex items-center gap-2 rounded-lg border bg-white text-left text-[13.5px] leading-5 text-gray-900',
                'shadow-theme-xs transition-[border-color,box-shadow,background-color] duration-150',
                'focus:outline-none focus-visible:ring-4 dark:bg-white/[0.03] dark:text-white/90',
                sizeClass,
                error
                    ? 'border-error-300 focus-visible:border-error-500 focus-visible:ring-error-500/15 dark:border-error-500/50'
                    : 'border-gray-200 hover:border-gray-300 focus-visible:border-brand-400 focus-visible:ring-brand-500/15 dark:border-white/[0.08] dark:hover:border-white/[0.14] dark:focus-visible:border-brand-500',
                disabled ? 'opacity-60 cursor-not-allowed bg-gray-50 dark:bg-white/[0.02]' : 'cursor-pointer',
            ]"
            @click="toggle"
        >
            <CalendarDays :size="16" class="text-gray-400 shrink-0" />
            <span class="flex-1 min-w-0 truncate" :class="!displayText ? 'text-gray-400 dark:text-gray-500' : ''">
                {{ displayText || placeholder }}
            </span>
            <button
                v-if="clearable && hasValue && !disabled"
                type="button"
                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                @click.stop="clear"
                aria-label="Clear date"
            >
                <X :size="14" />
            </button>
        </button>

        <transition name="fade-slide">
            <div
                v-if="open"
                class="absolute z-40 mt-1.5 rounded-2xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[color:var(--color-surface-dark)] shadow-elevation-3 p-3"
                :class="alignClass"
                role="dialog"
                aria-label="Date picker"
            >
                <div class="flex items-center justify-between px-1 pb-3">
                    <button type="button" class="p-1.5 rounded-lg text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/[0.05]" @click="prevMonth" aria-label="Previous month">
                        <ChevronLeft :size="16" />
                    </button>
                    <div class="flex items-center gap-1 text-theme-sm font-semibold text-gray-800 dark:text-white/90">
                        <select
                            v-model.number="viewMonth"
                            class="bg-transparent hover:bg-gray-100 dark:hover:bg-white/[0.05] rounded px-1.5 py-1 focus:outline-none focus:ring-2 focus:ring-brand-500/20 cursor-pointer"
                            :aria-label="'Month'"
                        >
                            <option v-for="(m, i) in monthNames" :key="i" :value="i">{{ m }}</option>
                        </select>
                        <select
                            v-model.number="viewYear"
                            class="bg-transparent hover:bg-gray-100 dark:hover:bg-white/[0.05] rounded px-1.5 py-1 focus:outline-none focus:ring-2 focus:ring-brand-500/20 cursor-pointer"
                            :aria-label="'Year'"
                        >
                            <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
                        </select>
                    </div>
                    <button type="button" class="p-1.5 rounded-lg text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/[0.05]" @click="nextMonth" aria-label="Next month">
                        <ChevronRight :size="16" />
                    </button>
                </div>

                <div class="grid grid-cols-7 gap-1 mb-1 px-1">
                    <div
                        v-for="d in weekDays"
                        :key="d"
                        class="text-center text-[10px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500"
                    >{{ d }}</div>
                </div>

                <div class="grid grid-cols-7 gap-1 px-1">
                    <button
                        v-for="cell in cells"
                        :key="cell.iso"
                        type="button"
                        :disabled="cell.disabled"
                        :class="[
                            'h-8 w-8 text-[12.5px] rounded-lg flex items-center justify-center transition-colors',
                            cell.outside ? 'text-gray-300 dark:text-gray-600' : 'text-gray-700 dark:text-gray-300',
                            cell.selected ? 'bg-gray-900 text-white hover:bg-gray-800 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100' : '',
                            cell.inRange && !cell.selected ? 'bg-gray-100 text-gray-800 dark:bg-white/[0.06] dark:text-white/90' : '',
                            cell.today && !cell.selected ? 'ring-1 ring-brand-500 ring-inset text-brand-600 dark:text-brand-400' : '',
                            !cell.selected && !cell.inRange && !cell.disabled ? 'hover:bg-gray-100 dark:hover:bg-white/[0.05]' : '',
                            cell.disabled ? 'opacity-40 cursor-not-allowed' : '',
                        ]"
                        @click="pick(cell)"
                    >{{ cell.day }}</button>
                </div>

                <div class="flex items-center justify-between gap-2 mt-3 pt-3 border-t border-gray-100 dark:border-gray-800 px-1">
                    <button
                        type="button"
                        class="text-theme-xs font-medium text-gray-600 dark:text-gray-300 hover:text-brand-500"
                        @click="pickToday"
                    >Today</button>
                    <div class="flex items-center gap-2">
                        <button
                            v-if="clearable && hasValue"
                            type="button"
                            class="text-theme-xs font-medium text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-100"
                            @click="clear"
                        >Clear</button>
                        <button
                            type="button"
                            class="text-theme-xs font-semibold text-brand-500 hover:text-brand-600"
                            @click="open = false"
                        >Done</button>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { addMonths, endOfMonth, format, isAfter, isBefore, isSameDay, parseISO, startOfDay, startOfMonth, startOfWeek } from 'date-fns';
import { CalendarDays, ChevronLeft, ChevronRight, X } from '@lucide/vue';

const props = defineProps({
    modelValue: { type: [String, Array, null], default: null },
    range: { type: Boolean, default: false },
    id: { type: String, default: '' },
    placeholder: { type: String, default: 'Select date' },
    displayFormat: { type: String, default: 'PP' },
    outputFormat: { type: String, default: 'yyyy-MM-dd' },
    min: { type: String, default: '' },
    max: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    error: { type: Boolean, default: false },
    clearable: { type: Boolean, default: true },
    align: { type: String, default: 'start' },
    size: { type: String, default: 'md' },
    weekStartsOn: { type: Number, default: 0 },
});
const emit = defineEmits(['update:modelValue', 'change']);

const root = ref(null);
const open = ref(false);
const today = startOfDay(new Date());
const viewMonth = ref(today.getMonth());
const viewYear = ref(today.getFullYear());
const pendingStart = ref(null);

const sizeClass = computed(() => {
    const map = { sm: 'h-9 px-2.5', md: 'h-10 px-3', lg: 'h-11 px-3.5' };
    return map[props.size] || map.md;
});

const alignClass = computed(() => (props.align === 'end' ? 'right-0' : 'left-0'));

const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
const weekDays = computed(() => {
    const base = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    return [...base.slice(props.weekStartsOn), ...base.slice(0, props.weekStartsOn)];
});

const yearOptions = computed(() => {
    const y = viewYear.value;
    const arr = [];
    for (let i = y - 60; i <= y + 20; i++) arr.push(i);
    return arr;
});

function toDate(v) {
    if (!v) return null;
    if (v instanceof Date) return v;
    try { return parseISO(v); } catch { return null; }
}

const minDate = computed(() => toDate(props.min));
const maxDate = computed(() => toDate(props.max));

const selected = computed(() => {
    if (props.range) {
        const arr = Array.isArray(props.modelValue) ? props.modelValue : [];
        return { start: toDate(arr[0]), end: toDate(arr[1]) };
    }
    return { start: toDate(props.modelValue), end: null };
});

const hasValue = computed(() => {
    if (props.range) return !!(selected.value.start && selected.value.end);
    return !!selected.value.start;
});

const displayText = computed(() => {
    if (props.range) {
        const { start, end } = selected.value;
        if (start && end) return `${format(start, props.displayFormat)} — ${format(end, props.displayFormat)}`;
        if (start) return `${format(start, props.displayFormat)} — …`;
        return '';
    }
    return selected.value.start ? format(selected.value.start, props.displayFormat) : '';
});

function isDisabledDate(date) {
    if (minDate.value && isBefore(date, startOfDay(minDate.value))) return true;
    if (maxDate.value && isAfter(date, startOfDay(maxDate.value))) return true;
    return false;
}

const cells = computed(() => {
    const monthStart = startOfMonth(new Date(viewYear.value, viewMonth.value, 1));
    const monthEnd = endOfMonth(monthStart);
    const gridStart = startOfWeek(monthStart, { weekStartsOn: props.weekStartsOn });
    const days = [];
    let cur = gridStart;
    for (let i = 0; i < 42; i++) {
        const outside = cur.getMonth() !== viewMonth.value;
        const disabled = isDisabledDate(cur);
        const isToday = isSameDay(cur, today);
        let isSelected = false;
        let inRange = false;
        if (props.range) {
            const { start, end } = selected.value;
            if (start && isSameDay(cur, start)) isSelected = true;
            if (end && isSameDay(cur, end)) isSelected = true;
            if (start && end && isAfter(cur, start) && isBefore(cur, end)) inRange = true;
            if (pendingStart.value && !end) {
                if (isSameDay(cur, pendingStart.value)) isSelected = true;
            }
        } else {
            if (selected.value.start && isSameDay(cur, selected.value.start)) isSelected = true;
        }
        days.push({
            date: cur,
            iso: format(cur, 'yyyy-MM-dd'),
            day: cur.getDate(),
            outside,
            disabled,
            today: isToday,
            selected: isSelected,
            inRange,
        });
        cur = new Date(cur.getFullYear(), cur.getMonth(), cur.getDate() + 1);
    }
    return days;
});

function toggle() {
    if (props.disabled) return;
    open.value = !open.value;
}

function emitValue(value) {
    emit('update:modelValue', value);
    emit('change', value);
}

function formatOut(d) { return d ? format(d, props.outputFormat) : null; }

function pick(cell) {
    if (cell.disabled) return;
    if (!props.range) {
        emitValue(formatOut(cell.date));
        open.value = false;
        return;
    }
    if (!pendingStart.value || (selected.value.start && selected.value.end)) {
        pendingStart.value = cell.date;
        emitValue([formatOut(cell.date), null]);
        return;
    }
    const start = pendingStart.value;
    const end = cell.date;
    const [a, b] = isBefore(end, start) ? [end, start] : [start, end];
    emitValue([formatOut(a), formatOut(b)]);
    pendingStart.value = null;
    open.value = false;
}

function pickToday() {
    if (isDisabledDate(today)) return;
    if (props.range) {
        pendingStart.value = today;
        emitValue([formatOut(today), null]);
    } else {
        emitValue(formatOut(today));
        open.value = false;
    }
}

function clear() {
    emitValue(props.range ? [null, null] : null);
    pendingStart.value = null;
}

function prevMonth() {
    const d = addMonths(new Date(viewYear.value, viewMonth.value, 1), -1);
    viewMonth.value = d.getMonth();
    viewYear.value = d.getFullYear();
}
function nextMonth() {
    const d = addMonths(new Date(viewYear.value, viewMonth.value, 1), 1);
    viewMonth.value = d.getMonth();
    viewYear.value = d.getFullYear();
}

watch(() => props.modelValue, (v) => {
    const d = props.range ? toDate(Array.isArray(v) ? v[0] : null) : toDate(v);
    if (d) {
        viewMonth.value = d.getMonth();
        viewYear.value = d.getFullYear();
    }
}, { immediate: true });

function onDocClick(e) {
    if (root.value && !root.value.contains(e.target)) open.value = false;
}
function onKey(e) {
    if (e.key === 'Escape') open.value = false;
}
onMounted(() => {
    document.addEventListener('mousedown', onDocClick);
    document.addEventListener('keydown', onKey);
});
onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocClick);
    document.removeEventListener('keydown', onKey);
});
</script>

<style scoped>
.fade-slide-enter-active,
.fade-slide-leave-active { transition: opacity 0.15s ease, transform 0.15s ease; }
.fade-slide-enter-from,
.fade-slide-leave-to { opacity: 0; transform: translateY(-4px); }
</style>
