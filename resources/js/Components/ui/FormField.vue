<template>
    <div class="flex flex-col gap-1.5">
        <label
            v-if="label || $slots.label"
            :for="forId"
            class="inline-flex items-baseline gap-1 text-[13px] leading-4 font-medium text-gray-800 dark:text-gray-200"
        >
            <slot name="label">{{ label }}</slot>
            <span v-if="required" class="text-error-500 leading-none" aria-hidden="true">*</span>
            <span v-if="optional" class="ml-1 text-[11px] font-normal text-gray-400 dark:text-gray-500">(optional)</span>
        </label>
        <slot />
        <p
            v-if="hint && !error"
            class="text-[12px] leading-4 text-gray-500 dark:text-gray-400"
        >
            {{ hint }}
        </p>
        <p
            v-if="error"
            class="inline-flex items-start gap-1 text-[12px] leading-4 text-error-600 dark:text-error-400"
            role="alert"
        >
            <AlertCircle class="h-3.5 w-3.5 shrink-0 mt-px" :size="14" />
            <span>{{ error }}</span>
        </p>
    </div>
</template>

<script setup>
import { AlertCircle } from '@lucide/vue';

defineProps({
    label: { type: String, default: '' },
    forId: { type: String, default: '' },
    required: { type: Boolean, default: false },
    optional: { type: Boolean, default: false },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
});
</script>
