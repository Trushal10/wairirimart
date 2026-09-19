<template>
    <div class="rounded-2xl border border-gray-200 dark:border-white/[0.06] bg-white dark:bg-[color:var(--color-surface-dark)] overflow-hidden">
        <div class="border-b border-gray-100 dark:border-white/[0.06] px-5 py-4">
            <h3 class="text-h2 text-gray-900 dark:text-white/95">Profit &amp; loss statement</h3>
            <p class="mt-1 text-[12.5px] text-gray-500 dark:text-gray-400">
                Figures in {{ pnl.assumptions.currency }}. Assumptions:
                shipping cost ratio <span class="font-semibold text-gray-700 dark:text-gray-300 num-tabular">{{ pnl.assumptions.shipping_cost_ratio }}</span>,
                default gateway fee <span class="font-semibold text-gray-700 dark:text-gray-300 num-tabular">{{ (pnl.assumptions.default_gateway_fee_rate * 100).toFixed(2) }}%</span>.
            </p>
        </div>

        <table class="min-w-full text-[13.5px]">
            <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
                <tr
                    v-for="row in rows"
                    :key="row.key"
                    :class="[
                        row.emphasis ? 'bg-gray-50/60 dark:bg-white/[0.02]' : '',
                        row.total ? 'text-gray-900 dark:text-white/95' : '',
                    ]"
                >
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-3">
                            <span
                                v-if="row.emphasis"
                                class="inline-block w-1 rounded-full self-stretch"
                                :class="row.color || 'bg-gray-900 dark:bg-white'"
                                aria-hidden="true"
                            ></span>
                            <div>
                                <div :class="row.total ? 'text-h3 text-gray-900 dark:text-white/95' : 'text-body-strong text-gray-900 dark:text-white/95'">
                                    {{ row.label }}
                                </div>
                                <div v-if="row.hint" class="mt-0.5 text-[11.5px] text-gray-500 dark:text-gray-400">{{ row.hint }}</div>
                            </div>
                        </div>
                    </td>
                    <td
                        class="px-5 py-3.5 text-right num-tabular"
                        :class="[
                            row.negative ? 'text-error-700 dark:text-error-400' : 'text-gray-900 dark:text-white/95',
                            row.total ? 'text-h2 font-semibold' : 'font-medium',
                        ]"
                    >
                        <template v-if="row.isPct">{{ Number(row.value || 0).toFixed(2) }}%</template>
                        <template v-else>{{ row.negative ? '− ' : '' }}{{ money(row.value) }}</template>
                    </td>
                    <td class="hidden md:table-cell px-5 py-3.5 text-right w-24 num-tabular text-[11.5px] text-gray-500 dark:text-gray-400">
                        {{ row.pct !== null ? row.pct + '%' : '' }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    pnl: { type: Object, required: true },
});

function money(n) {
    return '₹' + Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

const rev = computed(() => Number(props.pnl.revenue) || 0);
function pct(v) {
    if (!rev.value) return null;
    return ((Number(v || 0) / rev.value) * 100).toFixed(2);
}

const rows = computed(() => [
    { key: 'revenue',   label: 'Revenue',                    hint: 'Sum of order totals within the selected range.',                  value: props.pnl.revenue,               pct: pct(props.pnl.revenue),                emphasis: true,  color: 'bg-success-500' },
    { key: 'cogs',      label: 'Product cost (COGS)',        hint: 'Cost of goods sold based on variant / product cost_price.',       value: props.pnl.cogs,                  pct: pct(props.pnl.cogs),                   negative: true },
    { key: 'disc',      label: 'Discounts',                  hint: 'Coupon and promotional discounts applied to orders.',             value: props.pnl.discounts,             pct: pct(props.pnl.discounts),              negative: true },
    { key: 'gross',     label: 'Gross profit',               hint: 'Revenue − COGS − Discounts',                                      value: props.pnl.gross_profit,          pct: pct(props.pnl.gross_profit),           emphasis: true,  color: 'bg-brand-500', total: true },

    { key: 'ship',      label: 'Shipping cost',              hint: 'Charged shipping × shipping_cost_ratio (see assumptions).',       value: props.pnl.shipping_cost,         pct: pct(props.pnl.shipping_cost),          negative: true },
    { key: 'other_o',   label: 'Other expenses (orders)',    hint: 'Sum of orders.other_expense in range.',                           value: props.pnl.other_order_expense,   pct: pct(props.pnl.other_order_expense),    negative: true },
    { key: 'other_p',   label: 'Other expenses (period)',    hint: 'Operating expenses (rent, salaries, marketing) in range.',        value: props.pnl.other_period_expense,  pct: pct(props.pnl.other_period_expense),   negative: true },
    { key: 'operating', label: 'Operating profit',           hint: 'Gross profit − Shipping − Other expenses',                        value: props.pnl.operating_profit,      pct: pct(props.pnl.operating_profit),       emphasis: true,  color: 'bg-brand-500', total: true },

    { key: 'fees',      label: 'Payment gateway fees',       hint: 'Actual or estimated processor fees (Razorpay etc.).',             value: props.pnl.gateway_fees,          pct: pct(props.pnl.gateway_fees),           negative: true },
    { key: 'refunds',   label: 'Refunds',                    hint: 'Money returned to customers.',                                    value: props.pnl.refunds,               pct: pct(props.pnl.refunds),                negative: true },
    { key: 'net',       label: 'Net profit',                 hint: 'Operating profit − Gateway fees − Refunds',                       value: props.pnl.net_profit,            pct: pct(props.pnl.net_profit),             emphasis: true,  color: (props.pnl.net_profit ?? 0) >= 0 ? 'bg-success-500' : 'bg-error-500', total: true },
    { key: 'margin',    label: 'Profit margin',              hint: 'Net profit / Revenue',                                            value: props.pnl.margin_pct,            pct: null,                                  emphasis: true,  color: 'bg-gray-900 dark:bg-white', isPct: true },
    { key: 'taxes',     label: 'Taxes collected',            hint: 'Informational — pass-through liability, not a P&L expense.',      value: props.pnl.taxes_collected,       pct: pct(props.pnl.taxes_collected) },
]);
</script>
