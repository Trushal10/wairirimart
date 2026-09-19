<template>
    <div class="relative">
        <div
            v-if="$slots.leading"
            class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500"
        >
            <slot name="leading" />
        </div>
        <input
            ref="input"
            :id="id"
            :type="type"
            :value="modelValue"
            :placeholder="placeholder"
            :disabled="disabled"
            :readonly="readonly"
            :required="required"
            :autocomplete="autocomplete"
            :class="[
                'w-full rounded-lg border bg-white text-[13.5px] leading-5 text-gray-900 placeholder:text-gray-400',
                'shadow-theme-xs transition-[border-color,box-shadow,background-color] duration-150',
                'focus:outline-none focus-visible:ring-4',
                'dark:bg-white/[0.03] dark:text-white/90 dark:placeholder:text-white/30',
                paddings,
                sizeClass,
                isDateLike ? 'cursor-pointer' : '',
                error
                    ? 'border-error-300 focus-visible:border-error-500 focus-visible:ring-error-500/15 dark:border-error-500/50'
                    : 'border-gray-200 hover:border-gray-300 focus-visible:border-brand-400 focus-visible:ring-brand-500/15 dark:border-white/[0.08] dark:hover:border-white/[0.14] dark:focus-visible:border-brand-500',
                disabled ? 'opacity-60 cursor-not-allowed bg-gray-50 dark:bg-white/[0.02]' : '',
            ]"
            @input="$emit('update:modelValue', $event.target.value)"
            @blur="$emit('blur', $event)"
            @focus="$emit('focus', $event)"
            @click="onClick"
            @keydown="onKeydown"
        />
        <!-- Auto-injected calendar/clock icon for date-like inputs.
             Sits above the transparent native picker indicator so clicks fall
             through to open the browser's date picker. Falls back to
             showPicker() via the input click handler for other browsers. -->
        <div
            v-if="isDateLike && !$slots.trailing"
            class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 dark:text-gray-500"
            aria-hidden="true"
        >
            <Clock v-if="type === 'time'" :size="15" />
            <Calendar v-else :size="15" />
        </div>
        <div
            v-if="$slots.trailing"
            class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 dark:text-gray-500"
        >
            <slot name="trailing" />
        </div>
    </div>
</template>

<script setup>
import { computed, useSlots } from 'vue';
import { Calendar, Clock } from '@lucide/vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    type: { type: String, default: 'text' },
    id: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
    autocomplete: { type: String, default: 'off' },
    error: { type: Boolean, default: false },
    /** sm | md | lg */
    size: { type: String, default: 'md' },
});

defineEmits(['update:modelValue', 'blur', 'focus']);

const slots = useSlots();

const isDateLike = computed(() =>
    ['date', 'time', 'datetime-local', 'month', 'week'].includes(props.type)
);

const sizeClass = computed(() =>
    props.size === 'sm' ? 'h-9' : props.size === 'lg' ? 'h-11' : 'h-10'
);

const paddings = computed(() => {
    const left = slots.leading ? 'pl-9' : 'pl-3';
    // Reserve right padding when we're rendering the auto date icon OR a trailing slot.
    const right = (slots.trailing || isDateLike.value) ? 'pr-9' : 'pr-3';
    return `${left} ${right}`;
});

// Fallback: force the native picker to open on click. Some browsers only
// pop the picker when the calendar-indicator itself is clicked (which we've
// overlaid transparently), so this ensures a click anywhere on the input
// still opens the picker.
function onClick(e) {
    if (!isDateLike.value || props.disabled || props.readonly) return;
    const el = e.currentTarget;
    if (typeof el.showPicker === 'function') {
        try { el.showPicker(); } catch (_) { /* silently ignore */ }
    }
}

// Keyboard: Space or Enter on a focused date input should also open the picker.
function onKeydown(e) {
    if (!isDateLike.value || props.disabled || props.readonly) return;
    if (e.key === ' ' || e.key === 'Enter') {
        const el = e.currentTarget;
        if (typeof el.showPicker === 'function') {
            e.preventDefault();
            try { el.showPicker(); } catch (_) { /* silently ignore */ }
        }
    }
}
</script>
