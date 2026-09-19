<template>
    <header
        class="sticky top-0 z-30 flex h-16 items-center gap-2 border-b border-gray-200/70 dark:border-white/[0.06] bg-[color:var(--color-canvas)]/85 dark:bg-[color:var(--color-canvas-dark)]/85 backdrop-blur-md px-4 sm:px-6 lg:px-8"
    >
        <!-- Sidebar toggle -->
        <button
            @click="$emit('sidebarToggle')"
            class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 hover:text-gray-900 hover:bg-gray-100 dark:hover:text-white/95 dark:hover:bg-white/[0.06] transition-colors focus-ring shrink-0"
            :aria-label="sidebarToggle ? 'Close sidebar' : 'Open sidebar'"
            :aria-expanded="sidebarToggle"
            :title="sidebarToggle ? 'Hide sidebar' : 'Show sidebar'"
        >
            <PanelLeftClose v-if="sidebarToggle" :size="18" class="hidden lg:block" />
            <PanelLeftOpen v-else :size="18" class="hidden lg:block" />
            <Menu v-if="!sidebarToggle" :size="18" class="lg:hidden" />
            <X v-else :size="18" class="lg:hidden" />
        </button>

        <!-- Command palette trigger -->
        <button
            type="button"
            @click="$emit('openCommandPalette')"
            class="group flex items-center gap-2.5 h-9 rounded-lg border border-gray-200 bg-white pl-3 pr-1.5 text-left text-[13px] text-gray-500 shadow-theme-xs hover:border-gray-300 hover:bg-gray-50 transition-colors focus-ring dark:bg-white/[0.03] dark:border-white/[0.08] dark:text-gray-400 dark:hover:border-white/[0.16] dark:hover:bg-white/[0.06] w-full max-w-[280px] sm:max-w-[380px] lg:max-w-[440px]"
            aria-label="Open command palette"
        >
            <Search :size="15" class="text-gray-400 shrink-0" />
            <span class="flex-1 truncate">Search or jump to…</span>
            <span class="inline-flex items-center gap-0.5 rounded border border-gray-200 dark:border-white/[0.12] bg-gray-50 dark:bg-white/[0.04] px-1.5 py-0.5 text-[10.5px] font-medium text-gray-500 dark:text-gray-400 shrink-0">
                <span>⌘</span><span>K</span>
            </span>
        </button>

        <div class="flex-1"></div>

        <!-- Quick create -->
        <div class="relative hidden sm:block" ref="createRef">
            <button
                type="button"
                @click.stop="createOpen = !createOpen"
                :class="[
                    'inline-flex items-center gap-1.5 h-9 rounded-lg pl-2.5 pr-2 text-[13px] font-medium transition-colors focus-ring',
                    createOpen
                        ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
                        : 'bg-gray-900 text-white hover:bg-gray-800 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100 shadow-theme-xs',
                ]"
                aria-haspopup="menu"
                :aria-expanded="createOpen"
            >
                <Plus :size="15" />
                <span>Create</span>
                <ChevronDown :size="14" :class="['transition-transform', createOpen ? 'rotate-180' : '']" />
            </button>
            <transition
                enter-active-class="transition ease-out duration-150"
                enter-from-class="opacity-0 scale-[0.98] translate-y-0.5"
                enter-to-class="opacity-100 scale-100 translate-y-0"
                leave-active-class="transition ease-in duration-100"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-[0.98] translate-y-0.5"
            >
                <div
                    v-if="createOpen"
                    class="absolute right-0 mt-2 w-[220px] rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[color:var(--color-surface-dark)] shadow-elevation-3 origin-top-right p-1.5"
                    role="menu"
                >
                    <Link
                        v-for="item in createItems"
                        :key="item.href"
                        :href="item.href"
                        class="flex items-center gap-2.5 rounded-md px-2.5 py-2 text-[13px] text-gray-700 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-300 dark:hover:text-white/95 dark:hover:bg-white/[0.06] transition-colors"
                        role="menuitem"
                        @click="createOpen = false"
                    >
                        <component :is="item.icon" :size="15" class="text-gray-400" />
                        <span class="flex-1">{{ item.label }}</span>
                    </Link>
                </div>
            </transition>
        </div>

        <!-- Notifications -->
        <button
            type="button"
            class="relative inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 hover:text-gray-900 hover:bg-gray-100 dark:hover:text-white/95 dark:hover:bg-white/[0.06] transition-colors focus-ring"
            aria-label="Notifications"
        >
            <Bell :size="17" />
            <span
                v-if="hasUnread"
                class="absolute top-1.5 right-1.5 h-2 w-2 rounded-full bg-brand-500 ring-2 ring-[color:var(--color-canvas)] dark:ring-[color:var(--color-canvas-dark)]"
                aria-hidden="true"
            ></span>
        </button>

        <!-- Theme toggle -->
        <ThemeToggle />

        <div class="hidden sm:block h-6 w-px bg-gray-200 dark:bg-white/[0.08]" aria-hidden="true"></div>

        <!-- User menu -->
        <div class="relative" ref="userRef">
            <button
                @click.stop="userOpen = !userOpen"
                type="button"
                class="flex items-center gap-2 h-9 rounded-lg pl-1 pr-1.5 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-white/[0.06] transition-colors focus-ring"
                aria-haspopup="menu"
                :aria-expanded="userOpen"
            >
                <span
                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-brand-500 to-brand-700 text-white font-semibold text-[11.5px]"
                    aria-hidden="true"
                >
                    {{ initials }}
                </span>
                <span class="hidden xl:block text-left leading-tight">
                    <span class="block text-[13px] font-medium text-gray-900 dark:text-white/95 truncate max-w-[120px]">{{ user?.name || 'Admin' }}</span>
                </span>
                <ChevronDown :size="14" :class="['hidden xl:block text-gray-400 transition-transform', userOpen ? 'rotate-180' : '']" />
            </button>
            <transition
                enter-active-class="transition ease-out duration-150"
                enter-from-class="opacity-0 scale-[0.98] translate-y-0.5"
                enter-to-class="opacity-100 scale-100 translate-y-0"
                leave-active-class="transition ease-in duration-100"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-[0.98] translate-y-0.5"
            >
                <div
                    v-if="userOpen"
                    class="absolute right-0 mt-2 w-[260px] rounded-xl border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[color:var(--color-surface-dark)] shadow-elevation-3 origin-top-right overflow-hidden"
                    role="menu"
                >
                    <div class="px-3.5 py-3 border-b border-gray-100 dark:border-white/[0.06]">
                        <div class="flex items-center gap-2.5">
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-brand-500 to-brand-700 text-white font-semibold text-sm"
                                aria-hidden="true"
                            >{{ initials }}</span>
                            <div class="min-w-0">
                                <div class="text-[13.5px] font-semibold text-gray-900 dark:text-white/95 truncate">{{ user?.name || 'Admin' }}</div>
                                <div class="text-[11.5px] text-gray-500 dark:text-gray-400 truncate">{{ user?.email || '—' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="p-1.5">
                        <Link
                            :href="route('admin.profile')"
                            class="flex items-center gap-2.5 rounded-md px-2.5 py-2 text-[13px] text-gray-700 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-300 dark:hover:text-white/95 dark:hover:bg-white/[0.06] transition-colors"
                            role="menuitem"
                            @click="userOpen = false"
                        >
                            <UserRound :size="15" class="text-gray-400" />
                            <span>Edit profile</span>
                        </Link>
                        <Link
                            :href="route('admin.setting')"
                            class="flex items-center gap-2.5 rounded-md px-2.5 py-2 text-[13px] text-gray-700 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-300 dark:hover:text-white/95 dark:hover:bg-white/[0.06] transition-colors"
                            role="menuitem"
                            @click="userOpen = false"
                        >
                            <Settings2 :size="15" class="text-gray-400" />
                            <span>Settings</span>
                        </Link>
                        <button
                            type="button"
                            @click="openHelp"
                            class="w-full flex items-center gap-2.5 rounded-md px-2.5 py-2 text-[13px] text-gray-700 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-300 dark:hover:text-white/95 dark:hover:bg-white/[0.06] transition-colors"
                            role="menuitem"
                        >
                            <Keyboard :size="15" class="text-gray-400" />
                            <span class="flex-1 text-left">Keyboard shortcuts</span>
                            <kbd class="inline-flex items-center rounded border border-gray-200 dark:border-white/[0.12] bg-gray-50 dark:bg-white/[0.04] px-1.5 py-0.5 text-[10px] font-medium text-gray-500">?</kbd>
                        </button>
                    </div>
                    <div class="p-1.5 border-t border-gray-100 dark:border-white/[0.06]">
                        <Link
                            :href="route('admin.logout')"
                            as="button"
                            method="post"
                            class="w-full flex items-center gap-2.5 rounded-md px-2.5 py-2 text-[13px] font-medium text-error-600 hover:bg-error-50 dark:text-error-400 dark:hover:bg-error-500/10 transition-colors"
                            role="menuitem"
                        >
                            <LogOut :size="15" />
                            <span>Sign out</span>
                        </Link>
                    </div>
                </div>
            </transition>
        </div>
    </header>
</template>

<script>
import { Link } from '@inertiajs/vue3';
import ThemeToggle from './ThemeToggle.vue';
import { useShortcuts } from '@/Composables/useShortcuts';
import {
    Menu, X, Search, Plus, ChevronDown, Bell,
    UserRound, Settings2, Keyboard, LogOut,
    PanelLeftClose, PanelLeftOpen,
    Package, ShoppingCart, TicketPercent, FolderTree, Image as ImageIcon,
} from '@lucide/vue';

export default {
    components: {
        ThemeToggle, Link,
        Menu, X, Search, Plus, ChevronDown, Bell,
        UserRound, Settings2, Keyboard, LogOut,
        PanelLeftClose, PanelLeftOpen,
    },
    props: {
        sidebarToggle: Boolean,
        user: Object,
        hasUnread: { type: Boolean, default: false },
    },
    emits: ['sidebarToggle', 'openCommandPalette', 'openHelp'],
    data() {
        return {
            createOpen: false,
            userOpen: false,
            createItems: [
                { label: 'Product',  href: route('admin.products.create'),  icon: Package },
                { label: 'Order',    href: route('admin.orders'),           icon: ShoppingCart },
                { label: 'Coupon',   href: route('admin.coupons.create'),   icon: TicketPercent },
                { label: 'Category', href: route('admin.category.create'),  icon: FolderTree },
                { label: 'Slider',   href: route('admin.slider.create'),    icon: ImageIcon },
            ],
        };
    },
    computed: {
        initials() {
            const name = (this.user?.name || '').trim();
            if (!name) return 'A';
            const parts = name.split(/\s+/).filter(Boolean);
            const first = parts[0]?.[0] || '';
            const last = parts.length > 1 ? parts[parts.length - 1][0] : '';
            return (first + last).toUpperCase() || first.toUpperCase() || 'A';
        },
    },
    methods: {
        openHelp() {
            this.userOpen = false;
            this.$emit('openHelp');
        },
        onDocClick(e) {
            if (this.$refs.userRef && !this.$refs.userRef.contains(e.target)) this.userOpen = false;
            if (this.$refs.createRef && !this.$refs.createRef.contains(e.target)) this.createOpen = false;
        },
        onEsc(e) {
            if (e.key === 'Escape') { this.userOpen = false; this.createOpen = false; }
        },
    },
    mounted() {
        document.addEventListener('mousedown', this.onDocClick);
        document.addEventListener('keydown', this.onEsc);
    },
    beforeUnmount() {
        document.removeEventListener('mousedown', this.onDocClick);
        document.removeEventListener('keydown', this.onEsc);
    },
};
</script>
