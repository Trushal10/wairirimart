<template>
    <teleport to="body">
        <transition name="fade">
            <div
                v-if="modelValue"
                class="fixed inset-0 z-99999 flex items-center justify-center p-4 bg-gray-900/60 dark:bg-black/70 backdrop-blur-sm"
                @click.self="onBackdrop"
            >
                <div
                    ref="panel"
                    :class="[
                        'w-full rounded-2xl bg-white dark:bg-[color:var(--color-surface-dark)] border border-gray-200 dark:border-white/[0.08] shadow-command flex flex-col max-h-[90vh]',
                        sizeClass,
                        'modal-panel-enter',
                    ]"
                    role="dialog"
                    aria-modal="true"
                    :aria-labelledby="titleId"
                    :aria-describedby="subtitle ? subtitleId : undefined"
                    tabindex="-1"
                    @keydown="onKeydown"
                >
                    <div class="flex items-start justify-between gap-4 px-6 pt-5 pb-4 border-b border-gray-100 dark:border-white/[0.06]">
                        <div class="min-w-0">
                            <h3 :id="titleId" class="text-h2 text-gray-900 dark:text-white/95 truncate">
                                {{ title }}
                            </h3>
                            <p v-if="subtitle" :id="subtitleId" class="mt-1 text-body text-gray-500 dark:text-gray-400">
                                {{ subtitle }}
                            </p>
                        </div>
                        <button
                            v-if="closable"
                            type="button"
                            class="-mr-1.5 -mt-0.5 inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:text-gray-800 hover:bg-gray-100 dark:hover:text-white/90 dark:hover:bg-white/[0.08] focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/25 transition-colors"
                            @click="close"
                            aria-label="Close dialog"
                        >
                            <X :size="18" />
                        </button>
                    </div>
                    <div class="px-6 py-5 overflow-y-auto custom-scrollbar text-body text-gray-700 dark:text-gray-300">
                        <slot />
                    </div>
                    <div
                        v-if="$slots.footer"
                        class="px-6 py-4 border-t border-gray-100 dark:border-white/[0.06] flex items-center justify-end gap-2 bg-gray-50/60 dark:bg-white/[0.02]"
                    >
                        <slot name="footer" />
                    </div>
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
    size: { type: String, default: 'md' },
    closable: { type: Boolean, default: true },
    closeOnBackdrop: { type: Boolean, default: true },
    closeOnEscape: { type: Boolean, default: true },
});
const emit = defineEmits(['update:modelValue', 'close']);

const panel = ref(null);
const uid = Math.random().toString(36).slice(2, 9);
const titleId = `modal-title-${uid}`;
const subtitleId = `modal-subtitle-${uid}`;
let lastFocused = null;

const sizeClass = computed(() => ({
    sm: 'max-w-md',
    md: 'max-w-lg',
    lg: 'max-w-2xl',
    xl: 'max-w-4xl',
    full: 'max-w-[95vw]',
}[props.size] || 'max-w-lg'));

function focusables() {
    if (!panel.value) return [];
    const nodes = panel.value.querySelectorAll(
        'a[href], area[href], input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), iframe, object, embed, [tabindex]:not([tabindex="-1"]), [contenteditable="true"]'
    );
    return Array.from(nodes).filter(el => !el.hasAttribute('inert') && el.offsetParent !== null);
}

function close() {
    emit('update:modelValue', false);
    emit('close');
}

function onBackdrop() {
    if (props.closeOnBackdrop && props.closable) close();
}

function onKeydown(e) {
    if (e.key === 'Escape' && props.closeOnEscape && props.closable) {
        e.stopPropagation();
        close();
        return;
    }
    if (e.key !== 'Tab') return;
    const list = focusables();
    if (!list.length) {
        e.preventDefault();
        panel.value?.focus();
        return;
    }
    const first = list[0];
    const last = list[list.length - 1];
    const active = document.activeElement;
    if (e.shiftKey && active === first) {
        e.preventDefault();
        last.focus();
    } else if (!e.shiftKey && active === last) {
        e.preventDefault();
        first.focus();
    }
}

watch(() => props.modelValue, async (open) => {
    if (typeof document === 'undefined') return;
    document.body.style.overflow = open ? 'hidden' : '';
    if (open) {
        lastFocused = document.activeElement;
        await nextTick();
        const list = focusables();
        (list[0] || panel.value)?.focus();
    } else if (lastFocused && typeof lastFocused.focus === 'function') {
        lastFocused.focus();
        lastFocused = null;
    }
});
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
.modal-panel-enter {
    animation: cmdk-in 200ms cubic-bezier(0.16, 1, 0.3, 1) both;
}
</style>
