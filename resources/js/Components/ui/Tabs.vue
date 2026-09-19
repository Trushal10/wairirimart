<template>
    <div :class="['tabs', variantWrapperClass]">
        <div
            role="tablist"
            :aria-label="ariaLabel"
            :class="[
                'flex gap-1',
                variant === 'pills' ? 'p-1 bg-gray-100 dark:bg-white/[0.05] rounded-xl' : '',
                variant === 'underline' ? 'border-b border-gray-200 dark:border-white/[0.08] gap-5' : '',
                variant === 'segmented' ? 'p-1 bg-gray-100 dark:bg-white/[0.05] rounded-lg' : '',
                fullWidth ? 'w-full' : '',
            ]"
        >
            <button
                v-for="(tab, i) in normalizedTabs"
                :key="tab.value"
                :ref="el => registerRef(el, i)"
                type="button"
                role="tab"
                :id="`tab-${uid}-${tab.value}`"
                :aria-selected="modelValue === tab.value"
                :aria-controls="`panel-${uid}-${tab.value}`"
                :tabindex="modelValue === tab.value ? 0 : -1"
                :disabled="tab.disabled"
                :class="[
                    'inline-flex items-center gap-2 whitespace-nowrap font-medium transition-colors focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/25 rounded-md disabled:opacity-50 disabled:cursor-not-allowed',
                    fullWidth ? 'flex-1 justify-center' : '',
                    tabClass(tab),
                ]"
                @click="select(tab)"
                @keydown="onKeydown"
            >
                <component v-if="tab.icon" :is="tab.icon" :size="16" class="shrink-0" />
                <span>{{ tab.label }}</span>
                <span
                    v-if="tab.badge != null"
                    :class="[
                        'inline-flex items-center justify-center min-w-[18px] h-[18px] px-1.5 text-[10.5px] font-semibold rounded-full',
                        modelValue === tab.value
                            ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
                            : 'bg-gray-200 text-gray-700 dark:bg-white/[0.08] dark:text-gray-300',
                    ]"
                >{{ tab.badge }}</span>
            </button>
        </div>

        <div
            v-for="tab in normalizedTabs"
            :key="`panel-${tab.value}`"
            role="tabpanel"
            :id="`panel-${uid}-${tab.value}`"
            :aria-labelledby="`tab-${uid}-${tab.value}`"
            :hidden="modelValue !== tab.value"
            :class="panelClass"
            tabindex="0"
        >
            <slot :name="tab.value" v-if="modelValue === tab.value" />
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number], required: true },
    tabs: { type: Array, required: true },
    variant: { type: String, default: 'underline' },
    fullWidth: { type: Boolean, default: false },
    ariaLabel: { type: String, default: 'Tabs' },
    panelClass: { type: String, default: 'pt-5' },
});
const emit = defineEmits(['update:modelValue', 'change']);

const uid = Math.random().toString(36).slice(2, 9);
const buttonRefs = [];

function registerRef(el, i) {
    buttonRefs[i] = el;
}

const normalizedTabs = computed(() =>
    props.tabs.map(t =>
        typeof t === 'string' || typeof t === 'number'
            ? { value: t, label: String(t), disabled: false }
            : { disabled: false, ...t }
    )
);

const variantWrapperClass = computed(() => props.variant === 'underline' ? '' : '');

function tabClass(tab) {
    const active = props.modelValue === tab.value;
    if (props.variant === 'pills') {
        return active
            ? 'bg-white text-gray-900 shadow-theme-xs rounded-lg px-3.5 py-1.5 text-[13px] dark:bg-[color:var(--color-surface-dark)] dark:text-white/95'
            : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white/95 rounded-lg px-3.5 py-1.5 text-[13px]';
    }
    if (props.variant === 'segmented') {
        return active
            ? 'bg-white text-gray-900 shadow-theme-xs rounded-md px-3 py-1.5 text-[13px] dark:bg-[color:var(--color-surface-dark)] dark:text-white/95'
            : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white/95 rounded-md px-3 py-1.5 text-[13px]';
    }
    return active
        ? 'text-gray-900 dark:text-white/95 border-b-2 border-gray-900 dark:border-white pb-2.5 -mb-px px-0.5 text-[13.5px]'
        : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-100 border-b-2 border-transparent pb-2.5 -mb-px px-0.5 text-[13.5px]';
}

function select(tab) {
    if (tab.disabled) return;
    if (props.modelValue !== tab.value) {
        emit('update:modelValue', tab.value);
        emit('change', tab.value);
    }
}

async function onKeydown(e) {
    const list = normalizedTabs.value;
    const idx = list.findIndex(t => t.value === props.modelValue);
    let next = idx;
    if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
        e.preventDefault();
        do { next = (next + 1) % list.length; } while (list[next].disabled && next !== idx);
    } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
        e.preventDefault();
        do { next = (next - 1 + list.length) % list.length; } while (list[next].disabled && next !== idx);
    } else if (e.key === 'Home') {
        e.preventDefault();
        next = list.findIndex(t => !t.disabled);
    } else if (e.key === 'End') {
        e.preventDefault();
        for (let i = list.length - 1; i >= 0; i--) if (!list[i].disabled) { next = i; break; }
    } else {
        return;
    }
    if (next !== idx && list[next] && !list[next].disabled) {
        select(list[next]);
        await nextTick();
        buttonRefs[next]?.focus();
    }
}
</script>
