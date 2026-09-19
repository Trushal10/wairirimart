<template>
    <div class="relative" ref="root">
        <button
            type="button"
            :id="id"
            :disabled="disabled"
            :aria-expanded="open"
            aria-haspopup="listbox"
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
            @keydown="onTriggerKey"
        >
            <span class="flex-1 min-w-0 flex flex-wrap items-center gap-1.5">
                <template v-if="selectedOptions.length === 0">
                    <span class="text-gray-400 dark:text-gray-500 truncate">{{ placeholder }}</span>
                </template>
                <template v-else-if="collapseAt > 0 && selectedOptions.length > collapseAt">
                    <span class="inline-flex items-center gap-1.5 rounded-md bg-gray-100 dark:bg-white/[0.06] px-2 py-0.5 text-theme-xs font-medium text-gray-700 dark:text-gray-200">
                        {{ selectedOptions.length }} selected
                    </span>
                </template>
                <template v-else>
                    <span
                        v-for="opt in selectedOptions"
                        :key="String(opt.value)"
                        class="inline-flex items-center gap-1 rounded-md bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300 px-2 py-0.5 text-theme-xs font-medium"
                    >
                        {{ opt.label }}
                        <button
                            v-if="!disabled"
                            type="button"
                            class="hover:text-brand-900 dark:hover:text-brand-100 focus:outline-none"
                            @click.stop="removeValue(opt.value)"
                            :aria-label="`Remove ${opt.label}`"
                        >
                            <X :size="12" />
                        </button>
                    </span>
                </template>
            </span>
            <button
                v-if="clearable && selectedOptions.length && !disabled"
                type="button"
                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                @click.stop="clearAll"
                aria-label="Clear all"
            >
                <X :size="14" />
            </button>
            <ChevronDown :size="16" class="text-gray-400 shrink-0 transition-transform" :class="{ 'rotate-180': open }" />
        </button>

        <transition name="fade-slide">
            <div
                v-if="open"
                class="absolute z-40 mt-1.5 w-full rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[color:var(--color-surface-dark)] shadow-elevation-3"
                role="listbox"
                aria-multiselectable="true"
            >
                <div v-if="searchable" class="p-2 border-b border-gray-100 dark:border-gray-800">
                    <div class="relative">
                        <Search :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                        <input
                            ref="searchInput"
                            v-model="query"
                            type="text"
                            :placeholder="searchPlaceholder"
                            class="w-full pl-9 pr-3 py-2 text-theme-sm rounded-lg bg-gray-50 dark:bg-white/[0.03] border border-transparent focus:outline-none focus:border-brand-300 dark:text-white/90"
                            @keydown="onSearchKey"
                        />
                    </div>
                </div>
                <ul class="max-h-64 overflow-y-auto custom-scrollbar py-1">
                    <li v-if="!filteredOptions.length" class="px-4 py-6 text-center text-theme-xs text-gray-500 dark:text-gray-400">
                        {{ emptyText }}
                    </li>
                    <li
                        v-for="(opt, i) in filteredOptions"
                        :key="String(opt.value)"
                        :aria-selected="isSelected(opt.value)"
                        :class="[
                            'flex items-center gap-2 px-3 py-2 text-theme-sm cursor-pointer',
                            i === highlight ? 'bg-brand-50 dark:bg-brand-500/10' : '',
                            opt.disabled ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50 dark:hover:bg-white/[0.04]',
                        ]"
                        role="option"
                        @mousemove="highlight = i"
                        @click="toggleValue(opt)"
                    >
                        <span
                            :class="[
                                'flex h-4 w-4 shrink-0 items-center justify-center rounded border-2 transition-colors',
                                isSelected(opt.value)
                                    ? 'border-brand-500 bg-brand-500 text-white'
                                    : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900',
                            ]"
                        >
                            <Check v-if="isSelected(opt.value)" :size="12" />
                        </span>
                        <span class="flex-1 text-gray-800 dark:text-white/90 truncate">{{ opt.label }}</span>
                        <span v-if="opt.hint" class="text-theme-xs text-gray-400">{{ opt.hint }}</span>
                    </li>
                </ul>
                <div
                    v-if="showFooter"
                    class="flex items-center justify-between px-3 py-2 border-t border-gray-100 dark:border-gray-800 text-theme-xs"
                >
                    <button type="button" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" @click="selectAll">
                        Select all
                    </button>
                    <button type="button" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" @click="clearAll">
                        Clear
                    </button>
                </div>
            </div>
        </transition>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Check, ChevronDown, Search, X } from '@lucide/vue';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Select…' },
    searchPlaceholder: { type: String, default: 'Search…' },
    emptyText: { type: String, default: 'No matches.' },
    id: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    error: { type: Boolean, default: false },
    size: { type: String, default: 'md' },
    searchable: { type: Boolean, default: true },
    clearable: { type: Boolean, default: true },
    collapseAt: { type: Number, default: 3 },
    showFooter: { type: Boolean, default: true },
});
const emit = defineEmits(['update:modelValue', 'change']);

const root = ref(null);
const searchInput = ref(null);
const open = ref(false);
const query = ref('');
const highlight = ref(0);

const sizeClass = computed(() => {
    const map = { sm: 'min-h-9 px-2.5 py-1.5', md: 'min-h-10 px-3 py-1.5', lg: 'min-h-11 px-3.5 py-2' };
    return map[props.size] || map.md;
});

const normalizedOptions = computed(() =>
    props.options.map(o =>
        typeof o === 'object' && o !== null
            ? { disabled: false, ...o, label: o.label ?? String(o.value) }
            : { value: o, label: String(o), disabled: false }
    )
);

const selectedOptions = computed(() =>
    normalizedOptions.value.filter(o => props.modelValue.includes(o.value))
);

const filteredOptions = computed(() => {
    if (!query.value) return normalizedOptions.value;
    const q = query.value.toLowerCase();
    return normalizedOptions.value.filter(o => o.label.toLowerCase().includes(q));
});

function isSelected(v) { return props.modelValue.includes(v); }

function toggle() {
    if (props.disabled) return;
    open.value = !open.value;
    if (open.value) nextTick(() => searchInput.value?.focus());
}

function toggleValue(opt) {
    if (opt.disabled) return;
    const next = isSelected(opt.value)
        ? props.modelValue.filter(v => v !== opt.value)
        : [...props.modelValue, opt.value];
    emit('update:modelValue', next);
    emit('change', next);
}

function removeValue(v) {
    const next = props.modelValue.filter(x => x !== v);
    emit('update:modelValue', next);
    emit('change', next);
}

function clearAll() {
    emit('update:modelValue', []);
    emit('change', []);
}

function selectAll() {
    const all = normalizedOptions.value.filter(o => !o.disabled).map(o => o.value);
    emit('update:modelValue', all);
    emit('change', all);
}

function onTriggerKey(e) {
    if (['ArrowDown', 'Enter', ' '].includes(e.key)) {
        e.preventDefault();
        if (!open.value) toggle();
    } else if (e.key === 'Escape') {
        open.value = false;
    }
}

function onSearchKey(e) {
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        highlight.value = Math.min(highlight.value + 1, filteredOptions.value.length - 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        highlight.value = Math.max(highlight.value - 1, 0);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        const opt = filteredOptions.value[highlight.value];
        if (opt) toggleValue(opt);
    } else if (e.key === 'Escape') {
        open.value = false;
    }
}

watch(query, () => (highlight.value = 0));

function onDocClick(e) {
    if (root.value && !root.value.contains(e.target)) open.value = false;
}
onMounted(() => document.addEventListener('mousedown', onDocClick));
onBeforeUnmount(() => document.removeEventListener('mousedown', onDocClick));
</script>

<style scoped>
.fade-slide-enter-active,
.fade-slide-leave-active { transition: opacity 0.15s ease, transform 0.15s ease; }
.fade-slide-enter-from,
.fade-slide-leave-to { opacity: 0; transform: translateY(-4px); }
</style>
