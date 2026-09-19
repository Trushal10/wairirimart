<template>
    <span
        :class="[
            'inline-block relative overflow-hidden',
            'bg-gray-200/70 dark:bg-white/[0.06]',
            rounded,
            className,
            animated ? 'skeleton-shimmer' : '',
        ]"
        :style="style"
        aria-hidden="true"
    />
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    width: { type: [String, Number], default: '100%' },
    height: { type: [String, Number], default: 12 },
    circle: { type: Boolean, default: false },
    rounded: { type: String, default: 'rounded-md' },
    animated: { type: Boolean, default: true },
    class: { type: String, default: '' },
});

const style = computed(() => ({
    width: typeof props.width === 'number' ? props.width + 'px' : props.width,
    height: typeof props.height === 'number' ? props.height + 'px' : props.height,
    borderRadius: props.circle ? '9999px' : undefined,
}));

const className = computed(() => props.class);
</script>

<style scoped>
.skeleton-shimmer::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.55),
        transparent
    );
    animation: skeleton-shimmer 1.6s ease-in-out infinite;
}
:global(.dark) .skeleton-shimmer::after {
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.06),
        transparent
    );
}
</style>
