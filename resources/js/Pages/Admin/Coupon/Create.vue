<template>
    <Head :title="isEdit ? 'Edit coupon' : 'New coupon'" />

    <PageHeader
        :title="isEdit ? coupon.code : 'New coupon'"
        :subtitle="isEdit ? 'Update discount rules, limits, and validity.' : 'Create a discount code shoppers can apply at checkout.'"
        :crumbs="crumbs"
    >
        <template #titleBadge>
            <Badge :variant="form.is_active ? 'success' : 'neutral'" size="sm" dot>
                {{ form.is_active ? 'Active' : 'Inactive' }}
            </Badge>
        </template>
        <template #actions>
            <Button variant="secondary" size="sm" tag="a" :href="route('admin.coupons.index')">Cancel</Button>
            <Button variant="primary" size="sm" :loading="submitting" @click="submit">
                {{ isEdit ? 'Save changes' : 'Create coupon' }}
            </Button>
        </template>
    </PageHeader>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <!-- Main -->
        <div class="xl:col-span-2 space-y-6">
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Basics -->
                <Card mode="flat" title="Discount details" subtitle="Code shoppers enter at checkout and how much off it grants.">
                    <div class="space-y-5">
                        <FormField label="Coupon code" required :error="errors.code" hint="Uppercase, no spaces.">
                            <Input
                                v-model="form.code"
                                placeholder="e.g. SAVE20"
                                maxlength="50"
                                required
                                :error="!!errors.code"
                                class="uppercase font-mono tracking-widest"
                            >
                                <template #leading><TicketPercent :size="14" /></template>
                            </Input>
                        </FormField>

                        <div class="grid grid-cols-2 gap-4">
                            <FormField label="Discount type" required :error="errors.type">
                                <Select
                                    v-model="form.type"
                                    :options="[
                                        { value: 'fixed', label: 'Fixed amount (₹)' },
                                        { value: 'percent', label: 'Percentage (%)' },
                                    ]"
                                    :error="!!errors.type"
                                />
                            </FormField>
                            <FormField
                                :label="form.type === 'percent' ? 'Value (%)' : 'Value (₹)'"
                                required
                                :error="errors.value"
                            >
                                <Input
                                    v-model="form.value"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    placeholder="0.00"
                                    required
                                    :error="!!errors.value"
                                >
                                    <template #leading>
                                        <span class="text-[13px]">{{ form.type === 'percent' ? '%' : '₹' }}</span>
                                    </template>
                                </Input>
                            </FormField>
                        </div>
                    </div>
                </Card>

                <!-- Constraints -->
                <Card mode="flat" title="Constraints" subtitle="Thresholds and caps that gate this discount.">
                    <div class="space-y-5">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <FormField label="Min order amount" hint="Leave 0 for no minimum.">
                                <Input
                                    v-model="form.min_order_amount"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    placeholder="0.00"
                                >
                                    <template #leading><span class="text-[13px]">₹</span></template>
                                </Input>
                            </FormField>
                            <FormField
                                v-if="form.type === 'percent'"
                                label="Max discount cap"
                                hint="Optional cap on the discount amount."
                            >
                                <Input
                                    v-model="form.max_discount_amount"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    placeholder="e.g. 200"
                                >
                                    <template #leading><span class="text-[13px]">₹</span></template>
                                </Input>
                            </FormField>
                        </div>

                        <FormField label="Usage limit" hint="Total redemptions across all customers. Leave blank for unlimited.">
                            <Input v-model="form.usage_limit" type="number" min="1" placeholder="Unlimited" />
                        </FormField>
                    </div>
                </Card>

                <!-- Validity -->
                <Card mode="flat" title="Validity window" subtitle="When shoppers can redeem this code.">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <FormField label="Starts at" hint="Leave blank to start immediately.">
                            <Input v-model="form.starts_at" type="datetime-local" />
                        </FormField>
                        <FormField label="Expires at" hint="Leave blank for no expiry.">
                            <Input v-model="form.expires_at" type="datetime-local" />
                        </FormField>
                    </div>
                </Card>

                <div class="flex items-center gap-2 justify-end">
                    <Button variant="secondary" size="sm" tag="a" :href="route('admin.coupons.index')">Cancel</Button>
                    <Button variant="primary" size="sm" type="submit" :loading="submitting">
                        {{ isEdit ? 'Save changes' : 'Create coupon' }}
                    </Button>
                </div>
            </form>
        </div>

        <!-- Sidebar -->
        <aside class="space-y-6 xl:col-span-1">
            <Card mode="flat" title="Status">
                <Switch v-model="form.is_active">
                    <span class="font-medium text-gray-900 dark:text-white/95">
                        {{ form.is_active ? 'Active' : 'Inactive' }}
                    </span>
                    <span class="block text-[12px] text-gray-500 dark:text-gray-400">
                        Only active coupons can be redeemed at checkout.
                    </span>
                </Switch>
            </Card>

            <div>
                <div class="mb-2 text-eyebrow text-gray-500 dark:text-gray-400">Preview</div>
                <div class="relative overflow-hidden rounded-2xl bg-gray-900 dark:bg-white text-white dark:text-gray-900 p-5 shadow-elevation-2">
                    <!-- Ticket notches -->
                    <span class="absolute -left-2 top-1/2 -translate-y-1/2 h-4 w-4 rounded-full bg-[color:var(--color-canvas)] dark:bg-[color:var(--color-canvas-dark)]" aria-hidden="true"></span>
                    <span class="absolute -right-2 top-1/2 -translate-y-1/2 h-4 w-4 rounded-full bg-[color:var(--color-canvas)] dark:bg-[color:var(--color-canvas-dark)]" aria-hidden="true"></span>

                    <div class="flex items-center justify-between mb-3">
                        <div class="text-eyebrow opacity-70">Discount code</div>
                        <TicketPercent :size="16" class="opacity-70" />
                    </div>
                    <div class="text-h1 font-mono tracking-widest num-tabular">
                        {{ form.code || 'YOUR CODE' }}
                    </div>
                    <div class="mt-4 flex items-baseline gap-2">
                        <span class="text-display num-tabular">
                            {{ form.type === 'percent' ? (Number(form.value || 0)) + '%' : '₹' + Number(form.value || 0).toFixed(2) }}
                        </span>
                        <span class="text-body opacity-70">off</span>
                    </div>
                    <div class="mt-4 pt-4 border-t border-white/20 dark:border-gray-900/15 space-y-1.5 text-[12px] opacity-90">
                        <div v-if="form.min_order_amount > 0" class="flex items-center gap-2">
                            <Check :size="12" class="shrink-0 opacity-70" />
                            <span class="num-tabular">Min order ₹{{ Number(form.min_order_amount).toFixed(2) }}</span>
                        </div>
                        <div v-if="form.type === 'percent' && form.max_discount_amount" class="flex items-center gap-2">
                            <Check :size="12" class="shrink-0 opacity-70" />
                            <span class="num-tabular">Up to ₹{{ Number(form.max_discount_amount).toFixed(2) }} off</span>
                        </div>
                        <div v-if="form.expires_at" class="flex items-center gap-2">
                            <Calendar :size="12" class="shrink-0 opacity-70" />
                            <span>Valid until {{ formatDate(form.expires_at) }}</span>
                        </div>
                        <div v-if="form.usage_limit" class="flex items-center gap-2">
                            <Users :size="12" class="shrink-0 opacity-70" />
                            <span class="num-tabular">{{ form.usage_limit }} uses total</span>
                        </div>
                    </div>
                </div>
                <p class="mt-2 text-caption">
                    Live preview — shows how shoppers see this coupon.
                </p>
            </div>
        </aside>
    </div>
</template>

<script>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import MainLayout from '../../../Layout/MainLayout.vue';
import { PageHeader, Card, Button, Input, Select, Switch, FormField, Badge } from '@/Components/ui';
import { TicketPercent, Check, Calendar, Users } from '@lucide/vue';

export default {
    layout: MainLayout,
    components: {
        Head, Link,
        PageHeader, Card, Button, Input, Select, Switch, FormField, Badge,
        TicketPercent, Check, Calendar, Users,
    },
    props: {
        coupon: Object,
    },
    data() {
        return {
            submitting: false,
            form: {
                code:                this.coupon?.code || '',
                type:                this.coupon?.type || 'fixed',
                value:               this.coupon?.value || '',
                min_order_amount:    this.coupon?.min_order_amount || '',
                max_discount_amount: this.coupon?.max_discount_amount || '',
                usage_limit:         this.coupon?.usage_limit || '',
                starts_at:           this.coupon?.starts_at ? this.coupon.starts_at.substring(0, 16) : '',
                expires_at:          this.coupon?.expires_at ? this.coupon.expires_at.substring(0, 16) : '',
                is_active:           this.coupon ? Boolean(this.coupon.is_active) : true,
            },
        };
    },
    computed: {
        isEdit() { return !!this.coupon?.id; },
        crumbs() {
            return [
                { label: 'Dashboard', href: route('admin.dashboard') },
                { label: 'Coupons', href: route('admin.coupons.index') },
                { label: this.isEdit ? 'Edit' : 'New' },
            ];
        },
        errors() {
            return usePage().props.errors || {};
        },
    },
    methods: {
        formatDate(d) {
            return d
                ? new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
                : '';
        },
        submit() {
            this.submitting = true;
            const method = this.isEdit ? 'put' : 'post';
            const url = this.isEdit
                ? route('admin.coupons.update', this.coupon.id)
                : route('admin.coupons.store');

            const emptyToNull = (v) => (v === '' || v === undefined ? null : v);

            const payload = {
                code: this.form.code,
                type: this.form.type,
                value: emptyToNull(this.form.value),
                min_order_amount: emptyToNull(this.form.min_order_amount) ?? 0,
                max_discount_amount: emptyToNull(this.form.max_discount_amount),
                usage_limit: emptyToNull(this.form.usage_limit),
                starts_at: emptyToNull(this.form.starts_at),
                expires_at: emptyToNull(this.form.expires_at),
                is_active: this.form.is_active ? 1 : 0,
            };

            router[method](url, payload, {
                onFinish: () => { this.submitting = false; },
            });
        },
    },
};
</script>
