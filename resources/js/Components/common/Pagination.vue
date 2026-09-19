<template>
    <nav
        v-if="items.length > 1"
        class="w-full flex items-center justify-between gap-3 flex-wrap"
        aria-label="Pagination"
    >
        <p v-if="showSummary && summary" class="text-[12px] text-gray-500 dark:text-gray-400 num-tabular">
            {{ summary }}
        </p>
        <span v-else class="flex-1"></span>

        <ul class="inline-flex items-center gap-1 ml-auto">
            <li v-for="(item, i) in items" :key="i">
                <component
                    :is="item.url ? Link : 'span'"
                    v-bind="item.url ? { href: item.url, preserveScroll: true } : {}"
                    :aria-current="item.active ? 'page' : undefined"
                    :aria-disabled="!item.url ? 'true' : undefined"
                    :aria-label="item.aria"
                    :class="[
                        'inline-flex items-center justify-center h-8 min-w-8 px-2.5 rounded-md text-[12.5px] font-medium num-tabular transition-colors focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/25',
                        item.type === 'ellipsis'
                            ? 'text-gray-400 dark:text-gray-500 cursor-default select-none'
                            : item.active
                                ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900 shadow-theme-xs'
                                : item.url
                                    ? 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-white/[0.06] dark:hover:text-white/95'
                                    : 'text-gray-300 dark:text-gray-600 cursor-not-allowed opacity-60',
                        item.type === 'prev' || item.type === 'next' ? 'gap-1.5 px-2.5' : '',
                    ]"
                >
                    <template v-if="item.type === 'prev'">
                        <ChevronLeft :size="16" />
                        <span class="hidden sm:inline">Previous</span>
                    </template>
                    <template v-else-if="item.type === 'next'">
                        <span class="hidden sm:inline">Next</span>
                        <ChevronRight :size="16" />
                    </template>
                    <template v-else>
                        {{ item.label }}
                    </template>
                </component>
            </li>
        </ul>
    </nav>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';

const props = defineProps({
    links: { type: Array, required: true },
    meta: { type: Object, default: null },
    showSummary: { type: Boolean, default: true },
});

function strip(str) {
    if (!str) return '';
    return String(str).replace(/&laquo;|&raquo;|«|»/g, '').replace(/\s+/g, ' ').trim();
}

function classify(raw) {
    const s = strip(raw).toLowerCase();
    if (s === '...' || s === '…') return 'ellipsis';
    if (s.includes('previous') || s === 'prev') return 'prev';
    if (s.includes('next')) return 'next';
    return 'page';
}

const items = computed(() =>
    (props.links || []).map(l => {
        const type = classify(l.label);
        return {
            url: l.url,
            active: !!l.active,
            type,
            label: type === 'ellipsis' ? '…' : strip(l.label),
            aria:
                type === 'prev'
                    ? 'Previous page'
                    : type === 'next'
                        ? 'Next page'
                        : type === 'ellipsis'
                            ? undefined
                            : `Page ${strip(l.label)}`,
        };
    })
);

const page = usePage();
const summary = computed(() => {
    const m = props.meta || page.props?.pagination || null;
    const from = m?.from;
    const to = m?.to;
    const total = m?.total;
    if (from == null || to == null || total == null) return '';
    return `Showing ${from.toLocaleString()}–${to.toLocaleString()} of ${total.toLocaleString()}`;
});
</script>
