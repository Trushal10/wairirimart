<template>
    <label :class="['inline-flex items-start gap-2.5', disabled ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer']">
        <span class="relative inline-flex items-center">
            <input
                ref="input"
                type="checkbox"
                :checked="isChecked"
                :disabled="disabled"
                :value="value"
                :aria-checked="indeterminate ? 'mixed' : isChecked"
                class="peer sr-only"
                @change="onChange"
            />
            <span
                :class="[
                    'flex h-4.5 w-4.5 items-center justify-center rounded-[5px] border shadow-theme-xs transition-colors duration-150',
                    (isChecked || indeterminate)
                        ? 'border-gray-900 bg-gray-900 dark:border-white dark:bg-white'
                        : 'border-gray-300 bg-white hover:border-gray-400 dark:border-white/[0.15] dark:bg-white/[0.04] dark:hover:border-white/[0.25]',
                    'peer-focus-visible:ring-4 peer-focus-visible:ring-brand-500/25',
                ]"
            >
                <svg
                    v-if="indeterminate"
                    class="h-3 w-3 text-white dark:text-gray-900"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="3"
                    stroke-linecap="round"
                >
                    <line x1="5" y1="12" x2="19" y2="12" />
                </svg>
                <svg
                    v-else-if="isChecked"
                    class="h-3 w-3 text-white dark:text-gray-900"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="3"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </span>
        </span>
        <span v-if="$slots.default" class="text-theme-sm text-gray-700 dark:text-gray-300 leading-5">
            <slot />
        </span>
    </label>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: [Boolean, Array], default: false },
    value: { type: [String, Number, Boolean, Object], default: null },
    disabled: { type: Boolean, default: false },
    indeterminate: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue', 'change']);

const input = ref(null);

const isChecked = computed(() => {
    if (Array.isArray(props.modelValue)) {
        return props.modelValue.some(v => v == props.value);
    }
    return !!props.modelValue;
});

function syncIndeterminate() {
    if (input.value) input.value.indeterminate = !!props.indeterminate;
}

onMounted(syncIndeterminate);
watch(() => props.indeterminate, syncIndeterminate);

function onChange(e) {
    if (Array.isArray(props.modelValue)) {
        const next = [...props.modelValue];
        const idx = next.findIndex(v => v == props.value);
        if (e.target.checked && idx === -1) next.push(props.value);
        if (!e.target.checked && idx !== -1) next.splice(idx, 1);
        emit('update:modelValue', next);
    } else {
        emit('update:modelValue', e.target.checked);
    }
    emit('change', e.target.checked);
}
</script>
