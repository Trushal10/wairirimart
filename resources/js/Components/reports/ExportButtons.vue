<template>
    <div class="inline-flex items-center gap-1.5">
        <Button variant="secondary" size="sm" tag="a" :href="urlFor('csv')" title="Download CSV">
            <template #leading><FileText :size="13" /></template>
            CSV
        </Button>
        <Button variant="secondary" size="sm" tag="a" :href="urlFor('xlsx')" title="Download Excel">
            <template #leading><Sheet :size="13" /></template>
            Excel
        </Button>
        <Button
            variant="secondary"
            size="sm"
            tag="a"
            :href="urlFor('pdf')"
            title="Download PDF (open in any PDF reader to print)"
        >
            <template #leading><FileDown :size="13" /></template>
            PDF
        </Button>
    </div>
</template>

<script setup>
import { Button } from '@/Components/ui';
import { FileText, Sheet, FileDown } from '@lucide/vue';

const props = defineProps({
    routeName: { type: String, required: true },
    filters: { type: Object, default: () => ({}) },
});

function urlFor(format, extra = {}) {
    const base = route(props.routeName);
    const params = new URLSearchParams();
    params.set('format', format);
    for (const [k, v] of Object.entries(props.filters || {})) {
        if (v === null || v === undefined || v === '') continue;
        params.set(k, String(v));
    }
    for (const [k, v] of Object.entries(extra || {})) {
        if (v === null || v === undefined || v === '') continue;
        params.set(k, String(v));
    }
    return `${base}?${params.toString()}`;
}
</script>
