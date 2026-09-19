<template>
    <Head :title="isEdit ? 'Edit expense' : 'New expense'" />

    <PageHeader
        :title="isEdit ? 'Edit expense' : 'New expense'"
        subtitle="Recorded expenses reduce Operating Profit on the P&L report."
        :crumbs="crumbs"
    >
        <template #actions>
            <Button variant="secondary" size="sm" tag="a" :href="route('admin.expenses.index')">Cancel</Button>
            <Button variant="primary" size="sm" :loading="submitting" @click="submit">
                {{ isEdit ? 'Save changes' : 'Add expense' }}
            </Button>
        </template>
    </PageHeader>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2">
            <Card mode="flat" title="Expense details">
                <form @submit.prevent="submit" class="space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <FormField label="Date" required :error="errors.date">
                            <Input v-model="form.date" type="date" required :error="!!errors.date" />
                        </FormField>
                        <FormField label="Category" required :error="errors.category">
                            <SelectDropdown
                                v-model="form.category"
                                :options="categoryOptions"
                                :nullable="false"
                                :searchable="false"
                            />
                        </FormField>
                        <FormField label="Title" required :error="errors.title" class="md:col-span-2">
                            <Input
                                v-model="form.title"
                                required
                                maxlength="200"
                                placeholder="e.g. Office rent — July"
                                :error="!!errors.title"
                            />
                        </FormField>
                        <FormField label="Amount" required :error="errors.amount">
                            <Input
                                v-model="form.amount"
                                type="number"
                                step="0.01"
                                min="0"
                                required
                                placeholder="0.00"
                                :error="!!errors.amount"
                            >
                                <template #leading><span class="text-[13px]">₹</span></template>
                            </Input>
                        </FormField>
                        <FormField label="Note" optional :error="errors.note" class="md:col-span-2">
                            <Textarea
                                v-model="form.note"
                                :rows="3"
                                maxlength="2000"
                                placeholder="Optional context…"
                                :error="!!errors.note"
                            />
                        </FormField>
                    </div>
                </form>
            </Card>
        </div>

        <aside class="space-y-6 xl:col-span-1">
            <Card mode="flat" title="About expenses">
                <ul class="space-y-3 text-[13px] text-gray-600 dark:text-gray-400">
                    <li class="flex gap-2">
                        <Check :size="14" class="mt-0.5 shrink-0 text-success-600 dark:text-success-400" />
                        <span>Recorded expenses are deducted from Operating Profit on the P&L report.</span>
                    </li>
                    <li class="flex gap-2">
                        <Check :size="14" class="mt-0.5 shrink-0 text-success-600 dark:text-success-400" />
                        <span>Categorise expenses so you can filter and roll up totals in reports.</span>
                    </li>
                    <li class="flex gap-2">
                        <Check :size="14" class="mt-0.5 shrink-0 text-success-600 dark:text-success-400" />
                        <span>Dates are grouped by <b>day</b>, <b>month</b>, or <b>year</b> when the report is filtered.</span>
                    </li>
                </ul>
            </Card>
        </aside>
    </div>
</template>

<script>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Card from '@/Components/ui/Card.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import FormField from '@/Components/ui/FormField.vue';
import SelectDropdown from '@/Components/reports/SelectDropdown.vue';
import Layout from '@/Layout/MainLayout.vue';
import { Check } from '@lucide/vue';

export default {
    layout: Layout,
    components: {
        Head, Link,
        PageHeader, Card, Button, Input, Textarea, FormField, SelectDropdown,
        Check,
    },
    props: {
        expense: { type: Object, default: null },
        categories: { type: Object, required: true },
    },
    data() {
        const e = this.expense || {};
        return {
            submitting: false,
            form: {
                date: e.date ? String(e.date).slice(0, 10) : new Date().toISOString().slice(0, 10),
                category: e.category || Object.keys(this.categories)[0],
                title: e.title || '',
                note: e.note || '',
                amount: e.amount || '',
            },
        };
    },
    computed: {
        isEdit() { return !!this.expense; },
        categoryOptions() {
            return Object.entries(this.categories).map(([value, label]) => ({ value, label }));
        },
        crumbs() {
            return [
                { label: 'Dashboard', href: route('admin.dashboard') },
                { label: 'Reports', href: route('admin.reports.sales') },
                { label: 'Operating expenses', href: route('admin.expenses.index') },
                { label: this.isEdit ? 'Edit' : 'New' },
            ];
        },
        errors() { return usePage().props.errors || {}; },
    },
    methods: {
        submit() {
            this.submitting = true;
            const done = () => { this.submitting = false; };
            if (this.isEdit) {
                router.put(route('admin.expenses.update', this.expense.id), this.form, { onFinish: done });
            } else {
                router.post(route('admin.expenses.store'), this.form, { onFinish: done });
            }
        },
    },
};
</script>
