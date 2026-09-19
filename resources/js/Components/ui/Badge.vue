<template>
    <span :class="classes">
        <span v-if="dot" :class="dotClass" aria-hidden="true"></span>
        <slot />
    </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    /** neutral | brand | success | warning | error | info | outline */
    variant: { type: String, default: 'neutral' },
    /** sm | md | lg */
    size: { type: String, default: 'md' },
    dot: { type: Boolean, default: false },
    solid: { type: Boolean, default: false },
    /** pill | rounded */
    shape: { type: String, default: 'pill' },
});

const softMap = {
    neutral:
        'bg-gray-100 text-gray-700 ring-1 ring-inset ring-gray-200/60 dark:bg-white/[0.06] dark:text-gray-300 dark:ring-white/[0.06]',
    brand:
        'bg-brand-50 text-brand-700 ring-1 ring-inset ring-brand-100 dark:bg-brand-500/12 dark:text-brand-300 dark:ring-brand-500/25',
    success:
        'bg-success-50 text-success-700 ring-1 ring-inset ring-success-100 dark:bg-success-500/12 dark:text-success-300 dark:ring-success-500/25',
    warning:
        'bg-warning-50 text-warning-800 ring-1 ring-inset ring-warning-100 dark:bg-warning-500/12 dark:text-warning-300 dark:ring-warning-500/25',
    error:
        'bg-error-50 text-error-700 ring-1 ring-inset ring-error-100 dark:bg-error-500/12 dark:text-error-300 dark:ring-error-500/25',
    info:
        'bg-blue-light-50 text-blue-light-700 ring-1 ring-inset ring-blue-light-100 dark:bg-blue-light-500/12 dark:text-blue-light-300 dark:ring-blue-light-500/25',
    outline:
        'bg-transparent text-gray-700 ring-1 ring-inset ring-gray-300 dark:text-gray-300 dark:ring-white/[0.12]',
};

const solidMap = {
    neutral: 'bg-gray-900 text-white',
    brand: 'bg-brand-500 text-white',
    success: 'bg-success-600 text-white',
    warning: 'bg-warning-500 text-white',
    error: 'bg-error-600 text-white',
    info: 'bg-blue-light-500 text-white',
    outline: 'bg-white text-gray-800 ring-1 ring-inset ring-gray-300',
};

const dotMap = {
    neutral: 'bg-gray-500',
    brand: 'bg-brand-500',
    success: 'bg-success-500',
    warning: 'bg-warning-500',
    error: 'bg-error-500',
    info: 'bg-blue-light-500',
    outline: 'bg-gray-500',
};

const sizeMap = {
    sm: 'text-[10.5px] leading-none px-1.5 py-1 gap-1',
    md: 'text-[11.5px] leading-none px-2 py-1 gap-1.5',
    lg: 'text-[12.5px] leading-none px-2.5 py-1.5 gap-1.5',
};

const shapeClass = computed(() => (props.shape === 'rounded' ? 'rounded-md' : 'rounded-full'));

const classes = computed(() => [
    'inline-flex items-center font-medium whitespace-nowrap',
    shapeClass.value,
    (props.solid ? solidMap : softMap)[props.variant] || (props.solid ? solidMap : softMap).neutral,
    sizeMap[props.size] || sizeMap.md,
]);

const dotClass = computed(() => [
    'inline-block rounded-full shrink-0',
    props.size === 'lg' ? 'h-1.5 w-1.5' : 'h-1.5 w-1.5',
    dotMap[props.variant] || dotMap.neutral,
]);
</script>
