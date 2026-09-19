<template>
    <div class="relative min-h-screen bg-[color:var(--color-canvas)] dark:bg-[color:var(--color-canvas-dark)]">
        <div class="grid min-h-screen lg:grid-cols-2">
            <!-- Left: form column -->
            <div class="flex flex-col px-6 py-8 sm:px-10 lg:px-12">
                <!-- Top bar: back link -->
                <div class="flex items-center justify-between">
                    <Link
                        :href="route('admin.dashboard')"
                        class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 -ml-2 text-[12.5px] text-gray-500 hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-white/95 dark:hover:bg-white/[0.06] transition-colors focus-ring"
                    >
                        <ArrowLeft :size="14" />
                        Back to dashboard
                    </Link>
                    <span class="lg:hidden inline-flex h-9 w-9 items-center justify-center rounded-lg bg-gray-900 text-white dark:bg-white dark:text-gray-900">
                        <ShoppingBag :size="16" />
                    </span>
                </div>

                <!-- Form -->
                <div class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center py-10">
                    <div class="mb-6">
                        <h1 class="text-display text-gray-900 dark:text-white/95">{{ title }}</h1>
                        <p v-if="subtitle" class="mt-2 text-body text-gray-500 dark:text-gray-400">
                            {{ subtitle }}
                        </p>
                    </div>
                    <slot />
                </div>

                <!-- Footer -->
                <div class="text-[11.5px] text-gray-400 dark:text-gray-500">
                    &copy; {{ new Date().getFullYear() }} {{ appName }}. All rights reserved.
                </div>
            </div>

            <!-- Right: brand column -->
            <div class="relative hidden lg:flex items-center justify-center overflow-hidden bg-gray-900 dark:bg-black">
                <!-- Subtle gradient wash + grid pattern -->
                <div
                    class="absolute inset-0 opacity-30"
                    :style="{
                        backgroundImage: 'radial-gradient(circle at 20% 20%, rgba(70,95,255,0.35), transparent 45%), radial-gradient(circle at 80% 80%, rgba(122,90,248,0.25), transparent 40%)',
                    }"
                    aria-hidden="true"
                ></div>
                <div
                    class="absolute inset-0"
                    :style="{
                        backgroundImage:
                            'linear-gradient(to right, rgba(255,255,255,0.04) 1px, transparent 1px), linear-gradient(to bottom, rgba(255,255,255,0.04) 1px, transparent 1px)',
                        backgroundSize: '48px 48px',
                        maskImage: 'radial-gradient(ellipse at center, black 40%, transparent 75%)',
                    }"
                    aria-hidden="true"
                ></div>

                <div class="relative z-10 flex flex-col items-center text-center max-w-md px-8">
                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-gray-900 shadow-elevation-3 mb-6">
                        <ShoppingBag :size="26" />
                    </span>
                    <h2 class="text-h1 text-white">{{ brandHeadline }}</h2>
                    <p class="mt-3 text-body text-white/70">
                        {{ brandTagline }}
                    </p>

                    <ul class="mt-8 w-full space-y-3 text-left">
                        <li v-for="feature in features" :key="feature" class="flex items-start gap-3 text-[13.5px] text-white/85">
                            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white/[0.08] text-brand-400">
                                <Check :size="12" stroke-width="3" />
                            </span>
                            {{ feature }}
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Theme toggle (fixed) -->
        <div class="fixed bottom-4 right-4 z-50 sm:bottom-6 sm:right-6">
            <ThemeToggle />
        </div>
    </div>
</template>

<script>
import { Link } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import { ArrowLeft, ShoppingBag, Check } from '@lucide/vue';

export default {
    components: { Link, ThemeToggle, ArrowLeft, ShoppingBag, Check },
    props: {
        title: { type: String, required: true },
        subtitle: { type: String, default: '' },
        appName: { type: String, default: 'Storefront Admin' },
        brandHeadline: { type: String, default: 'Run your storefront with confidence.' },
        brandTagline: { type: String, default: 'A modern admin panel built for eCommerce teams — orders, inventory, reports, and shipments in one place.' },
        features: {
            type: Array,
            default: () => [
                'Real-time orders, returns, and refunds',
                'Product catalog with variants and inventory',
                'Built-in P&L, sales, and shipping reports',
            ],
        },
    },
};
</script>
