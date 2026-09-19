<template>
    <header class="mb-6">
        <nav
            v-if="crumbs.length"
            class="mb-3 flex items-center gap-1.5 text-[12px] leading-4 text-gray-500 dark:text-gray-400"
            aria-label="Breadcrumb"
        >
            <template v-for="(c, i) in crumbs" :key="i">
                <Link
                    v-if="c.href && i < crumbs.length - 1"
                    :href="c.href"
                    class="rounded-md px-1.5 py-0.5 -mx-1.5 hover:text-gray-900 hover:bg-gray-100 dark:hover:text-white/95 dark:hover:bg-white/[0.06] transition-colors"
                >
                    {{ c.label }}
                </Link>
                <span
                    v-else
                    :class="[
                        'px-1.5 py-0.5 -mx-1.5',
                        i === crumbs.length - 1
                            ? 'text-gray-900 dark:text-white/90 font-medium'
                            : '',
                    ]"
                >
                    {{ c.label }}
                </span>
                <ChevronRight
                    v-if="i < crumbs.length - 1"
                    :size="12"
                    class="text-gray-400 dark:text-gray-600 shrink-0"
                />
            </template>
        </nav>
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between md:gap-6">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-h1 text-gray-900 dark:text-white/95 truncate">
                        {{ title }}
                    </h1>
                    <slot name="titleBadge" />
                </div>
                <p
                    v-if="subtitle"
                    class="mt-1 text-body text-gray-500 dark:text-gray-400 max-w-2xl"
                >
                    {{ subtitle }}
                </p>
                <slot name="meta" />
            </div>
            <div
                v-if="$slots.actions"
                class="flex flex-wrap items-center gap-2 shrink-0"
            >
                <slot name="actions" />
            </div>
        </div>
        <slot name="footer" />
    </header>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    crumbs: { type: Array, default: () => [] },
});
</script>
