<template>
    <teleport to="body">
        <div
            v-if="state.helpOpen"
            class="fixed inset-0 z-99999 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="shortcuts-title"
            @click.self="closeHelp"
        >
            <div class="absolute inset-0 bg-gray-900/50 dark:bg-black/70 backdrop-blur-sm" style="animation: cmdk-backdrop-in 0.2s ease-out;" @click="closeHelp"/>
            <div
                class="relative w-full max-w-lg rounded-2xl bg-white dark:bg-[color:var(--color-surface-dark)] border border-gray-200 dark:border-white/[0.08] shadow-command overflow-hidden"
                style="animation: cmdk-in 0.28s cubic-bezier(0.16, 1, 0.3, 1);"
            >
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-white/[0.06]">
                    <h3 id="shortcuts-title" class="text-base font-semibold text-gray-800 dark:text-white/90">Keyboard shortcuts</h3>
                    <button
                        type="button"
                        class="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 h-8 w-8 inline-flex items-center justify-center rounded-lg focus-ring"
                        @click="closeHelp"
                        aria-label="Close"
                    >
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>

                <div class="max-h-[60vh] overflow-y-auto custom-scrollbar px-5 py-4">
                    <div v-for="group in groups" :key="group.name" class="mb-4 last:mb-0">
                        <p class="text-[10px] font-semibold uppercase text-gray-400 tracking-wider mb-2">{{ group.name }}</p>
                        <ul class="space-y-1.5">
                            <li
                                v-for="item in group.items"
                                :key="item.label"
                                class="flex items-center justify-between rounded-lg px-3 py-2 hover:bg-gray-50 dark:hover:bg-white/[0.03]"
                            >
                                <span class="text-theme-sm text-gray-700 dark:text-gray-300">{{ item.label }}</span>
                                <span class="flex items-center gap-1">
                                    <kbd
                                        v-for="k in item.keys"
                                        :key="k"
                                        class="inline-flex items-center rounded border border-gray-200 dark:border-white/[0.12] bg-gray-50 dark:bg-white/[0.04] px-1.5 py-0.5 text-[10px] font-medium text-gray-600 dark:text-gray-300"
                                    >
                                        {{ k }}
                                    </kbd>
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="border-t border-gray-100 dark:border-white/[0.06] px-5 py-3 text-[11px] text-gray-500 dark:text-gray-400 flex items-center justify-between">
                    <span>Chord shortcuts: press keys in sequence.</span>
                    <span>Press <kbd class="inline-flex items-center rounded border border-gray-200 dark:border-white/[0.12] bg-gray-50 dark:bg-white/[0.04] px-1.5 py-0.5 text-[10px] font-medium">ESC</kbd> to close.</span>
                </div>
            </div>
        </div>
    </teleport>
</template>

<script>
import { useShortcuts } from '@/Composables/useShortcuts';

export default {
    setup() {
        return useShortcuts();
    },
    computed: {
        groups() {
            const grouped = new Map();
            this.legend.forEach(item => {
                const g = item.group || 'General';
                if (!grouped.has(g)) grouped.set(g, []);
                grouped.get(g).push(item);
            });
            const order = ['General', 'Navigate'];
            return order
                .filter(name => grouped.has(name))
                .map(name => ({ name, items: grouped.get(name) }))
                .concat(
                    [...grouped.keys()]
                        .filter(name => !order.includes(name))
                        .map(name => ({ name, items: grouped.get(name) }))
                );
        },
    },
};
</script>
