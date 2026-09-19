<template>
    <span class="inline-flex" @mouseenter="open" @mouseleave="close" @focusin="open" @focusout="close">
        <span
            ref="trigger"
            :aria-describedby="visible ? tooltipId : undefined"
            class="inline-flex"
        >
            <slot />
        </span>
        <teleport to="body">
            <transition name="tooltip">
                <div
                    v-if="visible && content"
                    ref="tip"
                    :id="tooltipId"
                    role="tooltip"
                    :style="floatStyle"
                    class="pointer-events-none fixed z-99999 max-w-xs px-2.5 py-1.5 rounded-md text-[11.5px] font-medium leading-snug shadow-tooltip bg-gray-900 text-white dark:bg-white dark:text-gray-900"
                >
                    {{ content }}
                    <span
                        class="absolute w-2 h-2 rotate-45 bg-gray-900 dark:bg-white"
                        :style="arrowStyle"
                    ></span>
                </div>
            </transition>
        </teleport>
    </span>
</template>

<script setup>
import { nextTick, onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    content: { type: String, default: '' },
    placement: { type: String, default: 'top' },
    delay: { type: Number, default: 120 },
    offset: { type: Number, default: 8 },
});

const trigger = ref(null);
const tip = ref(null);
const visible = ref(false);
const floatStyle = ref({});
const arrowStyle = ref({});
const tooltipId = `tooltip-${Math.random().toString(36).slice(2, 9)}`;
let showT = null;
let hideT = null;

async function position() {
    if (!trigger.value || !tip.value) return;
    const t = trigger.value.getBoundingClientRect();
    const tw = tip.value.offsetWidth;
    const th = tip.value.offsetHeight;
    let top = 0, left = 0, arrowTop = '', arrowLeft = '';
    switch (props.placement) {
        case 'bottom':
            top = t.bottom + props.offset;
            left = t.left + t.width / 2 - tw / 2;
            arrowTop = '-4px';
            arrowLeft = `${tw / 2 - 4}px`;
            break;
        case 'left':
            top = t.top + t.height / 2 - th / 2;
            left = t.left - tw - props.offset;
            arrowTop = `${th / 2 - 4}px`;
            arrowLeft = `${tw - 4}px`;
            break;
        case 'right':
            top = t.top + t.height / 2 - th / 2;
            left = t.right + props.offset;
            arrowTop = `${th / 2 - 4}px`;
            arrowLeft = '-4px';
            break;
        case 'top':
        default:
            top = t.top - th - props.offset;
            left = t.left + t.width / 2 - tw / 2;
            arrowTop = `${th - 4}px`;
            arrowLeft = `${tw / 2 - 4}px`;
    }
    const pad = 8;
    left = Math.max(pad, Math.min(left, window.innerWidth - tw - pad));
    top = Math.max(pad, Math.min(top, window.innerHeight - th - pad));
    floatStyle.value = { top: `${top}px`, left: `${left}px` };
    arrowStyle.value = { top: arrowTop, left: arrowLeft };
}

async function open() {
    if (!props.content) return;
    clearTimeout(hideT);
    showT = setTimeout(async () => {
        visible.value = true;
        await nextTick();
        position();
    }, props.delay);
}
function close() {
    clearTimeout(showT);
    hideT = setTimeout(() => (visible.value = false), 80);
}
onBeforeUnmount(() => {
    clearTimeout(showT);
    clearTimeout(hideT);
});
</script>

<style scoped>
.tooltip-enter-active,
.tooltip-leave-active {
    transition: opacity 0.14s ease, transform 0.14s ease;
}
.tooltip-enter-from,
.tooltip-leave-to {
    opacity: 0;
    transform: translateY(2px);
}
</style>
