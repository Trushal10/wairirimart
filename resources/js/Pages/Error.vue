<template>
    <Head :title="meta.title" />

    <div class="relative min-h-screen bg-[color:var(--color-canvas)] dark:bg-[color:var(--color-canvas-dark)]">
        <!-- Ambient gradient wash + faint grid -->
        <div
            class="pointer-events-none absolute inset-0 opacity-40"
            :style="{
                backgroundImage: 'radial-gradient(circle at 20% 15%, rgba(70,95,255,0.18), transparent 45%), radial-gradient(circle at 85% 85%, rgba(122,90,248,0.14), transparent 40%)',
            }"
            aria-hidden="true"
        ></div>
        <div
            class="pointer-events-none absolute inset-0"
            :style="{
                backgroundImage:
                    'linear-gradient(to right, rgba(0,0,0,0.03) 1px, transparent 1px), linear-gradient(to bottom, rgba(0,0,0,0.03) 1px, transparent 1px)',
                backgroundSize: '48px 48px',
                maskImage: 'radial-gradient(ellipse at center, black 35%, transparent 75%)',
            }"
            aria-hidden="true"
        ></div>

        <!-- Top-left back link -->
        <div class="relative z-10 px-6 py-6 sm:px-10">
            <Link
                :href="backHref"
                class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 -ml-2 text-[12.5px] font-medium text-gray-500 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-white/95 dark:hover:bg-white/[0.06] transition-colors focus-ring"
            >
                <ArrowLeft :size="14" />
                {{ backLabel }}
            </Link>
        </div>

        <!-- Center card -->
        <main class="relative z-10 flex min-h-[calc(100vh-120px)] items-center justify-center px-6 py-10">
            <div class="w-full max-w-lg text-center">
                <!-- Status glyph -->
                <div class="relative mx-auto mb-8 w-fit">
                    <div
                        class="absolute inset-0 -m-6 rounded-full blur-3xl opacity-40"
                        :class="glyphGlow"
                        aria-hidden="true"
                    ></div>
                    <div class="relative flex items-center justify-center">
                        <span
                            class="text-[110px] sm:text-[140px] leading-none font-extrabold tracking-tighter num-tabular bg-clip-text text-transparent"
                            :class="glyphText"
                        >
                            {{ meta.code }}
                        </span>
                    </div>
                    <div class="relative -mt-4 flex justify-center">
                        <span
                            class="inline-flex h-9 w-9 items-center justify-center rounded-xl shadow-elevation-2"
                            :class="glyphChip"
                        >
                            <component :is="meta.icon" :size="18" />
                        </span>
                    </div>
                </div>

                <h1 class="text-h1 text-gray-900 dark:text-white/95">
                    {{ meta.title }}
                </h1>
                <p class="mt-2 text-body text-gray-500 dark:text-gray-400 max-w-md mx-auto">
                    {{ message || meta.description }}
                </p>

                <div class="mt-8 flex flex-wrap items-center justify-center gap-2">
                    <Button variant="secondary" size="md" @click="goBack">
                        <template #leading><ArrowLeft :size="15" /></template>
                        Go back
                    </Button>
                    <Button variant="primary" size="md" tag="a" :href="homeHref">
                        <template #leading><Home :size="15" /></template>
                        {{ homeLabel }}
                    </Button>
                    <Button v-if="status === 500 || status === 503" variant="ghost" size="md" @click="reload">
                        <template #leading><RefreshCw :size="15" /></template>
                        Reload
                    </Button>
                </div>

                <!-- Suggested links -->
                <div v-if="showSuggestions" class="mt-10 pt-8 border-t border-gray-200/60 dark:border-white/[0.06]">
                    <p class="text-eyebrow text-gray-500 dark:text-gray-400 mb-3">Popular pages</p>
                    <div class="flex flex-wrap items-center justify-center gap-2">
                        <Link
                            v-for="link in suggestions"
                            :key="link.href"
                            :href="link.href"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 dark:border-white/[0.08] bg-white dark:bg-[color:var(--color-surface-dark)] px-3 py-1.5 text-[12.5px] font-medium text-gray-700 dark:text-gray-300 hover:border-gray-300 dark:hover:border-white/[0.14] hover:text-gray-900 dark:hover:text-white/95 transition-colors focus-ring"
                        >
                            <component :is="link.icon" :size="13" class="text-gray-400" />
                            {{ link.label }}
                        </Link>
                    </div>
                </div>
            </div>
        </main>

        <div class="relative z-10 pb-6 text-center text-[11.5px] text-gray-400 dark:text-gray-500">
            <span class="num-tabular">Error {{ status }}</span>
            <span v-if="requestId" class="ml-2">· ref {{ requestId }}</span>
        </div>
    </div>
</template>

<script>
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/Components/ui';
import {
    ArrowLeft, Home, RefreshCw,
    SearchX, ShieldAlert, ServerCrash, Wrench, CircleAlert,
    LayoutDashboard, Package, ShoppingCart, TicketPercent,
} from '@lucide/vue';

const META = {
    404: {
        code: '404',
        title: 'Page not found',
        description: "The page you're looking for doesn't exist or has been moved.",
        icon: SearchX,
    },
    403: {
        code: '403',
        title: 'Access denied',
        description: "You don't have permission to view this page. If you think this is a mistake, contact your administrator.",
        icon: ShieldAlert,
    },
    419: {
        code: '419',
        title: 'Session expired',
        description: 'Your session has timed out for security. Refresh the page and try again.',
        icon: RefreshCw,
    },
    429: {
        code: '429',
        title: 'Too many requests',
        description: "You're moving a bit fast. Wait a moment and try again.",
        icon: CircleAlert,
    },
    500: {
        code: '500',
        title: 'Something went wrong',
        description: "An unexpected error occurred on our end. We've been notified. Please try again in a moment.",
        icon: ServerCrash,
    },
    503: {
        code: '503',
        title: 'Service unavailable',
        description: "We're briefly under maintenance. Back in a few minutes.",
        icon: Wrench,
    },
};

const TONES = {
    404: {
        text: 'bg-gradient-to-br from-brand-500 to-brand-700',
        chip: 'bg-brand-500 text-white',
        glow: 'bg-brand-500',
    },
    403: {
        text: 'bg-gradient-to-br from-warning-500 to-warning-700',
        chip: 'bg-warning-500 text-white',
        glow: 'bg-warning-500',
    },
    419: {
        text: 'bg-gradient-to-br from-warning-500 to-warning-700',
        chip: 'bg-warning-500 text-white',
        glow: 'bg-warning-500',
    },
    429: {
        text: 'bg-gradient-to-br from-warning-500 to-warning-700',
        chip: 'bg-warning-500 text-white',
        glow: 'bg-warning-500',
    },
    500: {
        text: 'bg-gradient-to-br from-error-500 to-error-700',
        chip: 'bg-error-500 text-white',
        glow: 'bg-error-500',
    },
    503: {
        text: 'bg-gradient-to-br from-gray-700 to-gray-900 dark:from-gray-100 dark:to-white',
        chip: 'bg-gray-900 text-white dark:bg-white dark:text-gray-900',
        glow: 'bg-gray-500',
    },
};

export default {
    components: { Head, Link, Button, ArrowLeft, Home, RefreshCw },
    props: {
        status: { type: [Number, String], default: 500 },
        message: { type: String, default: '' },
        requestId: { type: [String, Number], default: '' },
        inAdmin: { type: Boolean, default: true },
    },
    computed: {
        meta() {
            return META[String(this.status)] || {
                code: String(this.status),
                title: 'Something went wrong',
                description: 'An unexpected error occurred. Please try again.',
                icon: CircleAlert,
            };
        },
        tone() {
            return TONES[String(this.status)] || TONES[500];
        },
        glyphText()  { return this.tone.text; },
        glyphChip()  { return this.tone.chip; },
        glyphGlow()  { return this.tone.glow; },
        homeHref() {
            try { return this.inAdmin ? route('admin.dashboard') : '/'; } catch (_) { return '/'; }
        },
        homeLabel() {
            return this.inAdmin ? 'Back to dashboard' : 'Back home';
        },
        backHref() { return this.homeHref; },
        backLabel() { return 'Home'; },
        showSuggestions() {
            return this.inAdmin && String(this.status) === '404';
        },
        suggestions() {
            try {
                return [
                    { href: route('admin.dashboard'),   label: 'Dashboard', icon: LayoutDashboard },
                    { href: route('admin.products'),    label: 'Products',  icon: Package },
                    { href: route('admin.orders'),      label: 'Orders',    icon: ShoppingCart },
                    { href: route('admin.coupons.index'), label: 'Coupons', icon: TicketPercent },
                ];
            } catch (_) { return []; }
        },
    },
    methods: {
        goBack() {
            if (typeof window !== 'undefined' && window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = this.homeHref;
            }
        },
        reload() {
            if (typeof window !== 'undefined') window.location.reload();
        },
    },
};
</script>
