<template>
    <teleport to="body">
        <div
            aria-live="polite"
            aria-atomic="true"
            class="pointer-events-none fixed inset-x-0 top-4 z-99999 flex flex-col items-end gap-2.5 px-4 sm:top-6 sm:right-6 sm:left-auto sm:pl-0"
        >
            <transition-group name="toast" tag="div" class="flex flex-col items-end gap-2.5 w-full sm:w-auto">
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    role="status"
                    :class="[
                        'pointer-events-auto relative w-full sm:min-w-[340px] sm:max-w-md rounded-xl bg-white dark:bg-[color:var(--color-surface-dark)] shadow-elevation-3 border overflow-hidden',
                        frameClass(toast.kind),
                    ]"
                >
                    <!-- Left accent bar (subtle, semantic) -->
                    <span
                        class="absolute left-0 top-0 bottom-0 w-1"
                        :class="accentBar(toast.kind)"
                        aria-hidden="true"
                    ></span>

                    <div class="flex items-start gap-3 py-3 pl-4 pr-3">
                        <span
                            :class="iconWrap(toast.kind)"
                            class="mt-0.5 shrink-0 flex h-6 w-6 items-center justify-center rounded-full"
                            aria-hidden="true"
                        >
                            <component :is="iconComponent(toast.kind)" :size="14" stroke-width="2.5" />
                        </span>

                        <div class="min-w-0 flex-1 pt-0.5">
                            <p v-if="toast.title" class="text-[13.5px] font-semibold text-gray-900 dark:text-white/95 leading-5">
                                {{ toast.title }}
                            </p>
                            <p :class="['text-[13px] leading-5 text-gray-700 dark:text-gray-300 break-words', toast.title ? 'mt-0.5' : '']">
                                {{ toast.message }}
                            </p>
                            <button
                                v-if="toast.actionLabel"
                                type="button"
                                @click="handleAction(toast)"
                                class="mt-2 inline-flex items-center gap-1 text-[12.5px] font-semibold text-gray-900 dark:text-white/95 hover:underline underline-offset-2 focus-ring rounded"
                            >
                                {{ toast.actionLabel }}
                                <ArrowRight :size="12" />
                            </button>
                        </div>

                        <button
                            type="button"
                            @click="dismiss(toast.id)"
                            class="shrink-0 -mt-0.5 -mr-1 h-7 w-7 inline-flex items-center justify-center rounded-md text-gray-400 hover:text-gray-900 hover:bg-gray-100 dark:hover:text-white/95 dark:hover:bg-white/[0.08] transition-colors focus-ring"
                            aria-label="Dismiss"
                        >
                            <X :size="14" />
                        </button>
                    </div>

                    <div
                        v-if="toast.timeout > 0"
                        :class="progressBg(toast.kind)"
                        class="h-[2px] origin-left animate-[toast-progress_var(--toast-duration)_linear_forwards]"
                        :style="{ '--toast-duration': toast.timeout + 'ms' }"
                    />
                </div>
            </transition-group>
        </div>
    </teleport>
</template>

<script setup>
import { useToast } from '@/Composables/useToast';
import { CheckCircle2, XCircle, AlertTriangle, Info, X, ArrowRight } from '@lucide/vue';

const { toasts, dismiss } = useToast();

function handleAction(toast) {
    if (typeof toast.onAction === 'function') toast.onAction();
    dismiss(toast.id);
}

function iconComponent(kind) {
    return {
        success: CheckCircle2,
        error: XCircle,
        warning: AlertTriangle,
        info: Info,
    }[kind] || Info;
}

function frameClass(kind) {
    return {
        success: 'border-gray-200 dark:border-white/[0.08]',
        error:   'border-gray-200 dark:border-white/[0.08]',
        warning: 'border-gray-200 dark:border-white/[0.08]',
        info:    'border-gray-200 dark:border-white/[0.08]',
    }[kind] || 'border-gray-200 dark:border-white/[0.08]';
}

function accentBar(kind) {
    return {
        success: 'bg-success-500',
        error:   'bg-error-500',
        warning: 'bg-warning-500',
        info:    'bg-brand-500',
    }[kind] || 'bg-gray-400';
}

function iconWrap(kind) {
    return {
        success: 'bg-success-500 text-white',
        error:   'bg-error-500 text-white',
        warning: 'bg-warning-500 text-white',
        info:    'bg-brand-500 text-white',
    }[kind] || 'bg-gray-500 text-white';
}

function progressBg(kind) {
    return {
        success: 'bg-success-500/70',
        error:   'bg-error-500/70',
        warning: 'bg-warning-500/70',
        info:    'bg-brand-500/70',
    }[kind] || 'bg-gray-400/70';
}
</script>

<style scoped>
.toast-enter-active {
    transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1),
                opacity 0.28s cubic-bezier(0.16, 1, 0.3, 1);
}
.toast-leave-active {
    transition: transform 0.2s cubic-bezier(0.4, 0, 0.6, 1),
                opacity 0.2s cubic-bezier(0.4, 0, 0.6, 1);
    position: absolute;
    right: 0;
}
.toast-enter-from {
    opacity: 0;
    transform: translateX(24px) scale(0.98);
}
.toast-leave-to {
    opacity: 0;
    transform: translateX(24px) scale(0.98);
}
.toast-move {
    transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes toast-progress {
    from { transform: scaleX(1); }
    to   { transform: scaleX(0); }
}
</style>
