<template>
    <div :class="['flex flex-col items-center justify-center text-center', sizeClass]">
        <div :class="illustrationClass">
            <slot name="illustration">
                <div
                    class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400"
                >
                    <slot name="icon">
                        <Inbox :size="24" />
                    </slot>
                </div>
            </slot>
        </div>
        <h3 class="text-h2 text-gray-900 dark:text-white/95">{{ title }}</h3>
        <p
            v-if="description"
            class="mt-1.5 max-w-md text-body text-gray-500 dark:text-gray-400"
        >
            {{ description }}
        </p>
        <div
            v-if="$slots.actions"
            class="mt-5 flex flex-wrap items-center justify-center gap-2"
        >
            <slot name="actions" />
        </div>
        <div v-if="$slots.footer" class="mt-4">
            <slot name="footer" />
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Inbox } from '@lucide/vue';

const props = defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    /** sm | md | lg */
    size: { type: String, default: 'md' },
});

const sizeClass = computed(
    () =>
        ({
            sm: 'py-8 px-4',
            md: 'py-12 px-4',
            lg: 'py-16 px-6',
        }[props.size] || 'py-12 px-4')
);

const illustrationClass = computed(
    () =>
        ({
            sm: 'mb-3',
            md: 'mb-4',
            lg: 'mb-5',
        }[props.size] || 'mb-4')
);
</script>
