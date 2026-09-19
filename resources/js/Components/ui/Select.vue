<template>
    <div class="relative" ref="root">
        <button
            type="button"
            :id="id"
            :disabled="disabled"
            :aria-expanded="open"
            aria-haspopup="listbox"
            :aria-labelledby="labelledby"
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
            <span
                class="flex-1 min-w-0 truncate"
                :class="!selectedOption ? 'text-gray-400 dark:text-gray-500' : ''"
            >
                {{ selectedOption ? selectedOption.label : (placeholder || 'Select…') }}
            </span>
            <ChevronDown
                :size="16"
                class="text-gray-400 shrink-0 transition-transform"
                :class="{ 'rotate-180': open }"
            />
        </button>

        <transition name="fade-slide">
            <div
                v-if="open"
                class="absolute z-40 mt-1.5 w-full rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[color:var(--color-surface-dark)] shadow-elevation-3 overflow-hidden"
            >
                <ul
                    ref="listEl"
                    role="listbox"
                    :aria-labelledby="labelledby"
                    tabindex="-1"
                    class="max-h-64 overflow-y-auto custom-scrollbar py-1"
                    @keydown="onListKey"
                >
                    <li v-if="!normalizedOptions.length" class="px-4 py-6 text-center text-theme-xs text-gray-500 dark:text-gray-400">
                        {{ emptyText }}
                    </li>
                    <li
                        v-for="(opt, i) in normalizedOptions"
                        :key="String(opt.value)"
                        :ref="el => registerRef(el, i)"
                        role="option"
                        :aria-selected="modelValue === opt.value"
                        :aria-disabled="opt.disabled ? 'true' : undefined"
                        :tabindex="-1"
                        :class="[
                            'flex items-center gap-2 px-3 py-2 text-theme-sm cursor-pointer transition-colors',
                            highlight === i && !opt.disabled ? 'bg-brand-50 dark:bg-brand-500/10' : '',
                            modelValue === opt.value ? 'text-brand-700 dark:text-brand-300 font-medium' : 'text-gray-800 dark:text-white/90',
                            opt.disabled ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50 dark:hover:bg-white/[0.04]',
                        ]"
                        @mousemove="highlight = i"
                        @click="pick(opt)"
                    >
                        <span class="flex-1 truncate">{{ opt.label }}</span>
                        <Check
                            v-if="modelValue === opt.value"
                            :size="14"
                            class="text-brand-500 shrink-0"
                        />
                    </li>
                </ul>
            </div>
        </transition>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Check, ChevronDown } from '@lucide/vue';

const props = defineProps({
    modelValue: { type: [String, Number, Boolean, null], default: '' },
    id: { type: String, default: '' },
    labelledby: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    options: { type: Array, default: () => [] },
    disabled: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
    error: { type: Boolean, default: false },
    size: { type: String, default: 'md' },
    emptyText: { type: String, default: 'No options.' },
    defaultOpen: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue', 'change', 'open', 'close']);

const root = ref(null);
const listEl = ref(null);
const open = ref(false);
const highlight = ref(-1);
const itemRefs = [];

function registerRef(el, i) {
    itemRefs[i] = el;
}

const sizeClass = computed(() => {
    const map = { sm: 'h-9 px-2.5', md: 'h-10 px-3', lg: 'h-11 px-3.5' };
    return map[props.size] || map.md;
});

const normalizedOptions = computed(() =>
    (props.options || []).map(o =>
        typeof o === 'object' && o !== null
            ? { value: o.value, label: o.label ?? String(o.value), disabled: !!o.disabled }
            : { value: o, label: String(o), disabled: false }
    )
);

const selectedOption = computed(() =>
    normalizedOptions.value.find(o => o.value === props.modelValue) || null
);

function toggle() {
    if (props.disabled) return;
    open.value = !open.value;
    if (open.value) {
        const idx = normalizedOptions.value.findIndex(o => o.value === props.modelValue);
        highlight.value = idx >= 0 ? idx : normalizedOptions.value.findIndex(o => !o.disabled);
        nextTick(() => {
            listEl.value?.focus();
            scrollIntoView();
        });
    }
}

watch(open, (v) => emit(v ? 'open' : 'close'));

function pick(opt) {
    if (opt.disabled) return;
    emit('update:modelValue', opt.value);
    emit('change', opt.value);
    open.value = false;
}

function move(delta) {
    const list = normalizedOptions.value;
    if (!list.length) return;
    let i = highlight.value;
    for (let step = 0; step < list.length; step++) {
        i = (i + delta + list.length) % list.length;
        if (!list[i].disabled) {
            highlight.value = i;
            scrollIntoView();
            return;
        }
    }
}

function scrollIntoView() {
    const el = itemRefs[highlight.value];
    if (el && el.scrollIntoView) el.scrollIntoView({ block: 'nearest' });
}

function onTriggerKey(e) {
    if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(e.key)) {
        e.preventDefault();
        if (!open.value) toggle();
        else if (e.key === 'ArrowDown') move(1);
        else if (e.key === 'ArrowUp') move(-1);
    } else if (e.key === 'Escape') {
        open.value = false;
    }
}

let typeahead = '';
let typeTimer = null;
function typeToSearch(char) {
    typeahead += char.toLowerCase();
    clearTimeout(typeTimer);
    typeTimer = setTimeout(() => (typeahead = ''), 600);
    const list = normalizedOptions.value;
    const start = Math.max(0, highlight.value);
    for (let i = 1; i <= list.length; i++) {
        const idx = (start + i) % list.length;
        const opt = list[idx];
        if (!opt.disabled && opt.label.toLowerCase().startsWith(typeahead)) {
            highlight.value = idx;
            scrollIntoView();
            return;
        }
    }
}

function onListKey(e) {
    if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
    else if (e.key === 'Home') { e.preventDefault(); highlight.value = -1; move(1); }
    else if (e.key === 'End') { e.preventDefault(); highlight.value = normalizedOptions.value.length; move(-1); }
    else if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        const opt = normalizedOptions.value[highlight.value];
        if (opt) pick(opt);
    } else if (e.key === 'Escape' || e.key === 'Tab') {
        open.value = false;
    } else if (e.key.length === 1 && /\S/.test(e.key)) {
        typeToSearch(e.key);
    }
}

function onDocClick(e) {
    if (root.value && !root.value.contains(e.target)) open.value = false;
}
onMounted(() => {
    document.addEventListener('mousedown', onDocClick);
    if (props.defaultOpen) nextTick(toggle);
});
onBeforeUnmount(() => document.removeEventListener('mousedown', onDocClick));

watch(() => props.options, () => {
    if (highlight.value >= normalizedOptions.value.length) highlight.value = -1;
});
</script>

<style scoped>
.fade-slide-enter-active,
.fade-slide-leave-active { transition: opacity 0.15s ease, transform 0.15s ease; }
.fade-slide-enter-from,
.fade-slide-leave-to { opacity: 0; transform: translateY(-4px); }
</style>
