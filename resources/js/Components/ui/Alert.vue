<template>
    <div :class="['flex gap-3 rounded-xl border p-3.5', variantClass]" role="alert">
        <span class="shrink-0">
            <slot name="icon">
                <component :is="iconComponent" :size="18" class="mt-px" />
            </slot>
        </span>
        <div class="min-w-0 flex-1 space-y-0.5">
            <p v-if="title" class="text-[13.5px] leading-5 font-semibold">{{ title }}</p>
            <div :class="['text-[13px] leading-5', title ? 'opacity-90' : '']">
                <slot>{{ message }}</slot>
            </div>
            <div v-if="$slots.actions" class="mt-2 flex flex-wrap items-center gap-2">
                <slot name="actions" />
            </div>
        </div>
        <button
            v-if="dismissible"
            type="button"
            class="shrink-0 opacity-70 hover:opacity-100 transition -mr-1 -mt-0.5 h-6 w-6 inline-flex items-center justify-center rounded-md hover:bg-black/5 dark:hover:bg-white/[0.08]"
            @click="$emit('dismiss')"
            aria-label="Dismiss"
        >
            <X :size="14" />
        </button>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import {
    AlertTriangle,
    CheckCircle2,
    Info,
    XCircle,
    X,
} from '@lucide/vue';

const props = defineProps({
    /** info | success | warning | error */
    variant: { type: String, default: 'info' },
    title: { type: String, default: '' },
    message: { type: String, default: '' },
    dismissible: { type: Boolean, default: false },
});
defineEmits(['dismiss']);

const iconComponent = computed(
    () =>
        ({
            info: Info,
            success: CheckCircle2,
            warning: AlertTriangle,
            error: XCircle,
        }[props.variant] || Info)
);

const variantClass = computed(
    () =>
        ({
            info: 'bg-blue-light-50 border-blue-light-200 text-blue-light-900 dark:bg-blue-light-500/10 dark:border-blue-light-500/25 dark:text-blue-light-200 [&_svg]:text-blue-light-600 dark:[&_svg]:text-blue-light-400',
            success:
                'bg-success-50 border-success-200 text-success-900 dark:bg-success-500/10 dark:border-success-500/25 dark:text-success-200 [&_svg]:text-success-600 dark:[&_svg]:text-success-400',
            warning:
                'bg-warning-50 border-warning-200 text-warning-900 dark:bg-warning-500/10 dark:border-warning-500/25 dark:text-warning-200 [&_svg]:text-warning-600 dark:[&_svg]:text-warning-400',
            error:
                'bg-error-50 border-error-200 text-error-900 dark:bg-error-500/10 dark:border-error-500/25 dark:text-error-200 [&_svg]:text-error-600 dark:[&_svg]:text-error-400',
        }[props.variant] ||
        'bg-gray-50 border-gray-200 text-gray-900 dark:bg-white/[0.04] dark:border-white/[0.08] dark:text-gray-200 [&_svg]:text-gray-500')
);
</script>
