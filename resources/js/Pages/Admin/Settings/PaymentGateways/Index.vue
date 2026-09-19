<template>
    <Head title="Payment gateways" />

    <SettingsShell
        title="Payment gateways"
        subtitle="Enable providers, add credentials, and choose your storefront default."
    >
        <div class="space-y-6">
            <Alert v-if="!hasDefault" variant="warning" title="No default gateway set">
                Customers won't be able to check out until you set a default active payment gateway.
                Enable a gateway, then click <strong>Set default</strong>.
            </Alert>

            <div class="grid gap-4 md:grid-cols-2">
                <div
                    v-for="g in gateways"
                    :key="g.id"
                    :class="[
                        'group relative rounded-2xl bg-white dark:bg-[color:var(--color-surface-dark)] p-5 transition-all',
                        g.is_default
                            ? 'border-2 border-gray-900 dark:border-white shadow-elevation-2'
                            : 'border border-gray-200 dark:border-white/[0.06] hover:border-gray-300 dark:hover:border-white/[0.14] hover:shadow-elevation-1',
                    ]"
                >
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div
                                class="h-11 w-11 shrink-0 rounded-xl flex items-center justify-center font-bold uppercase text-[13px]"
                                :class="gatewayColor(g.code)"
                            >
                                {{ initials(g.name) }}
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-h3 text-gray-900 dark:text-white/95 truncate">{{ g.name }}</h4>
                                <div class="mt-0.5 flex flex-wrap items-center gap-1.5 text-[11.5px] text-gray-500 dark:text-gray-400 num-tabular">
                                    <span class="font-mono">{{ g.code }}</span>
                                    <span class="text-gray-300 dark:text-gray-600">·</span>
                                    <Badge :variant="g.mode === 'live' ? 'success' : 'warning'" size="sm" shape="rounded">
                                        {{ g.mode === 'live' ? 'Live' : 'Test' }}
                                    </Badge>
                                    <span v-if="typeof g.priority === 'number'" class="text-gray-300 dark:text-gray-600">·</span>
                                    <span v-if="typeof g.priority === 'number'">Priority {{ g.priority }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col items-end gap-1.5 shrink-0">
                            <Badge v-if="g.is_default" variant="neutral" solid size="sm">Default</Badge>
                            <Badge :variant="g.is_active ? 'success' : 'neutral'" dot size="sm">
                                {{ g.is_active ? 'Active' : 'Disabled' }}
                            </Badge>
                        </div>
                    </div>

                    <p v-if="g.description" class="mb-3 text-[13px] text-gray-600 dark:text-gray-400">
                        {{ g.description }}
                    </p>

                    <div v-if="g.supports?.length" class="mb-4 flex flex-wrap gap-1">
                        <Badge v-for="s in g.supports" :key="s" size="sm" variant="neutral">{{ s }}</Badge>
                    </div>

                    <div
                        v-if="!g.credentials_present && g.code !== 'cod'"
                        class="mb-4 flex items-start gap-2 rounded-lg border border-warning-200 dark:border-warning-500/25 bg-warning-50 dark:bg-warning-500/10 p-3 text-[12px] text-warning-900 dark:text-warning-200"
                    >
                        <AlertTriangle :size="14" class="mt-px shrink-0 text-warning-600 dark:text-warning-400" />
                        <span>Credentials not configured — enabling will fail until keys are entered.</span>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button variant="primary" size="sm" tag="a" :href="route('admin.payment_gateways.edit', g.id)">
                            <template #leading><Settings2 :size="14" /></template>
                            Configure
                        </Button>
                        <Button
                            :variant="g.is_active ? 'secondary' : 'success'"
                            size="sm"
                            :loading="busy === g.id && action === 'toggle'"
                            :disabled="busy === g.id"
                            @click="toggle(g)"
                        >
                            <template #leading>
                                <component :is="g.is_active ? PowerOff : Power" :size="14" />
                            </template>
                            {{ g.is_active ? 'Disable' : 'Enable' }}
                        </Button>
                        <Button
                            v-if="!g.is_default && g.is_active"
                            variant="secondary"
                            size="sm"
                            :loading="busy === g.id && action === 'default'"
                            :disabled="busy === g.id"
                            @click="setDefault(g)"
                        >
                            <template #leading><Star :size="14" /></template>
                            Set default
                        </Button>
                        <Button
                            v-if="g.code !== 'cod'"
                            variant="ghost"
                            size="sm"
                            :loading="busy === g.id && action === 'test'"
                            :disabled="busy === g.id"
                            @click="test(g)"
                        >
                            <template #leading><Zap :size="14" /></template>
                            Test
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </SettingsShell>
</template>

<script>
import { Head, router } from '@inertiajs/vue3';
import Layout from '@/Layout/MainLayout.vue';
import SettingsShell from '@/Components/SettingsShell.vue';
import { Button, Badge, Alert } from '@/Components/ui';
import { Settings2, Power, PowerOff, Star, Zap, AlertTriangle } from '@lucide/vue';

export default {
    layout: Layout,
    components: {
        Head, SettingsShell,
        Button, Badge, Alert,
        Settings2, Star, Zap, AlertTriangle,
    },
    props: {
        gateways: { type: Array, required: true },
    },
    setup() {
        return { Power, PowerOff };
    },
    data() {
        return { busy: null, action: null };
    },
    computed: {
        hasDefault() {
            return this.gateways.some((g) => g.is_default && g.is_active);
        },
    },
    methods: {
        initials(name) {
            const parts = String(name || '?').trim().split(/\s+/);
            if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
            return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
        },
        gatewayColor(code) {
            const map = {
                razorpay: 'bg-blue-light-100 text-blue-light-700 dark:bg-blue-light-500/15 dark:text-blue-light-400',
                stripe:   'bg-brand-100 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400',
                paypal:   'bg-warning-100 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
                cod:      'bg-success-100 text-success-700 dark:bg-success-500/15 dark:text-success-400',
            };
            return map[code] || 'bg-gray-100 text-gray-700 dark:bg-white/[0.06] dark:text-gray-300';
        },
        toggle(g) {
            this.busy = g.id;
            this.action = 'toggle';
            router.post(route('admin.payment_gateways.toggle', g.id), {}, {
                preserveScroll: true,
                onFinish: () => { this.busy = null; this.action = null; },
            });
        },
        setDefault(g) {
            this.busy = g.id;
            this.action = 'default';
            router.post(route('admin.payment_gateways.default', g.id), {}, {
                preserveScroll: true,
                onFinish: () => { this.busy = null; this.action = null; },
            });
        },
        test(g) {
            this.busy = g.id;
            this.action = 'test';
            router.post(route('admin.payment_gateways.test', g.id), {}, {
                preserveScroll: true,
                onFinish: () => { this.busy = null; this.action = null; },
            });
        },
    },
};
</script>
