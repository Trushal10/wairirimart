<template>
    <div class="relative">
        <input
            :type="visible ? 'text' : 'password'"
            :value="modelValue"
            :placeholder="placeholder"
            :disabled="disabled"
            :required="required"
            :autocomplete="autocomplete"
            :class="[
                'w-full rounded-lg border bg-white text-[13.5px] leading-5 text-gray-900 placeholder:text-gray-400',
                'shadow-theme-xs transition-[border-color,box-shadow,background-color] duration-150',
                'focus:outline-none focus-visible:ring-4',
                'dark:bg-white/[0.03] dark:text-white/90 dark:placeholder:text-white/30',
                'pl-3 pr-20',
                sizeClass,
                error
                    ? 'border-error-300 focus-visible:border-error-500 focus-visible:ring-error-500/15 dark:border-error-500/50'
                    : 'border-gray-200 hover:border-gray-300 focus-visible:border-brand-400 focus-visible:ring-brand-500/15 dark:border-white/[0.08] dark:hover:border-white/[0.14] dark:focus-visible:border-brand-500',
                disabled ? 'opacity-60 cursor-not-allowed bg-gray-50 dark:bg-white/[0.02]' : '',
            ]"
            @input="$emit('update:modelValue', $event.target.value)"
        />
        <div class="absolute inset-y-0 right-0 flex items-center pr-1.5 gap-1">
            <button
                v-if="modelValue"
                type="button"
                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 h-8 w-8 inline-flex items-center justify-center rounded"
                @click="copy"
                :title="copied ? 'Copied!' : 'Copy'"
            >
                <svg v-if="!copied" width="14" height="14" viewBox="0 0 24 24" fill="none">
                    <rect x="9" y="9" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.8"/>
                    <path d="M5 15V5a2 2 0 012-2h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
                <svg v-else class="text-success-500" width="14" height="14" viewBox="0 0 24 24" fill="none">
                    <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
            <button
                type="button"
                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 h-8 w-8 inline-flex items-center justify-center rounded"
                @click="visible = !visible"
                :title="visible ? 'Hide' : 'Show'"
            >
                <svg v-if="!visible" width="16" height="16" viewBox="0 0 24 24" fill="none">
                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z" stroke="currentColor" stroke-width="1.6"/>
                    <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/>
                </svg>
                <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none">
                    <path d="M17.94 17.94A10.94 10.94 0 0112 19c-6.5 0-10-7-10-7a19.55 19.55 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c6.5 0 10 7 10 7a19.71 19.71 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24M1 1l22 22" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
    autocomplete: { type: String, default: 'new-password' },
    error: { type: Boolean, default: false },
    size: { type: String, default: 'md' },
});
defineEmits(['update:modelValue']);

const visible = ref(false);
const copied = ref(false);
const sizeClass = computed(() =>
    props.size === 'sm' ? 'h-9' : props.size === 'lg' ? 'h-11' : 'h-10'
);

async function copy() {
    try {
        await navigator.clipboard.writeText(props.modelValue);
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 1500);
    } catch (e) {
        // ignore — copy is a nicety
    }
}
</script>
