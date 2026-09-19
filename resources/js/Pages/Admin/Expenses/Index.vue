<template>
    <Head title="Operating expenses" />

    <PageHeader
        title="Operating expenses"
        subtitle="Period-based expenses (rent, salaries, marketing…). Deducted from the P&L report."
        :crumbs="crumbs"
    >
        <template #actions>
            <Button variant="primary" size="sm" tag="a" :href="route('admin.expenses.create')">
                <template #leading><Plus :size="14" /></template>
                Add expense
            </Button>
        </template>
    </PageHeader>

    <Card mode="flat" padding="md" class="mb-6">
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-[150px]">
                <label class="mb-1 block text-[12px] font-medium text-gray-600 dark:text-gray-400">From</label>
                <Input v-model="local.from" type="date" />
            </div>
            <div class="min-w-[150px]">
                <label class="mb-1 block text-[12px] font-medium text-gray-600 dark:text-gray-400">To</label>
                <Input v-model="local.to" type="date" />
            </div>
            <div class="min-w-[180px]">
                <label class="mb-1 block text-[12px] font-medium text-gray-600 dark:text-gray-400">Category</label>
                <SelectDropdown
                    v-model="local.category"
                    :options="categoryOptions"
                    :searchable="false"
                    null-label="All categories"
                    null-value=""
                />
            </div>
            <div class="flex-1 min-w-[220px]">
                <label class="mb-1 block text-[12px] font-medium text-gray-600 dark:text-gray-400">Search</label>
                <Input v-model="local.search" placeholder="Search title…" @keyup.enter="apply()">
                    <template #leading><Search :size="14" /></template>
                </Input>
            </div>
            <div class="flex items-center gap-2 pt-6">
                <Button variant="ghost" size="md" @click="reset()">Reset</Button>
                <Button variant="primary" size="md" @click="apply()">
                    <template #leading><Filter :size="14" /></template>
                    Apply
                </Button>
            </div>
        </div>
    </Card>

    <Card mode="flat" title="Expenses" padding="none">
        <template #actions>
            <div class="flex items-center gap-3 text-[12.5px]">
                <span class="text-gray-500 dark:text-gray-400 num-tabular">
                    <strong class="text-gray-800 dark:text-white/90">{{ rows.total || 0 }}</strong> entries
                </span>
                <span class="text-gray-600 dark:text-gray-300">
                    Page sum: <span class="num-tabular font-semibold text-gray-900 dark:text-white/95">₹{{ money(pageSum) }}</span>
                </span>
            </div>
        </template>

        <div class="overflow-x-auto custom-scrollbar">
            <table class="min-w-full text-left text-[13.5px]">
                <thead class="bg-gray-50/60 dark:bg-white/[0.02] text-eyebrow text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-white/[0.06]">
                    <tr>
                        <th class="px-4 py-3 w-32">Date</th>
                        <th class="px-4 py-3 w-36">Category</th>
                        <th class="px-4 py-3">Title</th>
                        <th class="px-4 py-3">Note</th>
                        <th class="px-4 py-3 text-right w-32">Amount</th>
                        <th class="px-4 py-3 text-right w-32"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04] text-gray-700 dark:text-gray-300">
                    <tr v-if="!rows.data.length">
                        <td colspan="6" class="text-center py-14 text-body text-gray-500 dark:text-gray-400">
                            No expenses recorded for the current filters.
                        </td>
                    </tr>
                    <tr v-for="e in rows.data" :key="e.id" class="hover:bg-gray-50 dark:hover:bg-white/[0.02] transition-colors">
                        <td class="px-4 py-3.5 whitespace-nowrap num-tabular text-[12.5px]">{{ formatDate(e.date) }}</td>
                        <td class="px-4 py-3.5">
                            <Badge variant="neutral" size="sm" shape="rounded">
                                {{ categories[e.category] || e.category }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3.5 text-body-strong text-gray-900 dark:text-white/95">{{ e.title }}</td>
                        <td class="px-4 py-3.5 max-w-md truncate text-[12px] text-gray-500 dark:text-gray-400">{{ e.note || '—' }}</td>
                        <td class="px-4 py-3.5 text-right num-tabular text-body-strong text-gray-900 dark:text-white/95">₹{{ money(e.amount) }}</td>
                        <td class="px-4 py-3.5 text-right">
                            <div class="inline-flex items-center gap-0.5">
                                <Tooltip content="Edit">
                                    <Link
                                        :href="route('admin.expenses.edit', e.id)"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/[0.08] dark:hover:text-white/95 transition-colors focus-ring"
                                    >
                                        <Pencil :size="14" />
                                    </Link>
                                </Tooltip>
                                <Tooltip content="Delete">
                                    <button
                                        type="button"
                                        @click="confirmDelete(e)"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-500 hover:bg-error-50 hover:text-error-600 dark:text-gray-400 dark:hover:bg-error-500/10 dark:hover:text-error-400 transition-colors focus-ring"
                                    >
                                        <Trash2 :size="14" />
                                    </button>
                                </Tooltip>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <template #footer>
            <span class="text-[12px] text-gray-500 dark:text-gray-400 num-tabular">
                Page {{ rows.current_page }} of {{ rows.last_page }}
            </span>
            <Pagination :links="rows.links" />
        </template>
    </Card>

    <DeleteAlert
        v-if="pendingDelete"
        @confirmDelete="doDelete"
        @cancelDelete="pendingDelete = null"
        title="Delete expense"
        :message="`Delete “${pendingDelete.title}” (₹${money(pendingDelete.amount)})? This cannot be undone.`"
    />
</template>

<script>
import { Head, Link, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Badge from '@/Components/ui/Badge.vue';
import Tooltip from '@/Components/ui/Tooltip.vue';
import SelectDropdown from '@/Components/reports/SelectDropdown.vue';
import Pagination from '@/Components/common/Pagination.vue';
import DeleteAlert from '@/Components/common/DeleteAlert.vue';
import Layout from '@/Layout/MainLayout.vue';
import { Plus, Pencil, Trash2, Search, Filter } from '@lucide/vue';

export default {
    layout: Layout,
    components: {
        Head, Link,
        PageHeader, Card, Button, Input, Badge, Tooltip, SelectDropdown, Pagination, DeleteAlert,
        Plus, Pencil, Trash2, Search, Filter,
    },
    props: {
        rows: { type: Object, required: true },
        filters: { type: Object, required: true },
        categories: { type: Object, required: true },
        pageSum: { type: Number, required: true },
    },
    data() {
        return {
            local: { ...this.filters },
            pendingDelete: null,
            crumbs: [
                { label: 'Dashboard', href: route('admin.dashboard') },
                { label: 'Reports', href: route('admin.reports.sales') },
                { label: 'Operating expenses' },
            ],
        };
    },
    computed: {
        categoryOptions() {
            return Object.entries(this.categories).map(([value, label]) => ({ value, label }));
        },
    },
    methods: {
        money(v) { return Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        formatDate(v) {
            if (!v) return '—';
            const d = new Date(v);
            return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        },
        apply() {
            const payload = {};
            for (const [k, v] of Object.entries(this.local)) if (v) payload[k] = v;
            router.get(route('admin.expenses.index'), payload, { preserveState: true, replace: true });
        },
        reset() {
            this.local = { from: '', to: '', category: '', search: '' };
            router.get(route('admin.expenses.index'), {}, { preserveState: true, replace: true });
        },
        confirmDelete(e) { this.pendingDelete = e; },
        doDelete() {
            if (!this.pendingDelete) return;
            router.delete(route('admin.expenses.destroy', this.pendingDelete.id), { preserveScroll: true });
            this.pendingDelete = null;
        },
    },
};
</script>
