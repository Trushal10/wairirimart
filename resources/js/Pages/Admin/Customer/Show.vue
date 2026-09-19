<template>
    <Head :title="customer.name" />

    <PageHeader
        :title="customer.name"
        :subtitle="customer.email || customer.phone || 'Customer detail'"
        :crumbs="crumbs"
    >
        <template #titleBadge>
            <Badge :variant="customer.blocked_at ? 'error' : 'success'" size="sm" dot>
                {{ customer.blocked_at ? 'Blocked' : 'Active' }}
            </Badge>
        </template>
        <template #actions>
            <Button
                v-if="customer.blocked_at"
                variant="secondary"
                size="sm"
                @click="unblock"
            >
                <template #leading><ShieldCheck :size="14" /></template>
                Unblock
            </Button>
            <Button v-else variant="danger" size="sm" @click="showBlock = true">
                <template #leading><ShieldOff :size="14" /></template>
                Block customer
            </Button>
        </template>
    </PageHeader>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <!-- Main -->
        <div class="xl:col-span-2 space-y-6">
            <!-- Stats -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <StatCard label="Total orders" :value="stats.orders_count" tone="neutral" />
                <StatCard label="Open orders" :value="stats.open_orders" tone="brand" />
                <StatCard label="Lifetime spend" :value="'₹' + formatPrice(stats.spent_total)" tone="success" />
                <StatCard label="Last order" :value="stats.last_order_at ? relTime(stats.last_order_at) : '—'" tone="neutral" />
            </div>

            <!-- Recent orders -->
            <Card mode="flat" title="Recent orders" subtitle="Latest 10 orders for this customer.">
                <div v-if="!recent_orders.length" class="py-10 text-center text-[13px] text-gray-500 dark:text-gray-400">
                    No orders yet.
                </div>
                <table v-else class="w-full text-[13px]">
                    <thead>
                        <tr class="text-left text-[11.5px] uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-white/[0.06]">
                            <th class="py-2 pr-4">Order</th>
                            <th class="py-2 pr-4">Placed</th>
                            <th class="py-2 pr-4">Items</th>
                            <th class="py-2 pr-4">Total</th>
                            <th class="py-2 pr-4">Status</th>
                            <th class="py-2 text-right"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="o in recent_orders" :key="o.id" class="border-b border-gray-100 dark:border-white/[0.04]">
                            <td class="py-2.5 pr-4 font-mono text-[12px] text-gray-900 dark:text-white/95">{{ o.order_no }}</td>
                            <td class="py-2.5 pr-4 num-tabular text-gray-600 dark:text-gray-400">{{ formatDate(o.created_at) }}</td>
                            <td class="py-2.5 pr-4 num-tabular">{{ o.order_items_count }}</td>
                            <td class="py-2.5 pr-4 num-tabular text-body-strong">₹{{ formatPrice(o.total) }}</td>
                            <td class="py-2.5 pr-4">
                                <Badge :variant="statusVariant(o.status)" size="sm" dot>{{ o.status }}</Badge>
                            </td>
                            <td class="py-2.5 text-right">
                                <Link :href="route('admin.order.detail', o.id)" class="text-[12px] font-medium text-brand-600 dark:text-brand-400 hover:underline">
                                    View
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </Card>

            <!-- Addresses -->
            <Card mode="flat" title="Addresses" :subtitle="`${customer.addresses.length} saved`">
                <div v-if="!customer.addresses.length" class="py-8 text-center text-[13px] text-gray-500 dark:text-gray-400">
                    No addresses saved.
                </div>
                <div v-else class="grid gap-3 sm:grid-cols-2">
                    <div v-for="addr in customer.addresses" :key="addr.id"
                        class="rounded-lg border border-gray-200 dark:border-white/[0.06] p-3 text-[13px]">
                        <div class="font-medium text-gray-900 dark:text-white/95">{{ addr.name || customer.name }}</div>
                        <div class="text-gray-500 dark:text-gray-400 mt-0.5">{{ addr.phone || customer.phone }}</div>
                        <div class="mt-2 text-gray-600 dark:text-gray-300 leading-relaxed">
                            {{ addr.address }}<br>
                            {{ addr.city }}, {{ addr.state }} {{ addr.pincode }}
                        </div>
                    </div>
                </div>
            </Card>
        </div>

        <!-- Sidebar -->
        <aside class="space-y-6 xl:col-span-1">
            <Card mode="flat" title="Contact">
                <dl class="text-[13px] space-y-3">
                    <div>
                        <dt class="text-eyebrow text-gray-500 dark:text-gray-400">Email</dt>
                        <dd class="mt-0.5 text-gray-900 dark:text-white/95">
                            {{ customer.email || '—' }}
                            <Badge v-if="customer.email_verified_at" variant="success" size="sm" class="ml-2">Verified</Badge>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-eyebrow text-gray-500 dark:text-gray-400">Phone</dt>
                        <dd class="mt-0.5 text-gray-900 dark:text-white/95">
                            {{ customer.phone || '—' }}
                            <Badge v-if="customer.phone_verified_at" variant="success" size="sm" class="ml-2">Verified</Badge>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-eyebrow text-gray-500 dark:text-gray-400">Joined</dt>
                        <dd class="mt-0.5 text-gray-900 dark:text-white/95">{{ formatDate(customer.created_at) }}</dd>
                    </div>
                    <div v-if="customer.provider">
                        <dt class="text-eyebrow text-gray-500 dark:text-gray-400">Signed up via</dt>
                        <dd class="mt-0.5 text-gray-900 dark:text-white/95 capitalize">{{ customer.provider }}</dd>
                    </div>
                </dl>
            </Card>

            <Card v-if="customer.blocked_at" mode="flat" title="Block info">
                <dl class="text-[13px] space-y-3">
                    <div>
                        <dt class="text-eyebrow text-gray-500 dark:text-gray-400">Blocked at</dt>
                        <dd class="mt-0.5 text-gray-900 dark:text-white/95">{{ formatDate(customer.blocked_at) }}</dd>
                    </div>
                    <div v-if="customer.block_reason">
                        <dt class="text-eyebrow text-gray-500 dark:text-gray-400">Reason</dt>
                        <dd class="mt-0.5 text-gray-900 dark:text-white/95">{{ customer.block_reason }}</dd>
                    </div>
                </dl>
            </Card>
        </aside>
    </div>

    <!-- Block modal -->
    <div v-if="showBlock" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 p-4" @click.self="showBlock = false">
        <div class="w-full max-w-md rounded-xl bg-white dark:bg-[color:var(--color-surface-dark)] p-6 shadow-elevation-3">
            <h3 class="text-body-strong text-gray-900 dark:text-white/95 mb-1">Block {{ customer.name }}?</h3>
            <p class="text-[13px] text-gray-500 dark:text-gray-400 mb-4">
                They'll be signed out immediately and cannot sign in again until you unblock.
            </p>
            <FormField label="Reason (optional)" hint="Only visible to admins.">
                <Textarea v-model="blockReason" rows="3" placeholder="e.g. Chargeback dispute" />
            </FormField>
            <div class="mt-4 flex justify-end gap-2">
                <Button variant="secondary" size="sm" @click="showBlock = false">Cancel</Button>
                <Button variant="danger" size="sm" @click="confirmBlock">Block customer</Button>
            </div>
        </div>
    </div>
</template>

<script>
import { Head, Link, router } from '@inertiajs/vue3';
import MainLayout from '../../../Layout/MainLayout.vue';
import { PageHeader, Card, Badge, Button, FormField, Textarea } from '@/Components/ui';
import { ShieldOff, ShieldCheck } from '@lucide/vue';

const StatCard = {
    props: { label: String, value: [Number, String], tone: { type: String, default: 'neutral' } },
    template: `
        <div class="rounded-xl border border-gray-200 dark:border-white/[0.06] bg-white dark:bg-[color:var(--color-surface-dark)] px-4 py-3">
            <div class="text-eyebrow text-gray-500 dark:text-gray-400">{{ label }}</div>
            <div class="mt-1 num-tabular font-semibold text-[20px]" :class="{
                'text-gray-900 dark:text-white/95': tone === 'neutral',
                'text-success-700 dark:text-success-400': tone === 'success',
                'text-error-700 dark:text-error-400': tone === 'error',
                'text-brand-600 dark:text-brand-400': tone === 'brand',
            }">{{ value }}</div>
        </div>
    `,
};

export default {
    layout: MainLayout,
    components: {
        Head, Link, StatCard,
        PageHeader, Card, Badge, Button, FormField, Textarea,
        ShieldOff, ShieldCheck,
    },
    props: {
        customer: Object,
        stats: Object,
        recent_orders: Array,
    },
    data() {
        return {
            showBlock: false,
            blockReason: '',
        };
    },
    computed: {
        crumbs() {
            return [
                { label: 'Dashboard', href: route('admin.dashboard') },
                { label: 'Customers', href: route('admin.customers.index') },
                { label: this.customer.name },
            ];
        },
    },
    methods: {
        formatDate(d) {
            return d ? new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
        },
        formatPrice(v) {
            const n = Number(v || 0);
            return n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        relTime(d) {
            if (!d) return '—';
            const secs = (Date.now() - new Date(d).getTime()) / 1000;
            if (secs < 60) return 'just now';
            if (secs < 3600) return Math.floor(secs / 60) + 'm ago';
            if (secs < 86400) return Math.floor(secs / 3600) + 'h ago';
            if (secs < 2592000) return Math.floor(secs / 86400) + 'd ago';
            return this.formatDate(d);
        },
        statusVariant(s) {
            if (s === 'delivered') return 'success';
            if (s === 'confirmed') return 'brand';
            if (s === 'canceled') return 'error';
            return 'warning';
        },
        confirmBlock() {
            router.post(route('admin.customers.block', this.customer.id), { reason: this.blockReason }, {
                preserveScroll: true,
                onSuccess: () => { this.showBlock = false; this.blockReason = ''; },
            });
        },
        unblock() {
            router.post(route('admin.customers.unblock', this.customer.id), {}, { preserveScroll: true });
        },
    },
};
</script>
