<template>
    <Card mode="flat" padding="md" class="mb-6">
        <div class="flex flex-wrap items-end gap-3">
            <!-- Date preset -->
            <div class="flex-1 min-w-[180px]">
                <label class="mb-1 block text-[12px] font-medium text-gray-600 dark:text-gray-400">Period</label>
                <SelectDropdown
                    v-model="local.preset"
                    :options="options.presets"
                    :nullable="false"
                    :searchable="false"
                    @update:modelValue="onPresetChange"
                />
            </div>

            <div v-if="local.preset === 'custom'" class="min-w-[150px]">
                <label class="mb-1 block text-[12px] font-medium text-gray-600 dark:text-gray-400">From</label>
                <Input v-model="local.from" type="date" />
            </div>
            <div v-if="local.preset === 'custom'" class="min-w-[150px]">
                <label class="mb-1 block text-[12px] font-medium text-gray-600 dark:text-gray-400">To</label>
                <Input v-model="local.to" type="date" />
            </div>

            <div class="min-w-[140px]" v-if="has('group')">
                <label class="mb-1 block text-[12px] font-medium text-gray-600 dark:text-gray-400">Group by</label>
                <SelectDropdown
                    v-model="local.group"
                    :options="options.groups"
                    :nullable="false"
                    :searchable="false"
                />
            </div>

            <div class="min-w-[160px]" v-if="has('status')">
                <label class="mb-1 block text-[12px] font-medium text-gray-600 dark:text-gray-400">Order status</label>
                <SelectDropdown
                    v-model="local.status"
                    :options="statusOptions"
                    :searchable="false"
                    null-label="All statuses"
                    null-value=""
                />
            </div>

            <div class="min-w-[170px]" v-if="has('payment_status')">
                <label class="mb-1 block text-[12px] font-medium text-gray-600 dark:text-gray-400">Payment status</label>
                <SelectDropdown
                    v-model="local.payment_status"
                    :options="paymentStatusOptions"
                    :searchable="false"
                    null-label="All"
                    null-value=""
                />
            </div>

            <div class="min-w-[160px]" v-if="has('payment_type')">
                <label class="mb-1 block text-[12px] font-medium text-gray-600 dark:text-gray-400">Gateway</label>
                <SelectDropdown
                    v-model="local.payment_type"
                    :options="options.paymentTypes"
                    null-label="All gateways"
                    null-value=""
                />
            </div>

            <div class="min-w-[160px]" v-if="has('courier_id')">
                <label class="mb-1 block text-[12px] font-medium text-gray-600 dark:text-gray-400">Courier</label>
                <SelectDropdown
                    v-model="local.courier_id"
                    :options="options.couriers"
                    null-label="All couriers"
                    :null-value="null"
                />
            </div>

            <div class="min-w-[160px]" v-if="has('category_id')">
                <label class="mb-1 block text-[12px] font-medium text-gray-600 dark:text-gray-400">Category</label>
                <SelectDropdown
                    v-model="local.category_id"
                    :options="options.categories"
                    null-label="All categories"
                    :null-value="null"
                />
            </div>

            <div class="min-w-[160px]" v-if="has('brand')">
                <label class="mb-1 block text-[12px] font-medium text-gray-600 dark:text-gray-400">Brand</label>
                <SelectDropdown
                    v-model="local.brand"
                    :options="options.brands"
                    null-label="All brands"
                    null-value=""
                />
            </div>

            <div class="flex-[2] min-w-[220px]" v-if="has('search')">
                <label class="mb-1 block text-[12px] font-medium text-gray-600 dark:text-gray-400">Search</label>
                <Input v-model="local.search" :placeholder="searchPlaceholder" @keyup.enter="apply()">
                    <template #leading><Search :size="14" /></template>
                </Input>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-2 ml-auto pt-6">
                <Button variant="ghost" size="md" @click="reset()">Reset</Button>
                <Button variant="primary" size="md" @click="apply()">
                    <template #leading><Filter :size="14" /></template>
                    Apply
                </Button>
            </div>
        </div>

        <!-- Active-filter chips -->
        <div
            v-if="activeChips.length"
            class="mt-4 pt-4 border-t border-gray-100 dark:border-white/[0.06] flex flex-wrap items-center gap-2"
        >
            <span class="text-eyebrow text-gray-500 dark:text-gray-400">Active</span>
            <button
                v-for="chip in activeChips"
                :key="chip.key"
                type="button"
                @click="removeChip(chip.key)"
                class="inline-flex items-center gap-1.5 rounded-md bg-gray-900 text-white dark:bg-white dark:text-gray-900 px-2 py-1 text-[11.5px] font-medium hover:bg-gray-800 dark:hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40 transition-colors"
            >
                <span>{{ chip.label }}: {{ chip.displayValue }}</span>
                <X :size="12" />
            </button>
        </div>

        <slot name="chips" :active="activeChips" :remove="removeChip" />
    </Card>
</template>

<script setup>
import { reactive, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import SelectDropdown from './SelectDropdown.vue';
import { Card, Button, Input } from '@/Components/ui';
import { Search, Filter, X } from '@lucide/vue';

const props = defineProps({
    filters: { type: Object, required: true },
    options: { type: Object, required: true },
    show: { type: Array, default: () => ['status', 'group', 'search'] },
    routeName: { type: String, required: true },
    searchPlaceholder: { type: String, default: 'Search…' },
});

const local = reactive({ ...defaultShape(), ...normalise(props.filters) });

function defaultShape() {
    return {
        preset: 'last_30', from: '', to: '', group: 'day',
        status: '', payment_status: '', payment_type: '',
        courier_id: null, category_id: null, brand: '',
        product_id: null, customer_id: null, search: '',
    };
}

function normalise(f) {
    return {
        preset: f.preset || 'last_30',
        from: f.from || '',
        to: f.to || '',
        group: f.group || 'day',
        status: f.status || '',
        payment_status: f.payment_status || '',
        payment_type: f.payment_type || '',
        courier_id: f.courier_id ?? null,
        category_id: f.category_id ?? null,
        brand: f.brand || '',
        product_id: f.product_id ?? null,
        customer_id: f.customer_id ?? null,
        search: f.search || '',
    };
}

function has(key) { return props.show.includes(key); }

function prettyStatus(s) {
    if (!s) return '';
    return s.split('_').map((w) => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
}

const statusOptions = computed(() =>
    (props.options.statuses || []).map((s) => ({ value: s, label: prettyStatus(s) })),
);
const paymentStatusOptions = computed(() =>
    (props.options.paymentStatuses || []).map((s) => ({ value: s, label: prettyStatus(s) })),
);

function onPresetChange(val) {
    if (val !== 'custom') {
        local.from = '';
        local.to = '';
    }
}

function apply() {
    const payload = {};
    for (const [k, v] of Object.entries(local)) {
        if (v === '' || v === null || v === undefined) continue;
        payload[k] = v;
    }
    router.get(route(props.routeName), payload, {
        preserveState: true, preserveScroll: true, replace: true,
    });
}

function reset() {
    Object.assign(local, defaultShape());
    router.get(route(props.routeName), {}, {
        preserveState: true, preserveScroll: true, replace: true,
    });
}

const activeChips = computed(() => {
    const map = {
        status: 'Status', payment_status: 'Payment', payment_type: 'Gateway',
        courier_id: 'Courier', category_id: 'Category', brand: 'Brand',
        product_id: 'Product', customer_id: 'Customer', search: 'Search',
    };
    const chips = [];
    for (const [k, label] of Object.entries(map)) {
        const v = local[k];
        if (v === '' || v === null || v === undefined) continue;
        chips.push({ key: k, label, value: v, displayValue: displayValueFor(k, v) });
    }
    return chips;
});

function displayValueFor(key, value) {
    const bucketMap = {
        payment_type: props.options.paymentTypes,
        courier_id: props.options.couriers,
        category_id: props.options.categories,
        brand: props.options.brands,
    };
    const bucket = bucketMap[key];
    if (bucket) {
        const match = bucket.find((o) => String(o.value) === String(value));
        if (match) return match.label;
    }
    if (key === 'status' || key === 'payment_status') return prettyStatus(value);
    return String(value);
}

function removeChip(key) {
    local[key] = defaultShape()[key];
    apply();
}

watch(() => props.filters, (f) => Object.assign(local, defaultShape(), normalise(f)), { deep: true });
</script>
