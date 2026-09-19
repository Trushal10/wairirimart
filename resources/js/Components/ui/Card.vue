<template>
    <div :class="wrapperClass">
        <div
            v-if="title || $slots.header || $slots.actions"
            :class="[
                'flex flex-wrap items-center justify-between gap-3',
                headerPaddingClass,
                'border-b border-gray-100 dark:border-white/[0.06]',
            ]"
        >
            <div class="min-w-0">
                <slot name="header">
                    <h3 class="text-h2 text-gray-900 dark:text-white/90 truncate">
                        {{ title }}
                    </h3>
                    <p v-if="subtitle" class="mt-0.5 text-body text-gray-500 dark:text-gray-400">
                        {{ subtitle }}
                    </p>
                </slot>
            </div>
            <div v-if="$slots.actions" class="flex items-center gap-2 shrink-0">
                <slot name="actions" />
            </div>
        </div>
        <div :class="[bodyPaddingClass, bodyClass]">
            <slot />
        </div>
        <div
            v-if="$slots.footer"
            :class="[
                footerPaddingClass,
                'border-t border-gray-100 dark:border-white/[0.06] flex items-center justify-end gap-2 bg-gray-50/40 dark:bg-white/[0.02]',
            ]"
        >
            <slot name="footer" />
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    /** flat | raised | interactive */
    mode: { type: String, default: 'flat' },
    /** none | sm | md | lg */
    padding: { type: String, default: 'md' },
    radius: { type: String, default: '2xl' }, // xl | 2xl
    bodyClass: { type: String, default: '' },
    /* Backwards-compat with previous API */
    padded: { type: Boolean, default: null },
    shadow: { type: Boolean, default: null },
});

const surfaceClass = computed(() => {
    const base = 'bg-white dark:bg-[color:var(--color-surface-dark)] border border-gray-200 dark:border-white/[0.06]';
    if (props.mode === 'raised') return `${base} shadow-elevation-1`;
    if (props.mode === 'interactive')
        return `${base} transition-shadow duration-200 hover:shadow-elevation-2`;
    return base;
});

const radiusClass = computed(() => (props.radius === 'xl' ? 'rounded-xl' : 'rounded-2xl'));

const wrapperClass = computed(() => [
    radiusClass.value,
    surfaceClass.value,
    // Backwards-compat: `shadow=true` (old) upgrades to raised
    props.shadow === true && props.mode === 'flat' ? 'shadow-elevation-1' : '',
]);

// Backwards-compat: `padded=false` (old) collapses to no body padding
const effectivePadding = computed(() =>
    props.padded === false ? 'none' : props.padding
);

const bodyPaddingClass = computed(
    () =>
        ({
            none: '',
            sm: 'p-4',
            md: 'p-5',
            lg: 'p-6',
        }[effectivePadding.value] || 'p-5')
);

const headerPaddingClass = computed(
    () =>
        ({
            none: 'px-5 py-4',
            sm: 'px-4 py-3',
            md: 'px-5 py-4',
            lg: 'px-6 py-5',
        }[effectivePadding.value] || 'px-5 py-4')
);

const footerPaddingClass = computed(
    () =>
        ({
            none: 'px-5 py-3.5',
            sm: 'px-4 py-3',
            md: 'px-5 py-3.5',
            lg: 'px-6 py-4',
        }[effectivePadding.value] || 'px-5 py-3.5')
);
</script>
