<template>
    <div class="min-h-screen bg-[color:var(--color-canvas)] dark:bg-[color:var(--color-canvas-dark)]">
        <!-- Sidebar (fixed, always) -->
        <Sidebar :open="sidebarOpen" @close="sidebarOpen = false" />

        <!-- Mobile backdrop -->
        <transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="sidebarOpen"
                @click="sidebarOpen = false"
                class="fixed inset-0 z-40 bg-gray-900/50 dark:bg-black/60 backdrop-blur-sm lg:hidden"
                aria-hidden="true"
            ></div>
        </transition>

        <!-- Main column (padded to the right of the fixed sidebar on desktop) -->
        <div
            :class="[
                'flex min-h-screen flex-col transition-[padding-left] duration-200 ease-[cubic-bezier(0.4,0,0.2,1)]',
                sidebarOpen ? 'lg:pl-[260px]' : 'lg:pl-16',
            ]"
        >
            <Header
                :user="user"
                :sidebarToggle="sidebarOpen"
                @sidebarToggle="sidebarOpen = !sidebarOpen"
                @openCommandPalette="openPalette"
                @openHelp="openHelp"
            />

            <main class="flex-1">
                <div class="mx-auto w-full max-w-[1440px] px-4 py-5 sm:px-6 lg:px-8 lg:py-7">
                    <slot />
                </div>
            </main>

            <footer class="mt-auto border-t border-gray-200/60 dark:border-white/[0.06] bg-transparent">
                <div class="mx-auto flex w-full max-w-[1440px] flex-col items-center justify-between gap-2 px-4 py-4 text-[11.5px] text-gray-500 dark:text-gray-500 sm:flex-row sm:px-6 lg:px-8">
                    <span>&copy; {{ new Date().getFullYear() }} {{ appName }}. All rights reserved.</span>
                    <button
                        type="button"
                        @click="openHelp"
                        class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 -mx-2 -my-1 hover:text-gray-800 hover:bg-gray-100 dark:hover:text-gray-200 dark:hover:bg-white/[0.05] focus-ring transition-colors"
                    >
                        <kbd class="inline-flex h-4.5 items-center rounded border border-gray-300 dark:border-white/[0.15] bg-white dark:bg-white/[0.04] px-1.5 text-[10px] font-medium">?</kbd>
                        Keyboard shortcuts
                    </button>
                </div>
            </footer>
        </div>

        <ToastContainer />
        <CommandPalette />
        <ShortcutsHelp />
    </div>
</template>

<script>
import { router, usePage } from '@inertiajs/vue3';
import Sidebar from '@/Components/Sidebar.vue';
import Header from '@/Components/Header.vue';
import { ToastContainer, CommandPalette, ShortcutsHelp } from '@/Components/ui';
import { useToast } from '@/Composables/useToast';
import { useCommandPalette } from '@/Composables/useCommandPalette';
import { useShortcuts } from '@/Composables/useShortcuts';

const STORAGE_KEY = 'admin.sidebar.open';

export default {
    components: { Sidebar, Header, ToastContainer, CommandPalette, ShortcutsHelp },
    props: {
        user: Object,
        appName: { type: String, default: 'Admin' },
    },
    data() {
        return { sidebarOpen: false };
    },
    created() {
        this.toast = useToast();
        this.palette = useCommandPalette();
        this.shortcuts = useShortcuts();

        // Initial sidebar state — read stored preference; fall back to
        // "visible on desktop, hidden on mobile" if nothing is saved.
        if (typeof window !== 'undefined') {
            let stored = null;
            try { stored = window.localStorage.getItem(STORAGE_KEY); } catch (_) { /* no-op */ }
            if (stored === '1' || stored === '0') {
                this.sidebarOpen = stored === '1';
            } else {
                this.sidebarOpen = window.matchMedia?.('(min-width: 1024px)').matches ?? true;
            }
        }
    },
    mounted() {
        this.shortcuts.install();

        // Fire flash → toast for the initial page load.
        this.flashToToast(usePage().props.flash);

        // Fire flash → toast after every Inertia visit. This is more reliable
        // than watching usePage().props.flash — the reactive proxy doesn't
        // always trigger deep watchers on nested key changes, and a repeated
        // flash message with the same text would silently no-op.
        this._removeVisitListener = router.on('success', (event) => {
            const page = event?.detail?.page || usePage();
            this.flashToToast(page.props?.flash);
        });
    },
    beforeUnmount() {
        this.shortcuts.uninstall();
        if (typeof this._removeVisitListener === 'function') {
            this._removeVisitListener();
            this._removeVisitListener = null;
        }
    },
    watch: {
        sidebarOpen(v) {
            try { window.localStorage.setItem(STORAGE_KEY, v ? '1' : '0'); } catch (_) { /* no-op */ }
        },
    },
    methods: {
        openPalette() { this.palette.open(); },
        openHelp() { this.shortcuts.openHelp(); },
        flashToToast(flash) {
            if (!flash) return;
            if (flash.success) this.toast.success(flash.success);
            if (flash.error)   this.toast.error(flash.error);
            if (flash.info)    this.toast.info(flash.info);
            if (flash.warning) this.toast.warning(flash.warning);
        },
    },
};
</script>
