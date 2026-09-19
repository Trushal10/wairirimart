<template>
    <Head :title="`Return ${theReturn.return_no}`" />

    <PageHeader
        :title="theReturn.return_no"
        :subtitle="`Requested ${formatDate(theReturn.requested_at, true)}`"
        :crumbs="crumbs"
    >
        <template #titleBadge>
            <Badge :variant="statusVariant(theReturn.status)" size="sm" dot>
                {{ prettyStatus(theReturn.status) }}
            </Badge>
        </template>
        <template #actions>
            <Button variant="secondary" size="sm" tag="a" :href="route('admin.order.detail', theReturn.order.id)">
                <template #leading><ExternalLink :size="14" /></template>
                Order #{{ theReturn.order.order_no }}
            </Button>
        </template>
    </PageHeader>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <!-- Main -->
        <div class="space-y-6 xl:col-span-2">
            <!-- Items -->
            <Card mode="flat" title="Items to be returned" padding="none">
                <div class="divide-y divide-gray-100 dark:divide-white/[0.04]">
                    <div
                        v-for="ri in theReturn.items"
                        :key="ri.id"
                        class="flex items-center gap-3 px-5 py-3.5"
                    >
                        <img
                            :src="itemImage(ri.order_item)"
                            alt=""
                            class="h-12 w-12 rounded-lg object-cover border border-gray-200 dark:border-white/[0.06] bg-gray-100 dark:bg-white/[0.04] shrink-0"
                            @error="ev => (ev.target.src = '/client/images/placeholder.jpg')"
                        />
                        <div class="flex-1 min-w-0">
                            <div class="text-body-strong text-gray-900 dark:text-white/95 truncate">
                                {{ ri.order_item?.product?.name || 'Product' }}
                            </div>
                            <div class="mt-0.5 text-[12px] text-gray-500 dark:text-gray-400 num-tabular">
                                Qty {{ ri.quantity }} · ₹{{ money(ri.unit_price) }} each
                            </div>
                        </div>
                        <div class="text-body-strong num-tabular text-gray-900 dark:text-white/95 shrink-0">
                            ₹{{ money(ri.quantity * ri.unit_price) }}
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-between gap-3 px-5 py-3.5 border-t border-gray-100 dark:border-white/[0.06] bg-gray-50/40 dark:bg-white/[0.02]">
                    <span class="text-h3 text-gray-900 dark:text-white/95">Refund amount</span>
                    <span class="text-h2 num-tabular text-gray-900 dark:text-white/95">₹{{ money(theReturn.refund_amount) }}</span>
                </div>
            </Card>

            <!-- Reason -->
            <Card mode="flat" title="Customer's reason">
                <div class="space-y-3">
                    <div class="inline-flex items-center gap-2">
                        <Badge variant="neutral" size="md">{{ reasons[theReturn.reason] || theReturn.reason }}</Badge>
                    </div>
                    <p v-if="theReturn.comment" class="text-[13.5px] text-gray-700 dark:text-gray-300 whitespace-pre-line leading-6">
                        {{ theReturn.comment }}
                    </p>

                    <div v-if="theReturn.photo" class="pt-1">
                        <a :href="`/storage/returns/${theReturn.photo}`" target="_blank" rel="noopener" class="inline-block group">
                            <img
                                :src="`/storage/returns/${theReturn.photo}`"
                                alt="Customer photo"
                                class="max-h-64 rounded-lg border border-gray-200 dark:border-white/[0.06] transition-transform group-hover:scale-[1.01]"
                            />
                        </a>
                    </div>
                </div>

                <Alert v-if="theReturn.rejection_reason" variant="error" title="Rejection reason" class="mt-4">
                    {{ theReturn.rejection_reason }}
                </Alert>
            </Card>

            <!-- Actions -->
            <Card v-if="theReturn.status === 'requested'" mode="flat" title="Next step · review request">
                <p class="text-[13px] text-gray-600 dark:text-gray-400 mb-4">
                    Review the reason and photo. Approving asks the customer to ship the item back.
                </p>
                <div class="flex flex-wrap gap-2">
                    <Button variant="success" size="sm" :loading="loading.approve" @click="doApprove">
                        <template #leading><Check :size="14" /></template>
                        Approve return
                    </Button>
                    <Button variant="danger-outline" size="sm" @click="openReject">
                        <template #leading><X :size="14" /></template>
                        Reject
                    </Button>
                </div>
            </Card>

            <Card v-else-if="theReturn.status === 'approved'" mode="flat" title="Next step · receive package">
                <p class="text-[13px] text-gray-600 dark:text-gray-400 mb-4">
                    Waiting for the customer to ship the package back. When received and inspected, mark as received.
                </p>
                <Checkbox v-model="restockOnReceipt" class="mb-4">
                    Restock returned units back to inventory
                </Checkbox>
                <Button variant="primary" size="sm" :loading="loading.receive" @click="doReceive">
                    <template #leading><PackageCheck :size="14" /></template>
                    Mark package as received
                </Button>
            </Card>

            <Card v-else-if="theReturn.status === 'received'" mode="flat" title="Next step · issue refund">
                <p class="text-[13px] text-gray-600 dark:text-gray-400 mb-4">
                    Package received. Open the order to trigger a Razorpay refund, then return here to mark refunded.
                </p>
                <div class="flex flex-wrap gap-2">
                    <Button variant="primary" size="sm" tag="a" :href="route('admin.order.detail', theReturn.order.id)">
                        Go to order → Refund ₹{{ money(theReturn.refund_amount) }}
                    </Button>
                    <Button variant="secondary" size="sm" :loading="loading.refunded" @click="doRefunded">
                        Mark refunded (already done)
                    </Button>
                </div>
            </Card>

            <Card v-else-if="['rejected', 'refunded', 'cancelled'].includes(theReturn.status)" mode="flat" padding="none">
                <EmptyState
                    size="sm"
                    title="Return closed"
                    description="This return is closed. No further action needed."
                />
            </Card>
        </div>

        <!-- Sidebar -->
        <aside class="space-y-6 xl:col-span-1">
            <Card mode="flat" title="Customer">
                <div class="flex items-start gap-3">
                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-white font-semibold text-[12.5px]"
                        :style="{ background: avatarColor(theReturn.customer?.name || theReturn.order?.shipping_name) }"
                        aria-hidden="true"
                    >{{ initials(theReturn.customer?.name || theReturn.order?.shipping_name) }}</span>
                    <div class="min-w-0 flex-1">
                        <div class="text-body-strong text-gray-900 dark:text-white/95 truncate">
                            {{ theReturn.customer?.name || theReturn.order?.shipping_name }}
                        </div>
                        <div v-if="theReturn.customer?.email || theReturn.order?.shipping_email" class="text-[12px] text-gray-500 dark:text-gray-400 truncate">
                            {{ theReturn.customer?.email || theReturn.order?.shipping_email }}
                        </div>
                        <div v-if="theReturn.customer?.phone || theReturn.order?.shipping_phone" class="text-[12px] text-gray-500 dark:text-gray-400 num-tabular truncate">
                            {{ theReturn.customer?.phone || theReturn.order?.shipping_phone }}
                        </div>
                    </div>
                </div>
            </Card>

            <Card mode="flat" title="Order payment">
                <template #actions>
                    <Badge :variant="paymentVariant(theReturn.order?.payment?.status)" size="sm" dot>
                        {{ prettyStatus(theReturn.order?.payment?.status) || '—' }}
                    </Badge>
                </template>
                <div class="space-y-2 text-[13px]">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Method</span>
                        <span class="text-gray-900 dark:text-white/95 capitalize font-medium">{{ theReturn.order?.payment?.type || '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Amount paid</span>
                        <span class="num-tabular text-gray-900 dark:text-white/95 font-medium">₹{{ money(theReturn.order?.payment?.amount) }}</span>
                    </div>
                    <div v-if="Number(theReturn.order?.payment?.refunded_amount) > 0" class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Already refunded</span>
                        <span class="num-tabular text-warning-700 dark:text-warning-400 font-medium">₹{{ money(theReturn.order?.payment?.refunded_amount) }}</span>
                    </div>
                </div>
            </Card>

            <Card mode="flat" title="Return summary">
                <dl class="space-y-2.5 text-[13px]">
                    <div class="flex items-center justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Return #</dt>
                        <dd class="num-tabular text-gray-900 dark:text-white/95 font-medium">{{ theReturn.return_no }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Order</dt>
                        <dd>
                            <Link :href="route('admin.order.detail', theReturn.order.id)" class="num-tabular text-brand-600 dark:text-brand-400 hover:underline font-medium">
                                #{{ theReturn.order.order_no }}
                            </Link>
                        </dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Items</dt>
                        <dd class="num-tabular text-gray-900 dark:text-white/95 font-medium">{{ theReturn.items.length }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Refund amount</dt>
                        <dd class="num-tabular text-gray-900 dark:text-white/95 font-semibold">₹{{ money(theReturn.refund_amount) }}</dd>
                    </div>
                </dl>
            </Card>
        </aside>
    </div>

    <ConfirmDialog
        :open="rejectDialog.open"
        title="Reject return request?"
        message="The customer will see your reason on their return page. Rejected returns can't be un-rejected."
        confirm-label="Reject"
        variant="danger"
        :loading="loading.reject"
        :ask-reason="true"
        reason-placeholder="e.g. Package not sealed / used item"
        @confirm="handleReject"
        @cancel="rejectDialog.open = false"
    />
</template>

<script>
import Layout from '@/Layout/MainLayout.vue';
import ConfirmDialog from '@/Components/common/ConfirmDialog.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { PageHeader, Card, Button, Badge, Alert, Checkbox, EmptyState } from '@/Components/ui';
import { Check, X, PackageCheck, ExternalLink } from '@lucide/vue';

const AVATAR_COLORS = ['#3641F5', '#12B76A', '#F79009', '#0BA5EC', '#7A5AF8', '#EE46BC', '#F04438'];
function hashString(s = '') {
    let h = 0;
    for (let i = 0; i < s.length; i++) h = ((h << 5) - h + s.charCodeAt(i)) | 0;
    return Math.abs(h);
}

export default {
    layout: Layout,
    components: {
        Head, Link, ConfirmDialog,
        PageHeader, Card, Button, Badge, Alert, Checkbox, EmptyState,
        Check, X, PackageCheck, ExternalLink,
    },
    props: {
        return: { type: Object, required: true },
        reasons: { type: Object, default: () => ({}) },
    },
    data() {
        return {
            restockOnReceipt: !!this.return.restock_on_receipt,
            rejectDialog: { open: false },
            loading: { approve: false, reject: false, receive: false, refunded: false },
        };
    },
    computed: {
        theReturn() { return this.return; },
        crumbs() {
            return [
                { label: 'Dashboard', href: route('admin.dashboard') },
                { label: 'Returns', href: route('admin.returns.index') },
                { label: this.theReturn.return_no },
            ];
        },
    },
    methods: {
        money(v) {
            const n = Number(v || 0);
            return n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        formatDate(v, withTime = false) {
            if (!v) return '—';
            const d = new Date(v);
            if (Number.isNaN(d.getTime())) return v;
            return d.toLocaleString('en-IN', withTime
                ? { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }
                : { day: '2-digit', month: 'short', year: 'numeric' });
        },
        prettyStatus(s) {
            if (!s) return '';
            const raw = String(s).replaceAll('_', ' ');
            return raw.charAt(0).toUpperCase() + raw.slice(1);
        },
        itemImage(item) {
            const url = item?.media?.url;
            return url ? `/storage/product/${url}` : '/client/images/placeholder.jpg';
        },
        statusVariant(status) {
            return {
                requested: 'info',
                approved: 'warning',
                received: 'brand',
                refunded: 'success',
                rejected: 'error',
                cancelled: 'neutral',
            }[status] || 'neutral';
        },
        paymentVariant(status) {
            return {
                pending: 'warning',
                paid: 'success',
                failed: 'error',
                refunded: 'neutral',
                partially_refunded: 'warning',
            }[status] || 'neutral';
        },
        initials(name) {
            const n = (name || '').trim();
            if (!n) return '·';
            const parts = n.split(/\s+/).filter(Boolean);
            const first = parts[0]?.[0] || '';
            const last = parts.length > 1 ? parts[parts.length - 1][0] : '';
            return (first + last).toUpperCase() || first.toUpperCase();
        },
        avatarColor(name) { return AVATAR_COLORS[hashString(name || '') % AVATAR_COLORS.length]; },
        doApprove() {
            this.loading.approve = true;
            router.post(route('admin.returns.approve', this.return.id), {}, {
                preserveScroll: true,
                onFinish: () => { this.loading.approve = false; },
            });
        },
        openReject() { this.rejectDialog.open = true; },
        handleReject({ reason }) {
            const r = (reason || '').trim();
            if (!r) return;
            this.loading.reject = true;
            router.post(route('admin.returns.reject', this.return.id), { reason: r }, {
                preserveScroll: true,
                onFinish: () => {
                    this.loading.reject = false;
                    this.rejectDialog.open = false;
                },
            });
        },
        doReceive() {
            this.loading.receive = true;
            router.post(route('admin.returns.received', this.return.id), {
                restock: this.restockOnReceipt,
            }, {
                preserveScroll: true,
                onFinish: () => { this.loading.receive = false; },
            });
        },
        doRefunded() {
            this.loading.refunded = true;
            router.post(route('admin.returns.refunded', this.return.id), {}, {
                preserveScroll: true,
                onFinish: () => { this.loading.refunded = false; },
            });
        },
    },
};
</script>
