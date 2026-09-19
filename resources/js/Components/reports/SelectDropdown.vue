<template>
    <div class="relative" ref="rootEl">
        <button
            type="button"
            :id="triggerId"
            :disabled="disabled"
            @click="toggle"
            @keydown="onTriggerKeydown"
            :aria-haspopup="'listbox'"
            :aria-expanded="open"
            :aria-controls="listboxId"
            :class="[
                'group inline-flex items-center justify-between gap-2 w-full h-10 rounded-lg border bg-white pl-3 pr-2 text-[13.5px] text-left shadow-theme-xs transition-[border-color,box-shadow,background-color] duration-150',
                'focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/15',
                'dark:bg-white/[0.03] dark:text-gray-200',
                open
                    ? 'border-brand-400 dark:border-brand-500'
                    : 'border-gray-200 hover:border-gray-300 dark:border-white/[0.08] dark:hover:border-white/[0.14]',
                disabled ? 'opacity-60 cursor-not-allowed bg-gray-50 dark:bg-white/[0.02]' : 'cursor-pointer',
            ]"
        >
            <span :class="['truncate', valueLabel ? 'text-gray-800 dark:text-white/90' : 'text-gray-400 dark:text-gray-500']">
                {{ valueLabel || placeholder }}
            </span>
            <svg
                width="16" height="16" viewBox="0 0 24 24" fill="none"
                :class="['shrink-0 text-gray-400 transition-transform duration-200', open ? 'rotate-180 text-brand-500' : '']"
            >
                <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>

        <transition
            enter-active-class="transition ease-out duration-150"
            enter-from-class="opacity-0 -translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <div
                v-if="open"
                :id="listboxId"
                role="listbox"
                :aria-labelledby="triggerId"
                :aria-activedescendant="activeId"
                @keydown="onListKeydown"
                tabindex="-1"
                ref="listEl"
                class="absolute z-50 mt-1.5 min-w-full max-w-[320px] rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[color:var(--color-surface-dark)] shadow-elevation-3 overflow-hidden"
            >
                <div v-if="searchable && normalisedOptions.length > 6" class="p-2 border-b border-gray-100 dark:border-gray-800">
                    <input
                        ref="searchEl"
                        v-model="query"
                        type="text"
                        :placeholder="searchPlaceholder"
                        class="h-9 w-full rounded-md border border-gray-200 bg-white px-2.5 text-theme-sm dark:bg-gray-900 dark:border-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                        @keydown.stop
                        @keydown.esc="close"
                        @keydown.enter.prevent="selectHighlighted"
                        @keydown.down.prevent="moveHighlight(1)"
                        @keydown.up.prevent="moveHighlight(-1)"
                    />
                </div>

                <ul class="max-h-64 overflow-y-auto custom-scrollbar py-1">
                    <li v-if="!filteredOptions.length" class="px-3 py-6 text-center text-theme-xs text-gray-500 dark:text-gray-400">
                        {{ noResultsText }}
                    </li>
                    <li
                        v-for="(opt, idx) in filteredOptions"
                        :key="opt.value ?? '__null__' + idx"
                        :id="optionId(idx)"
                        role="option"
                        :aria-selected="isSelected(opt)"
                        @mouseenter="highlightedIdx = idx"
                        @mousedown.prevent="pick(opt)"
                        :class="[
                            'group/opt flex items-center justify-between gap-2 mx-1 px-2.5 py-2 rounded-md cursor-pointer select-none text-[13px] transition-colors',
                            highlightedIdx === idx
                                ? 'bg-gray-100 dark:bg-white/[0.08] text-gray-900 dark:text-white/95'
                                : isSelected(opt)
                                    ? 'text-brand-700 dark:text-brand-300 font-medium'
                                    : 'text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/[0.04]',
                        ]"
                    >
                        <span class="truncate">{{ opt.label }}</span>
                        <svg
                            v-if="isSelected(opt)"
                            width="14" height="14" viewBox="0 0 24 24" fill="none"
                            class="shrink-0"
                        >
                            <path d="M5 12l5 5L20 7" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </li>
                </ul>
            </div>
        </transition>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number, null], default: null },
    options: { type: Array, required: true },      // [{ value, label }] OR [string]
    placeholder: { type: String, default: 'Select…' },
    searchable: { type: Boolean, default: true },
    searchPlaceholder: { type: String, default: 'Search…' },
    noResultsText: { type: String, default: 'No results.' },
    disabled: { type: Boolean, default: false },
    nullable: { type: Boolean, default: true },    // include an "All" / empty option
    nullLabel: { type: String, default: 'All' },
    nullValue: { default: '' },                    // '' for string filters, null for id filters
});

const emit = defineEmits(['update:modelValue']);

const uid = Math.random().toString(36).slice(2, 8);
const triggerId = `sd-trigger-${uid}`;
const listboxId = `sd-listbox-${uid}`;
const optionId = (i) => `sd-opt-${uid}-${i}`;

const open = ref(false);
const query = ref('');
const highlightedIdx = ref(0);

const rootEl = ref(null);
const listEl = ref(null);
const searchEl = ref(null);

const normalisedOptions = computed(() => {
    const base = (props.options || []).map((o) => {
        if (typeof o === 'string' || typeof o === 'number') {
            return { value: o, label: String(o) };
        }
        return { value: o.value ?? o.id ?? o.code, label: o.label ?? o.name ?? String(o.value ?? '') };
    });
    if (props.nullable) {
        return [{ value: props.nullValue, label: props.nullLabel }, ...base];
    }
    return base;
});

const filteredOptions = computed(() => {
    if (!query.value.trim()) return normalisedOptions.value;
    const q = query.value.toLowerCase();
    return normalisedOptions.value.filter((o) => String(o.label).toLowerCase().includes(q));
});

const valueLabel = computed(() => {
    const match = normalisedOptions.value.find((o) => equal(o.value, props.modelValue));
    if (!match) return '';
    if (props.nullable && equal(match.value, props.nullValue)) return match.label;
    return match.label;
});

const activeId = computed(() => (open.value ? optionId(highlightedIdx.value) : null));

function equal(a, b) {
    if (a === null && b === null) return true;
    if (a === undefined || b === undefined) return a === b;
    return String(a) === String(b);
}

function isSelected(opt) {
    return equal(opt.value, props.modelValue);
}

function toggle() {
    if (props.disabled) return;
    open.value ? close() : openMenu();
}

function openMenu() {
    open.value = true;
    query.value = '';
    // Highlight the currently-selected option (or first).
    const idx = filteredOptions.value.findIndex((o) => isSelected(o));
    highlightedIdx.value = idx >= 0 ? idx : 0;
    nextTick(() => {
        if (props.searchable && searchEl.value) searchEl.value.focus();
        else if (listEl.value) listEl.value.focus();
    });
}

function close() {
    open.value = false;
}

function pick(opt) {
    emit('update:modelValue', opt.value);
    close();
}

function moveHighlight(delta) {
    if (!filteredOptions.value.length) return;
    const n = filteredOptions.value.length;
    highlightedIdx.value = (highlightedIdx.value + delta + n) % n;
    scrollIntoView();
}

function selectHighlighted() {
    const opt = filteredOptions.value[highlightedIdx.value];
    if (opt) pick(opt);
}

function scrollIntoView() {
    nextTick(() => {
        const el = document.getElementById(optionId(highlightedIdx.value));
        if (el && el.scrollIntoView) el.scrollIntoView({ block: 'nearest' });
    });
}

function onTriggerKeydown(e) {
    if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(e.key)) {
        e.preventDefault();
        openMenu();
    }
}

function onListKeydown(e) {
    if (e.key === 'Escape') { e.preventDefault(); close(); return; }
    if (e.key === 'ArrowDown') { e.preventDefault(); moveHighlight(1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); moveHighlight(-1); }
    else if (e.key === 'Enter') { e.preventDefault(); selectHighlighted(); }
    else if (e.key === 'Home') { e.preventDefault(); highlightedIdx.value = 0; scrollIntoView(); }
    else if (e.key === 'End') { e.preventDefault(); highlightedIdx.value = filteredOptions.value.length - 1; scrollIntoView(); }
}

function onDocClick(e) {
    if (!open.value) return;
    if (rootEl.value && !rootEl.value.contains(e.target)) close();
}

watch(() => query.value, () => { highlightedIdx.value = 0; });

onMounted(() => document.addEventListener('mousedown', onDocClick));
onBeforeUnmount(() => document.removeEventListener('mousedown', onDocClick));
</script>
