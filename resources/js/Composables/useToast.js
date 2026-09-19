import { reactive } from 'vue';

const state = reactive({
    toasts: [],
});

let idCounter = 0;

function push(kind, message, options = {}) {
    const id = ++idCounter;
    const toast = {
        id,
        kind,
        title: options.title || null,
        message: message || '',
        timeout: options.timeout ?? 4500,
        actionLabel: options.actionLabel || null,
        onAction: options.onAction || null,
    };
    state.toasts.push(toast);
    if (toast.timeout > 0) {
        setTimeout(() => dismiss(id), toast.timeout);
    }
    return id;
}

function dismiss(id) {
    const i = state.toasts.findIndex(t => t.id === id);
    if (i !== -1) state.toasts.splice(i, 1);
}

function clear() {
    state.toasts.splice(0, state.toasts.length);
}

export function useToast() {
    return {
        toasts: state.toasts,
        success: (msg, opts) => push('success', msg, opts),
        error:   (msg, opts) => push('error', msg, opts),
        warning: (msg, opts) => push('warning', msg, opts),
        info:    (msg, opts) => push('info', msg, opts),
        dismiss,
        clear,
    };
}
