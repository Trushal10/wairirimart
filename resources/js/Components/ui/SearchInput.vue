<template>
    <div class="relative w-full">
        <span
            class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500"
        >
            <Search :size="15" />
        </span>
        <input
            :type="type"
            :value="modelValue"
            :placeholder="placeholder"
            :class="[
                'w-full rounded-lg border border-gray-200 bg-white pl-9 pr-8 text-[13.5px] leading-5 text-gray-900 placeholder:text-gray-400',
                'shadow-theme-xs transition-[border-color,box-shadow,background-color] duration-150',
                'focus:outline-none focus-visible:ring-4 hover:border-gray-300 focus-visible:border-brand-400 focus-visible:ring-brand-500/15',
                'dark:bg-white/[0.03] dark:border-white/[0.08] dark:text-white/90 dark:placeholder:text-white/30 dark:hover:border-white/[0.14] dark:focus-visible:border-brand-500',
                sizeClass,
            ]"
            @input="$emit('update:modelValue', $event.target.value)"
            @keydown.enter.prevent="$emit('search', modelValue)"
        />
        <button
            v-if="modelValue"
            type="button"
            class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors"
            @click="$emit('update:modelValue', ''); $emit('search', '');"
            aria-label="Clear search"
        >
            <X :size="14" />
        </button>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Search, X } from '@lucide/vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    type: { type: String, default: 'search' },
    placeholder: { type: String, default: 'Search…' },
    size: { type: String, default: 'md' },
});
defineEmits(['update:modelValue', 'search']);

const sizeClass = computed(() =>
    props.size === 'sm' ? 'h-9' : props.size === 'lg' ? 'h-11' : 'h-10'
);
</script>
