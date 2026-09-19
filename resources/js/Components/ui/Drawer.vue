<template>
    <teleport to="body">
        <transition name="drawer-fade">
            <div
                v-if="modelValue"
                class="fixed inset-0 z-99999 bg-gray-900/60 dark:bg-black/70 backdrop-blur-sm"
                @click.self="onBackdrop"
            ></div>
        </transition>
        <transition :name="`drawer-${side}`">
            <div
                v-if="modelValue"
                ref="panel"
                :class="[
                    'fixed z-99999 flex flex-col bg-white dark:bg-[color:var(--color-surface-dark)] border-gray-200 dark:border-white/[0.08] shadow-command',
                    positionClass,
                    sizeClass,
                ]"
                role="dialog"
                aria-modal="true"
                :aria-labelledby="titleId"
                tabindex="-1"
                @keydown="onKeydown"
            >
                <div class="flex items-start justify-between gap-4 px-6 pt-5 pb-4 border-b border-gray-100 dark:border-white/[0.06]">
                    <div class="min-w-0">
                        <h3 :id="titleId" class="text-h2 text-gray-900 dark:text-white/95 truncate">{{ title }}</h3>
                        <p v-if="subtitle" class="mt-1 text-body text-gray-500 dark:text-gray-400">{{ subtitle }}</p>
                    </div>
                    <button
                        v-if="closable"
                        type="button"
                        class="-mr-1.5 -mt-0.5 inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:text-gray-800 hover:bg-gray-100 dark:hover:text-white/90 dark:hover:bg-white/[0.08] focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/25 transition-colors"
                        @click="close"
                        aria-label="Close drawer"
                    >
                        <X :size="18" />
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto custom-scrollbar px-6 py-5 text-body text-gray-700 dark:text-gray-300">
                    <slot />
                </div>
                <div v-if="$slots.footer" class="px-6 py-4 border-t border-gray-100 dark:border-white/[0.06] flex items-center justify-end gap-2 bg-gray-50/60 dark:bg-white/[0.02]">
                    <slot name="footer" />
                </div>
            </div>
        </transition>
    </teleport>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { X } from '@lucide/vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    side: { type: String, default: 'right' },
    size: { type: String, default: 'md' },
    closable: { type: Boolean, default: true },
    closeOnBackdrop: { type: Boolean, default: true },
    closeOnEscape: { type: Boolean, default: true },
});
const emit = defineEmits(['update:modelValue', 'close']);

const panel = ref(null);
const titleId = `drawer-title-${Math.random().toString(36).slice(2, 9)}`;
let lastFocused = null;

const positionClass = computed(() => {
    if (props.side === 'left') return 'top-0 left-0 h-full border-r';
    if (props.side === 'top') return 'top-0 left-0 w-full border-b';
    if (props.side === 'bottom') return 'bottom-0 left-0 w-full border-t';
    return 'top-0 right-0 h-full border-l';
});

const sizeClass = computed(() => {
    const horizontal = props.side === 'left' || props.side === 'right';
    const map = {
        sm: horizontal ? 'w-full max-w-sm' : 'h-1/3 min-h-[240px]',
        md: horizontal ? 'w-full max-w-md' : 'h-1/2 min-h-[320px]',
        lg: horizontal ? 'w-full max-w-lg' : 'h-2/3 min-h-[420px]',
        xl: horizontal ? 'w-full max-w-2xl' : 'h-3/4 min-h-[520px]',
        full: horizontal ? 'w-screen' : 'h-screen',
    };
    return map[props.size] || map.md;
});

function focusables() {
    if (!panel.value) return [];
    const nodes = panel.value.querySelectorAll(
        'a[href], input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), [tabindex]:not([tabindex="-1"])'
    );
    return Array.from(nodes).filter(el => el.offsetParent !== null);
}

function close() {
    emit('update:modelValue', false);
    emit('close');
}
function onBackdrop() { if (props.closeOnBackdrop && props.closable) close(); }

function onKeydown(e) {
    if (e.key === 'Escape' && props.closeOnEscape && props.closable) {
        e.stopPropagation();
        close();
        return;
    }
    if (e.key !== 'Tab') return;
    const list = focusables();
    if (!list.length) { e.preventDefault(); panel.value?.focus(); return; }
    const first = list[0], last = list[list.length - 1], active = document.activeElement;
    if (e.shiftKey && active === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && active === last) { e.preventDefault(); first.focus(); }
}

watch(() => props.modelValue, async (open) => {
    if (typeof document === 'undefined') return;
    document.body.style.overflow = open ? 'hidden' : '';
    if (open) {
        lastFocused = document.activeElement;
        await nextTick();
        const list = focusables();
        (list[0] || panel.value)?.focus();
    } else if (lastFocused?.focus) {
        lastFocused.focus();
        lastFocused = null;
    }
});
</script>

<style scoped>
.drawer-fade-enter-active,
.drawer-fade-leave-active { transition: opacity 0.2s ease; }
.drawer-fade-enter-from,
.drawer-fade-leave-to { opacity: 0; }

.drawer-right-enter-active,
.drawer-right-leave-active,
.drawer-left-enter-active,
.drawer-left-leave-active,
.drawer-top-enter-active,
.drawer-top-leave-active,
.drawer-bottom-enter-active,
.drawer-bottom-leave-active { transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1); }

.drawer-right-enter-from, .drawer-right-leave-to { transform: translateX(100%); }
.drawer-left-enter-from,  .drawer-left-leave-to  { transform: translateX(-100%); }
.drawer-top-enter-from,   .drawer-top-leave-to   { transform: translateY(-100%); }
.drawer-bottom-enter-from,.drawer-bottom-leave-to{ transform: translateY(100%); }
</style>
