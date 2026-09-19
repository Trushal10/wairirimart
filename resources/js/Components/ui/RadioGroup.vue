<template>
    <div :class="orientation === 'horizontal' ? 'flex flex-wrap gap-3' : 'flex flex-col gap-2'" role="radiogroup" :aria-label="ariaLabel">
        <label
            v-for="opt in normalizedOptions"
            :key="String(opt.value)"
            :class="[
                'group relative flex cursor-pointer transition-colors focus-within:ring-4 focus-within:ring-brand-500/20',
                variantWrapperClass(opt),
                opt.disabled ? 'opacity-50 cursor-not-allowed' : '',
            ]"
        >
            <input
                type="radio"
                class="sr-only peer"
                :name="name"
                :value="opt.value"
                :checked="modelValue === opt.value"
                :disabled="opt.disabled"
                @change="select(opt)"
            />
            <span
                v-if="variant === 'default'"
                :class="[
                    'mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2 shadow-theme-xs transition-colors duration-150',
                    modelValue === opt.value
                        ? 'border-gray-900 bg-white dark:border-white dark:bg-white/[0.04]'
                        : 'border-gray-300 hover:border-gray-400 dark:border-white/[0.15] dark:hover:border-white/[0.25] bg-white dark:bg-white/[0.04]',
                ]"
            >
                <span
                    v-if="modelValue === opt.value"
                    class="h-1.5 w-1.5 rounded-full bg-gray-900 dark:bg-white"
                ></span>
            </span>
            <span :class="labelWrapperClass">
                <span class="flex items-center gap-2 text-theme-sm font-medium text-gray-800 dark:text-white/90">
                    <component v-if="opt.icon" :is="opt.icon" :size="16" class="text-gray-500 dark:text-gray-400 shrink-0" />
                    {{ opt.label }}
                </span>
                <span v-if="opt.description" class="mt-0.5 block text-theme-xs text-gray-500 dark:text-gray-400">
                    {{ opt.description }}
                </span>
            </span>
            <span
                v-if="variant === 'card' && modelValue === opt.value"
                class="absolute top-3 right-3 text-gray-900 dark:text-white"
            >
                <Check :size="18" />
            </span>
        </label>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Check } from '@lucide/vue';

const props = defineProps({
    modelValue: { type: [String, Number, Boolean, null], default: null },
    options: { type: Array, required: true },
    name: { type: String, default: () => `radio-${Math.random().toString(36).slice(2, 9)}` },
    orientation: { type: String, default: 'vertical' },
    variant: { type: String, default: 'default' },
    ariaLabel: { type: String, default: 'Options' },
});
const emit = defineEmits(['update:modelValue', 'change']);

const normalizedOptions = computed(() =>
    props.options.map(o =>
        typeof o === 'object' && o !== null
            ? { disabled: false, ...o }
            : { value: o, label: String(o), disabled: false }
    )
);

function select(opt) {
    if (opt.disabled || props.modelValue === opt.value) return;
    emit('update:modelValue', opt.value);
    emit('change', opt.value);
}

function variantWrapperClass(opt) {
    const active = props.modelValue === opt.value;
    if (props.variant === 'card') {
        return active
            ? 'rounded-xl border-2 border-gray-900 dark:border-white bg-gray-900/[0.02] dark:bg-white/[0.04] p-4 pr-10 shadow-elevation-1'
            : 'rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[color:var(--color-surface-dark)] p-4 pr-10 hover:border-gray-300 dark:hover:border-white/[0.16]';
    }
    return 'items-start gap-3';
}

const labelWrapperClass = computed(() =>
    props.variant === 'card' ? 'block' : 'block'
);
</script>
