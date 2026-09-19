<template>
    <textarea
        :id="id"
        :value="modelValue"
        :placeholder="placeholder"
        :rows="rows"
        :disabled="disabled"
        :readonly="readonly"
        :required="required"
        :class="[
            'w-full rounded-lg border bg-white px-3 py-2.5 text-[13.5px] leading-5 text-gray-900 placeholder:text-gray-400',
            'shadow-theme-xs transition-[border-color,box-shadow,background-color] duration-150 resize-y',
            'focus:outline-none focus-visible:ring-4',
            'dark:bg-white/[0.03] dark:text-white/90 dark:placeholder:text-white/30',
            error
                ? 'border-error-300 focus-visible:border-error-500 focus-visible:ring-error-500/15 dark:border-error-500/50'
                : 'border-gray-200 hover:border-gray-300 focus-visible:border-brand-400 focus-visible:ring-brand-500/15 dark:border-white/[0.08] dark:hover:border-white/[0.14] dark:focus-visible:border-brand-500',
            disabled ? 'opacity-60 cursor-not-allowed bg-gray-50 dark:bg-white/[0.02]' : '',
        ]"
        @input="$emit('update:modelValue', $event.target.value)"
        @blur="$emit('blur', $event)"
    />
</template>

<script setup>
defineProps({
    modelValue: { type: [String, Number], default: '' },
    id: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    rows: { type: [String, Number], default: 4 },
    disabled: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
    error: { type: Boolean, default: false },
});
defineEmits(['update:modelValue', 'blur']);
</script>
