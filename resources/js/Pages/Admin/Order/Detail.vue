<template>
    <Head :title="`Order #${order.order_no}`" />

    <PageHeader
        :title="`Order #${order.order_no}`"
        :subtitle="`Placed ${formatDate(order.created_at, true)}`"
        :crumbs="crumbs"
    >
        <template #titleBadge>
            <Badge :variant="statusVariant(order.status)" size="sm" dot>{{ prettyStatus(order.status) }}</Badge>
            <Badge v-if="order.payment?.status" :variant="paymentVariant(order.payment.status)" size="sm" dot>
                {{ prettyStatus(order.payment.status) }}
            </Badge>
        </template>
        <template #actions>
            <Button variant="secondary" size="sm" tag="a" :href="route('admin.order.label', order.id)" target="_blank" rel="noopener">
                <template #leading><Printer :size="14" /></template>
                Print label
            </Button>
            <Button variant="primary" size="sm" tag="a" :href="route('admin.order.invoice', order.id)" target="_blank" rel="noopener">
                <template #leading><FileText :size="14" /></template>
                Invoice
            </Button>
        </template>
    </PageHeader>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <!-- Main -->
        <div class="space-y-6 xl:col-span-2">
            <!-- Items -->
            <Card mode="flat" :title="`Items · ${order.order_items?.length || 0}`" padding="none">
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="min-w-full text-[13.5px]">
                        <thead class="bg-gray-50/60 dark:bg-white/[0.02] text-eyebrow text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-white/[0.06]">
                            <tr>
                                <th class="px-5 py-3 text-left">Product</th>
                                <th class="px-4 py-3 text-left w-40">Variant</th>
                                <th class="px-4 py-3 text-right w-16">Qty</th>
                                <th class="px-4 py-3 text-right w-24">Price</th>
                                <th class="px-5 py-3 text-right w-28">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
                            <tr v-for="item in order.order_items || []" :key="item.id">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <img
                                            :src="itemImage(item)"
                                            :alt="item.product?.name || 'Product'"
                                            class="h-11 w-11 shrink-0 rounded-lg object-cover border border-gray-200 dark:border-white/[0.06] bg-gray-100 dark:bg-white/[0.04]"
                                            @error="ev => (ev.target.src = '/client/images/placeholder.jpg')"
                                        />
                                        <div class="min-w-0">
                                            <div class="text-body-strong text-gray-900 dark:text-white/95 truncate">
                                                {{ item.product?.name || '—' }}
                                            </div>
                                            <div v-if="item.product?.sku" class="mt-0.5 inline-flex items-center gap-1 text-[11.5px] text-gray-500 dark:text-gray-400 num-tabular">
                                                <Hash :size="10" />{{ item.product.sku }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-[12.5px] text-gray-500 dark:text-gray-400 space-y-0.5">
                                    <div v-if="item.size">Size · {{ item.size }}</div>
                                    <div v-if="item.color">Color · {{ item.color }}</div>
                                    <div v-if="!item.size && !item.color" class="italic">—</div>
                                </td>
                                <td class="px-4 py-4 text-right num-tabular text-gray-700 dark:text-gray-300">
                                    {{ item.quantity }}
                                </td>
                                <td class="px-4 py-4 text-right num-tabular text-gray-700 dark:text-gray-300">
                                    ₹{{ money(item.price) }}
                                </td>
                                <td class="px-5 py-4 text-right num-tabular text-body-strong text-gray-900 dark:text-white/95">
                                    ₹{{ money(item.price * item.quantity) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t border-gray-100 dark:border-white/[0.06] bg-gray-50/40 dark:bg-white/[0.02]">
                            <tr>
                                <td colspan="4" class="px-5 py-2 text-right text-[12.5px] text-gray-600 dark:text-gray-400">Subtotal</td>
                                <td class="px-5 py-2 text-right num-tabular text-[13px] text-gray-900 dark:text-white/95">₹{{ money(order.sub_total) }}</td>
                            </tr>
                            <tr>
                                <td colspan="4" class="px-5 py-2 text-right text-[12.5px] text-gray-600 dark:text-gray-400">Shipping</td>
                                <td class="px-5 py-2 text-right num-tabular text-[13px] text-gray-900 dark:text-white/95">₹{{ money(order.shipping) }}</td>
                            </tr>
                            <tr>
                                <td colspan="4" class="px-5 py-2 text-right text-[12.5px] text-gray-600 dark:text-gray-400">Discount</td>
                                <td class="px-5 py-2 text-right num-tabular text-[13px] text-gray-900 dark:text-white/95">− ₹{{ money(order.discount) }}</td>
                            </tr>
                            <tr class="border-t border-gray-200 dark:border-white/[0.08]">
                                <td colspan="4" class="px-5 py-3 text-right text-h3 text-gray-900 dark:text-white/95">Grand total</td>
                                <td class="px-5 py-3 text-right num-tabular text-h2 text-gray-900 dark:text-white/95">
                                    ₹{{ money(order.total) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </Card>

            <!-- Shipment -->
            <Card v-if="latestShipment" mode="flat" title="Shipment">
                <template #actions>
                    <Badge :variant="shipmentVariant(latestShipment.status)" dot>{{ prettyStatus(latestShipment.status) }}</Badge>
                </template>

                <dl class="grid gap-x-6 gap-y-3 md:grid-cols-2 text-[13px]">
                    <MetaRow label="Provider" :value="latestShipment.provider" />
                    <MetaRow label="Courier" :value="latestShipment.courier_name" />
                    <MetaRow label="AWB" :value="latestShipment.awb_code" mono />
                    <MetaRow label="Shipment ID" :value="latestShipment.provider_shipment_id" mono />
                    <MetaRow label="Pickup scheduled" :value="latestShipment.pickup_scheduled_date ? formatDate(latestShipment.pickup_scheduled_date) : null" />
                    <MetaRow label="Shipped" :value="latestShipment.shipped_at ? formatDate(latestShipment.shipped_at) : null" />
                    <MetaRow label="Delivered" :value="latestShipment.delivered_at ? formatDate(latestShipment.delivered_at) : null" />
                    <div v-if="latestShipment.tracking_url" class="col-span-full">
                        <a
                            :href="latestShipment.tracking_url"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-1 text-[12.5px] font-medium text-brand-600 dark:text-brand-400 hover:underline"
                        >
                            Open tracking
                            <ExternalLink :size="12" />
                        </a>
                    </div>
                </dl>

                <div class="mt-5 flex flex-wrap gap-2">
                    <Button v-if="!latestShipment.awb_code" variant="primary" size="sm" :loading="loading.awb" @click="doAction('assign_awb')">
                        <template #leading><Barcode :size="14" /></template>
                        Assign AWB
                    </Button>
                    <Button
                        v-if="latestShipment.awb_code && !latestShipment.pickup_scheduled_date"
                        variant="primary"
                        size="sm"
                        :loading="loading.pickup"
                        @click="doAction('pickup')"
                    >
                        <template #leading><Truck :size="14" /></template>
                        Request pickup
                    </Button>
                    <Button v-if="latestShipment.awb_code" variant="secondary" size="sm" :loading="loading.label" @click="doAction('label')">
                        Generate label
                    </Button>
                    <Button
                        variant="secondary"
                        size="sm"
                        tag="a"
                        :href="route('admin.order.label', order.id)"
                        target="_blank"
                        rel="noopener"
                    >
                        <template #leading><Printer :size="14" /></template>
                        Print label
                    </Button>
                    <Button
                        v-if="latestShipment.label_url"
                        variant="ghost"
                        size="sm"
                        tag="a"
                        :href="`${route('admin.order.label', order.id)}?prefer=inhouse`"
                        target="_blank"
                        rel="noopener"
                        title="Use the themed in-house label instead of the courier's PDF"
                    >
                        In-house label
                    </Button>
                    <Button variant="ghost" size="sm" :loading="loading.sync" @click="doAction('sync')">
                        <template #leading><RefreshCw :size="14" /></template>
                        Sync tracking
                    </Button>
                    <Button
                        v-if="!isTerminalShipment"
                        variant="danger-outline"
                        size="sm"
                        :loading="loading.cancel"
                        @click="doAction('cancel')"
                    >
                        Cancel shipment
                    </Button>
                </div>
            </Card>

            <!-- Timeline -->
            <Card mode="flat" title="Order timeline">
                <ol v-if="timeline.length" class="relative ml-2 border-l border-gray-200 dark:border-white/[0.08]">
                    <li v-for="event in timeline" :key="event.id" class="mb-5 ml-6 last:mb-0">
                        <span
                            :class="[
                                'absolute -left-[9px] flex h-4 w-4 items-center justify-center rounded-full ring-4 ring-white dark:ring-[color:var(--color-surface-dark)]',
                                timelineDotClass(event.status),
                            ]"
                        ></span>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-body-strong text-gray-900 dark:text-white/95 capitalize">
                                {{ prettyStatus(event.status) }}
                            </span>
                            <Badge size="sm" variant="neutral">{{ event.source }}</Badge>
                            <time class="text-[11.5px] text-gray-500 dark:text-gray-400 num-tabular">
                                {{ formatDate(event.created_at, true) }}
                            </time>
                        </div>
                        <p v-if="event.comment" class="mt-1 text-[13px] text-gray-600 dark:text-gray-400">
                            {{ event.comment }}
                        </p>
                    </li>
                </ol>
                <EmptyState
                    v-else
                    size="sm"
                    title="No timeline events yet"
                    description="Order status changes and shipment events will appear here."
                />
            </Card>
        </div>

        <!-- Sidebar -->
        <aside class="space-y-6 xl:col-span-1">
            <!-- Fulfillment controls -->
            <Card mode="flat" title="Fulfillment">
                <div class="space-y-4">
                    <FormField label="Order status" for-id="order-status">
                        <Select
                            id="order-status"
                            v-model="statusDraft"
                            :options="statusOptions"
                            size="sm"
                            :disabled="loading.status"
                            @change="submitStatus"
                        />
                    </FormField>
                    <FormField label="Shipping partner" for-id="order-partner">
                        <Select
                            id="order-partner"
                            v-model="deliveryPartnerId"
                            :options="partnerOptions"
                            placeholder="Select partner"
                            size="sm"
                            :disabled="loading.partner"
                            @change="assignDeliveryPartner"
                        />
                    </FormField>
                </div>
            </Card>

            <!-- Customer -->
            <Card mode="flat" title="Customer">
                <div class="flex items-start gap-3">
                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-white font-semibold text-[12.5px]"
                        :style="{ background: avatarColor(order.customer?.name || order.shipping_name) }"
                        aria-hidden="true"
                    >{{ initials(order.customer?.name || order.shipping_name) }}</span>
                    <div class="min-w-0 flex-1">
                        <div class="text-body-strong text-gray-900 dark:text-white/95 truncate">
                            {{ order.customer?.name || order.shipping_name }}
                        </div>
                        <div v-if="order.customer?.email || order.shipping_email" class="text-[12px] text-gray-500 dark:text-gray-400 truncate">
                            {{ order.customer?.email || order.shipping_email }}
                        </div>
                        <div v-if="order.shipping_phone" class="text-[12px] text-gray-500 dark:text-gray-400 num-tabular truncate">
                            {{ order.shipping_phone }}
                        </div>
                    </div>
                </div>
            </Card>

            <!-- Shipping address -->
            <Card mode="flat" title="Shipping address">
                <div class="text-[13px] text-gray-700 dark:text-gray-300 leading-6">
                    <div class="text-body-strong text-gray-900 dark:text-white/95">{{ order.shipping_name }}</div>
                    <div>{{ order.shipping_address }}</div>
                    <div>{{ order.shipping_city }}, {{ order.shipping_state }} — <span class="num-tabular">{{ order.shipping_pincode }}</span></div>
                </div>
            </Card>

            <!-- Payment -->
            <Card mode="flat" title="Payment">
                <template #actions>
                    <Badge :variant="paymentVariant(order.payment?.status)" size="sm" dot>
                        {{ prettyStatus(order.payment?.status) || 'no payment' }}
                    </Badge>
                </template>
                <div class="space-y-2 text-[13px]">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Method</span>
                        <span class="text-gray-900 dark:text-white/95 capitalize font-medium">{{ order.payment?.type || '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Amount paid</span>
                        <span class="num-tabular text-gray-900 dark:text-white/95 font-medium">₹{{ money(order.payment?.amount) }}</span>
                    </div>
                    <div v-if="Number(order.payment?.refunded_amount) > 0" class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Refunded</span>
                        <span class="num-tabular text-warning-700 dark:text-warning-400 font-medium">₹{{ money(order.payment?.refunded_amount) }}</span>
                    </div>
                </div>

                <div v-if="canRefund" class="mt-4 pt-4 border-t border-gray-100 dark:border-white/[0.06] space-y-2">
                    <FormField label="Refund amount" for-id="refund-amount" :hint="`Max ₹${money(refundMax)}`" :error="refundError">
                        <Input
                            id="refund-amount"
                            v-model="refundAmount"
                            type="number"
                            step="0.01"
                            :placeholder="money(refundMax)"
                            size="sm"
                            :error="!!refundError"
                            @input="refundError = ''"
                        >
                            <template #leading><span class="text-[12.5px]">₹</span></template>
                        </Input>
                    </FormField>
                    <FormField label="Reason" for-id="refund-reason" optional>
                        <Input
                            id="refund-reason"
                            v-model="refundReason"
                            maxlength="200"
                            placeholder="e.g. wrong size"
                            size="sm"
                        />
                    </FormField>
                    <Button variant="danger" size="sm" :loading="loading.refund" @click="submitRefund" block>
                        <template #leading><RotateCcw :size="14" /></template>
                        Issue refund
                    </Button>
                </div>
            </Card>
        </aside>
    </div>

    <ConfirmDialog
        :open="confirmDialog.open"
        :title="confirmDialog.title"
        :message="confirmDialog.message"
        :details="confirmDialog.details"
        :confirm-label="confirmDialog.confirmLabel"
        :cancel-label="confirmDialog.cancelLabel || 'Cancel'"
        :variant="confirmDialog.variant"
        :loading="confirmDialog.loading"
        :ask-reason="confirmDialog.askReason"
        :reason-placeholder="confirmDialog.reasonPlaceholder"
        @confirm="onConfirmDialogConfirm"
        @cancel="closeConfirm"
    />
</template>

<script>
import { Head, router, Link } from '@inertiajs/vue3';
import Layout from '@/Layout/MainLayout.vue';
import ConfirmDialog from '@/Components/common/ConfirmDialog.vue';
import { PageHeader, Card, Button, Badge, EmptyState, Select, Input, FormField } from '@/Components/ui';
import {
    FileText, Printer, Barcode, Truck, RefreshCw,
    RotateCcw, ExternalLink, Hash,
} from '@lucide/vue';

const MetaRow = {
    props: { label: String, value: [String, Number], mono: Boolean },
    template: `
        <div class="flex items-baseline justify-between gap-3">
            <dt class="text-[12.5px] text-gray-500 dark:text-gray-400">{{ label }}</dt>
            <dd :class="[
                'text-[13px] text-right text-gray-900 dark:text-white/95 truncate',
                mono ? 'num-tabular font-mono' : '',
                !value ? 'italic text-gray-400 dark:text-gray-500 font-normal' : 'font-medium',
            ]">{{ value || '—' }}</dd>
        </div>
    `,
};

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
        PageHeader, Card, Button, Badge, EmptyState, Select, Input, FormField,
        FileText, Printer, Barcode, Truck, RefreshCw, RotateCcw, ExternalLink, Hash,
        MetaRow,
    },
    props: {
        order: { type: Object, required: true },
        partners: { type: [Object, Array], default: () => [] },
    },
    data() {
        return {
            statusDraft: this.order?.status || 'pending',
            deliveryPartnerId: this.order?.delivery_partner_id || '',
            refundAmount: null,
            refundReason: '',
            refundError: '',
            confirmDialog: {
                open: false, title: '', message: '', details: '',
                confirmLabel: 'Confirm', variant: 'primary', loading: false,
                askReason: false, reasonPlaceholder: '', onConfirm: null,
            },
            loading: {
                status: false, partner: false, refund: false,
                awb: false, pickup: false, label: false, sync: false, cancel: false,
            },
        };
    },
    computed: {
        crumbs() {
            return [
                { label: 'Dashboard', href: route('admin.dashboard') },
                { label: 'Orders', href: route('admin.orders') },
                { label: `#${this.order.order_no}` },
            ];
        },
        latestShipment() {
            const list = this.order?.shipments || [];
            return list.length ? list[0] : this.order?.latest_shipment || null;
        },
        timeline() {
            return [...(this.order?.status_history || [])].sort(
                (a, b) => new Date(b.created_at) - new Date(a.created_at)
            );
        },
        canRefund() {
            const p = this.order?.payment;
            if (!p) return false;
            const paid = Number(p.amount || 0);
            const refunded = Number(p.refunded_amount || 0);
            return p.type === 'razorpay'
                && (p.status === 'paid' || p.status === 'partially_refunded')
                && refunded < paid;
        },
        refundMax() {
            const p = this.order?.payment;
            if (!p) return 0;
            return Number(p.amount || 0) - Number(p.refunded_amount || 0);
        },
        isTerminalShipment() {
            const s = this.latestShipment?.status;
            return ['delivered', 'rto', 'cancelled', 'failed'].includes(s);
        },
        partnerOptions() {
            const list = Array.isArray(this.partners) ? this.partners : Object.values(this.partners || {});
            return list.map((p) => ({ value: p.id, label: p.name }));
        },
        statusOptions() {
            return [
                { value: 'pending', label: 'Pending' },
                { value: 'confirmed', label: 'Confirmed' },
                { value: 'delivered', label: 'Delivered' },
                { value: 'canceled', label: 'Cancelled' },
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
            const opts = withTime
                ? { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }
                : { day: '2-digit', month: 'short', year: 'numeric' };
            return d.toLocaleString('en-IN', opts);
        },
        itemImage(item) {
            const url = item?.media?.url;
            return url ? `/storage/product/${url}` : '/client/images/placeholder.jpg';
        },
        prettyStatus(status) {
            if (!status) return '—';
            const s = String(status).replaceAll('_', ' ');
            return s.charAt(0).toUpperCase() + s.slice(1);
        },
        statusVariant(status) {
            return {
                pending: 'warning',
                confirmed: 'brand',
                delivered: 'success',
                shipped: 'info',
                canceled: 'error',
                cancelled: 'error',
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
        shipmentVariant(status) {
            if (status === 'delivered') return 'success';
            if (['cancelled', 'rto', 'failed'].includes(status)) return 'error';
            return 'info';
        },
        timelineDotClass(status) {
            if (status === 'delivered') return 'bg-success-500';
            if (['cancelled', 'canceled', 'rto', 'failed'].includes(status)) return 'bg-error-500';
            if (status === 'pending') return 'bg-warning-500';
            return 'bg-brand-500';
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
        submitStatus() {
            const url = route('admin.order.update', this.order.id);
            this.loading.status = true;
            router.put(url, { status: this.statusDraft }, {
                preserveScroll: true,
                onError: () => { this.statusDraft = this.order.status; },
                onFinish: () => { this.loading.status = false; },
            });
        },
        assignDeliveryPartner() {
            if (!this.deliveryPartnerId) return;
            const url = route('admin.orders.delivery.update', this.order.id);
            this.loading.partner = true;
            router.put(url, { delivery_partner_id: this.deliveryPartnerId }, {
                preserveScroll: true,
                onFinish: () => { this.loading.partner = false; },
            });
        },
        doAction(kind) {
            const s = this.latestShipment;
            if (!s) return;
            const map = {
                assign_awb: { route: 'admin.shipment.assign_awb', flag: 'awb' },
                pickup: { route: 'admin.shipment.pickup', flag: 'pickup' },
                label: { route: 'admin.shipment.label', flag: 'label' },
                sync: { route: 'admin.shipment.sync', flag: 'sync' },
                cancel: { route: 'admin.shipment.cancel', flag: 'cancel' },
            };
            const entry = map[kind];
            if (!entry) return;

            const runAction = (extra = {}) => {
                this.loading[entry.flag] = true;
                router.post(route(entry.route, s.id), extra, {
                    preserveScroll: true,
                    onFinish: () => { this.loading[entry.flag] = false; },
                });
            };

            if (kind === 'cancel') {
                this.openConfirm({
                    title: 'Cancel this shipment?',
                    message: 'The courier will be notified and the order will move to Cancelled. This cannot be undone.',
                    confirmLabel: 'Yes, cancel it',
                    variant: 'danger',
                    askReason: true,
                    reasonPlaceholder: 'Why is this shipment being cancelled?',
                    onConfirm: ({ reason }) => {
                        this.closeConfirm();
                        runAction(reason ? { reason } : {});
                    },
                });
                return;
            }
            runAction();
        },
        submitRefund() {
            if (!this.canRefund) return;
            this.refundError = '';
            const amount = this.refundAmount || this.refundMax;
            if (!amount || amount <= 0 || amount > this.refundMax) {
                this.refundError = `Enter a valid amount up to ₹${this.money(this.refundMax)}.`;
                return;
            }
            this.openConfirm({
                title: 'Confirm refund',
                message: 'Refund this amount to the customer\'s original payment method?',
                details: '₹' + this.money(amount),
                confirmLabel: 'Yes, refund',
                variant: 'primary',
                onConfirm: () => {
                    this.closeConfirm();
                    this.loading.refund = true;
                    router.post(route('admin.order.refund', this.order.id), {
                        amount,
                        reason: this.refundReason || null,
                    }, {
                        preserveScroll: true,
                        onSuccess: () => {
                            this.refundAmount = null;
                            this.refundReason = '';
                            this.refundError = '';
                        },
                        onFinish: () => { this.loading.refund = false; },
                    });
                },
            });
        },
        openConfirm(cfg) {
            this.confirmDialog = {
                ...this.confirmDialog,
                open: true, loading: false, message: '', details: '',
                askReason: false, reasonPlaceholder: '', cancelLabel: 'Cancel',
                ...cfg,
            };
        },
        closeConfirm() {
            this.confirmDialog.open = false;
            this.confirmDialog.loading = false;
            this.confirmDialog.onConfirm = null;
        },
        onConfirmDialogConfirm(payload) {
            if (typeof this.confirmDialog.onConfirm === 'function') {
                this.confirmDialog.onConfirm(payload);
            } else {
                this.closeConfirm();
            }
        },
    },
};
</script>
