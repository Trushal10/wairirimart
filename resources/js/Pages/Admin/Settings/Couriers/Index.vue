<template>
    <Head title="Courier partners" />

    <SettingsShell
        title="Courier partners"
        subtitle="Enable a partner, add credentials, and choose the storefront default."
    >
        <div class="space-y-6">
            <Alert v-if="!hasDefault" variant="warning" title="No default courier set">
                You need at least one active default courier to auto-assign shipments.
                Enable a partner, then click <strong>Set default</strong>.
            </Alert>

            <div class="grid gap-4 md:grid-cols-2">
                <div
                    v-for="p in partners"
                    :key="p.id"
                    :class="[
                        'group relative rounded-2xl bg-white dark:bg-[color:var(--color-surface-dark)] p-5 transition-all',
                        p.is_default
                            ? 'border-2 border-gray-900 dark:border-white shadow-elevation-2'
                            : 'border border-gray-200 dark:border-white/[0.06] hover:border-gray-300 dark:hover:border-white/[0.14] hover:shadow-elevation-1',
                    ]"
                >
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div
                                class="h-11 w-11 shrink-0 rounded-xl flex items-center justify-center font-bold uppercase text-[13px]"
                                :class="courierColor(p.code)"
                            >
                                {{ initials(p.name) }}
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-h3 text-gray-900 dark:text-white/95 truncate">{{ p.name }}</h4>
                                <div class="mt-0.5 flex flex-wrap items-center gap-1.5 text-[11.5px] text-gray-500 dark:text-gray-400 num-tabular">
                                    <span class="font-mono">{{ p.code }}</span>
                                    <span class="text-gray-300 dark:text-gray-600">·</span>
                                    <Badge :variant="p.mode === 'live' ? 'success' : 'warning'" size="sm" shape="rounded">
                                        {{ p.mode === 'live' ? 'Live' : 'Test' }}
                                    </Badge>
                                    <span v-if="typeof p.priority === 'number'" class="text-gray-300 dark:text-gray-600">·</span>
                                    <span v-if="typeof p.priority === 'number'">Priority {{ p.priority }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col items-end gap-1.5 shrink-0">
                            <Badge v-if="p.is_default" variant="neutral" solid size="sm">Default</Badge>
                            <Badge :variant="p.is_active ? 'success' : 'neutral'" dot size="sm">
                                {{ p.is_active ? 'Active' : 'Disabled' }}
                            </Badge>
                        </div>
                    </div>

                    <p v-if="p.description" class="mb-3 text-[13px] text-gray-600 dark:text-gray-400">
                        {{ p.description }}
                    </p>

                    <div v-if="p.supports?.length" class="mb-4 flex flex-wrap gap-1">
                        <Badge v-for="s in p.supports" :key="s" size="sm" variant="neutral">{{ s }}</Badge>
                    </div>

                    <div
                        v-if="p.is_third_party && !p.credentials_present"
                        class="mb-4 flex items-start gap-2 rounded-lg border border-warning-200 dark:border-warning-500/25 bg-warning-50 dark:bg-warning-500/10 p-3 text-[12px] text-warning-900 dark:text-warning-200"
                    >
                        <AlertTriangle :size="14" class="mt-px shrink-0 text-warning-600 dark:text-warning-400" />
                        <span>Credentials not configured — external API calls will fail.</span>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button variant="primary" size="sm" tag="a" :href="route('admin.couriers.edit', p.id)">
                            <template #leading><Settings2 :size="14" /></template>
                            Configure
                        </Button>
                        <Button
                            :variant="p.is_active ? 'secondary' : 'success'"
                            size="sm"
                            :loading="busy === p.id && action === 'toggle'"
                            :disabled="busy === p.id"
                            @click="toggle(p)"
                        >
                            <template #leading>
                                <component :is="p.is_active ? PowerOff : Power" :size="14" />
                            </template>
                            {{ p.is_active ? 'Disable' : 'Enable' }}
                        </Button>
                        <Button
                            v-if="!p.is_default && p.is_active"
                            variant="secondary"
                            size="sm"
                            :loading="busy === p.id && action === 'default'"
                            :disabled="busy === p.id"
                            @click="setDefault(p)"
                        >
                            <template #leading><Star :size="14" /></template>
                            Set default
                        </Button>
                        <Button
                            v-if="p.is_third_party"
                            variant="ghost"
                            size="sm"
                            :loading="busy === p.id && action === 'test'"
                            :disabled="busy === p.id"
                            @click="test(p)"
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
        partners: { type: Array, required: true },
    },
    setup() {
        return { Power, PowerOff };
    },
    data() {
        return { busy: null, action: null };
    },
    computed: {
        hasDefault() {
            return this.partners.some((p) => p.is_default && p.is_active);
        },
    },
    methods: {
        initials(name) {
            const parts = String(name || '?').trim().split(/\s+/);
            if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
            return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
        },
        courierColor(code) {
            const map = {
                shiprocket: 'bg-brand-100 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400',
                delhivery:  'bg-error-100 text-error-700 dark:bg-error-500/15 dark:text-error-400',
                bluedart:   'bg-blue-light-100 text-blue-light-700 dark:bg-blue-light-500/15 dark:text-blue-light-400',
                dtdc:       'bg-warning-100 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
                xpressbees: 'bg-success-100 text-success-700 dark:bg-success-500/15 dark:text-success-400',
                shadowfax:  'bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-400',
            };
            return map[code] || 'bg-gray-100 text-gray-700 dark:bg-white/[0.06] dark:text-gray-300';
        },
        toggle(p) {
            this.busy = p.id;
            this.action = 'toggle';
            router.post(route('admin.couriers.toggle', p.id), {}, {
                preserveScroll: true,
                onFinish: () => { this.busy = null; this.action = null; },
            });
        },
        setDefault(p) {
            this.busy = p.id;
            this.action = 'default';
            router.post(route('admin.couriers.default', p.id), {}, {
                preserveScroll: true,
                onFinish: () => { this.busy = null; this.action = null; },
            });
        },
        test(p) {
            this.busy = p.id;
            this.action = 'test';
            router.post(route('admin.couriers.test', p.id), {}, {
                preserveScroll: true,
                onFinish: () => { this.busy = null; this.action = null; },
            });
        },
    },
};
</script>
