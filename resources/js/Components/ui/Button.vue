<template>
    <component
        :is="tag"
        :type="tag === 'button' ? type : undefined"
        :disabled="isDisabled"
        :class="classes"
        v-bind="$attrs"
    >
        <span
            v-if="loading"
            class="inline-flex items-center justify-center"
            aria-hidden="true"
        >
            <svg
                class="animate-spin h-4 w-4"
                viewBox="0 0 24 24"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
            >
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
        </span>
        <slot name="leading" v-if="!loading" />
        <span v-if="$slots.default" class="inline-flex items-center">
            <slot />
        </span>
        <slot name="trailing" v-if="!loading" />
    </component>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: { type: String, default: 'primary' },
    size: { type: String, default: 'md' },
    type: { type: String, default: 'button' },
    tag: { type: String, default: 'button' },
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    block: { type: Boolean, default: false },
});

const isDisabled = computed(() => props.disabled || props.loading);

// Near-black primary (Linear / Vercel / Shopify Admin style).
// Brand indigo reserved for `accent` and links elsewhere.
const variantClass = {
    primary:
        'bg-gray-900 text-white hover:bg-gray-800 active:bg-black shadow-theme-xs focus-visible:ring-gray-900/25 disabled:bg-gray-300 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100 dark:active:bg-white dark:disabled:bg-white/30',
    accent:
        'bg-brand-500 text-white hover:bg-brand-600 active:bg-brand-700 shadow-theme-xs focus-visible:ring-brand-500/30 disabled:bg-brand-300 dark:disabled:bg-brand-800',
    secondary:
        'bg-white text-gray-800 border border-gray-200 hover:bg-gray-50 active:bg-gray-100 shadow-theme-xs focus-visible:ring-gray-900/20 dark:bg-white/[0.04] dark:text-gray-100 dark:border-white/[0.08] dark:hover:bg-white/[0.08] dark:active:bg-white/[0.12]',
    subtle:
        'bg-gray-100 text-gray-800 hover:bg-gray-200 active:bg-gray-300 focus-visible:ring-gray-900/20 dark:bg-white/[0.06] dark:text-gray-100 dark:hover:bg-white/[0.1] dark:active:bg-white/[0.14]',
    ghost:
        'bg-transparent text-gray-700 hover:bg-gray-100 active:bg-gray-200 focus-visible:ring-gray-900/15 dark:text-gray-300 dark:hover:bg-white/[0.06] dark:active:bg-white/[0.1]',
    danger:
        'bg-error-600 text-white hover:bg-error-700 active:bg-error-800 shadow-theme-xs focus-visible:ring-error-500/30 disabled:bg-error-300',
    'danger-outline':
        'bg-white text-error-700 border border-error-200 hover:bg-error-50 active:bg-error-100 focus-visible:ring-error-500/20 dark:bg-transparent dark:text-error-300 dark:border-error-500/30 dark:hover:bg-error-500/10',
    success:
        'bg-success-600 text-white hover:bg-success-700 active:bg-success-800 shadow-theme-xs focus-visible:ring-success-500/30 disabled:bg-success-300',
    warning:
        'bg-warning-500 text-white hover:bg-warning-600 active:bg-warning-700 shadow-theme-xs focus-visible:ring-warning-500/30 disabled:bg-warning-300',
    link:
        'bg-transparent text-brand-600 hover:text-brand-700 hover:underline underline-offset-2 focus-visible:ring-brand-500/25 dark:text-brand-400',
};

const sizeClass = {
    xs: 'h-7  text-[12px] px-2.5 gap-1.5 rounded-md',
    sm: 'h-8  text-[13px] px-3   gap-1.5 rounded-md',
    md: 'h-10 text-[13px] px-3.5 gap-2   rounded-lg',
    lg: 'h-11 text-[14px] px-4.5 gap-2   rounded-lg',
    xl: 'h-12 text-[15px] px-5   gap-2   rounded-xl',
};

const classes = computed(() => [
    'inline-flex items-center justify-center font-medium whitespace-nowrap select-none',
    'transition-[background-color,box-shadow,color,border-color] duration-150 ease-[cubic-bezier(0.4,0,0.2,1)]',
    'focus:outline-none focus-visible:ring-4 disabled:opacity-60 disabled:cursor-not-allowed disabled:shadow-none',
    variantClass[props.variant] || variantClass.primary,
    sizeClass[props.size] || sizeClass.md,
    props.block ? 'w-full' : '',
]);
</script>
