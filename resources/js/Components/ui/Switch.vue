<template>
    <label :class="['inline-flex items-center gap-3', disabled ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer']">
        <span
            role="switch"
            :aria-checked="modelValue"
            :tabindex="disabled ? -1 : 0"
            :class="[
                'relative inline-flex shrink-0 items-center rounded-full transition-colors duration-200 shadow-theme-xs',
                'focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/25',
                sizeTrack,
                modelValue ? 'bg-gray-900 dark:bg-white' : 'bg-gray-200 dark:bg-white/[0.1]',
            ]"
            @click="toggle"
            @keydown.space.prevent="toggle"
            @keydown.enter.prevent="toggle"
        >
            <span
                :class="[
                    'inline-block bg-white dark:bg-gray-900 rounded-full shadow-theme-sm transform transition-transform duration-200 ease-[cubic-bezier(0.4,0,0.2,1)]',
                    sizeThumb,
                    modelValue ? translateOn : 'translate-x-0.5',
                ]"
            ></span>
        </span>
        <span v-if="$slots.default" class="text-theme-sm text-gray-700 dark:text-gray-300">
            <slot />
        </span>
    </label>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    size: { type: String, default: 'md' },
});
const emit = defineEmits(['update:modelValue']);

function toggle() {
    if (props.disabled) return;
    emit('update:modelValue', !props.modelValue);
}

const sizeTrack = computed(() =>
    props.size === 'sm' ? 'h-5 w-9' : props.size === 'lg' ? 'h-7 w-13' : 'h-6 w-11'
);
const sizeThumb = computed(() =>
    props.size === 'sm' ? 'h-4 w-4' : props.size === 'lg' ? 'h-6 w-6' : 'h-5 w-5'
);
const translateOn = computed(() =>
    props.size === 'sm' ? 'translate-x-4' : props.size === 'lg' ? 'translate-x-6' : 'translate-x-5'
);
</script>
