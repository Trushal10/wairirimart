<template>
    <Head :title="`Configure ${gateway.name}`" />

    <SettingsShell
        :title="`Configure ${gateway.name}`"
        subtitle="Credentials are encrypted at rest. Leave a field blank to keep the existing value."
        :crumbs="crumbs"
    >
        <template #titleBadge>
            <Badge :variant="form.mode === 'live' ? 'success' : 'warning'" size="sm" dot>
                {{ form.mode === 'live' ? 'Live' : 'Test' }}
            </Badge>
        </template>
        <template #actions>
            <Button variant="secondary" size="sm" tag="a" :href="route('admin.payment_gateways.index')">
                <template #leading><ArrowLeft :size="14" /></template>
                Back
            </Button>
            <Button variant="ghost" size="sm" :loading="testing" @click="test">
                <template #leading><Zap :size="14" /></template>
                Test
            </Button>
            <Button variant="primary" size="sm" :loading="submitting" @click="submit">
                Save changes
            </Button>
        </template>

        <form @submit.prevent="submit" class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <!-- Basics -->
                <Card mode="flat" title="Basic information">
                    <div class="space-y-5">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <FormField label="Display name" required :error="errors?.name">
                                <Input v-model="form.name" required maxlength="100" :error="!!errors?.name" />
                            </FormField>
                            <FormField label="Provider code" hint="Fixed — matches the adapter.">
                                <Input :model-value="gateway.code" disabled class="font-mono" />
                            </FormField>
                        </div>
                        <FormField label="Description" hint="Optional — displayed to admins only.">
                            <Textarea v-model="form.description" maxlength="500" :rows="2" placeholder="What this gateway is used for…" />
                        </FormField>
                    </div>
                </Card>

                <!-- Mode & priority -->
                <Card mode="flat" title="Mode & priority" subtitle="Test mode routes transactions to the provider's sandbox.">
                    <div class="space-y-5">
                        <FormField label="Environment">
                            <div class="inline-flex rounded-lg border border-gray-200 dark:border-white/[0.08] p-1 bg-gray-50 dark:bg-white/[0.03]">
                                <button
                                    type="button"
                                    v-for="opt in modeOptions"
                                    :key="opt.value"
                                    @click="form.mode = opt.value"
                                    :class="[
                                        'px-3.5 py-1.5 rounded-md text-[13px] font-medium transition-colors focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/25',
                                        form.mode === opt.value
                                            ? 'bg-white dark:bg-[color:var(--color-surface-dark)] text-gray-900 dark:text-white/95 shadow-theme-xs'
                                            : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white/95',
                                    ]"
                                >
                                    <span class="inline-flex items-center gap-1.5">
                                        <span
                                            class="h-1.5 w-1.5 rounded-full"
                                            :class="opt.value === 'live' ? 'bg-success-500' : 'bg-warning-500'"
                                        ></span>
                                        {{ opt.label }}
                                    </span>
                                </button>
                            </div>
                            <p class="mt-2 text-[12px] text-gray-500 dark:text-gray-400">
                                <template v-if="form.mode === 'live'">
                                    <span class="text-success-700 dark:text-success-400 font-semibold">Live mode</span> — real transactions.
                                </template>
                                <template v-else>
                                    <span class="text-warning-700 dark:text-warning-400 font-semibold">Test mode</span> — safe for development. No real money changes hands.
                                </template>
                            </p>
                        </FormField>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <FormField label="Display priority" hint="Higher priority appears first at checkout.">
                                <Input v-model.number="form.priority" type="number" min="0" max="255" placeholder="0" />
                            </FormField>
                            <FormField label="Availability">
                                <div class="space-y-3 pt-1">
                                    <Switch v-model="form.is_active">
                                        <span class="font-medium text-gray-900 dark:text-white/95">Enabled</span>
                                        <span class="block text-[12px] text-gray-500 dark:text-gray-400">Show at checkout.</span>
                                    </Switch>
                                    <Switch v-model="form.is_default">
                                        <span class="font-medium text-gray-900 dark:text-white/95">Default gateway</span>
                                        <span class="block text-[12px] text-gray-500 dark:text-gray-400">Pre-selected at checkout.</span>
                                    </Switch>
                                </div>
                            </FormField>
                        </div>
                    </div>
                </Card>

                <!-- API credentials -->
                <Card
                    v-if="Object.keys(schema || {}).length"
                    mode="flat"
                    title="API credentials"
                    subtitle="Leaving a secret blank keeps its existing value."
                >
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <FormField
                            v-for="(meta, field) in schema"
                            :key="field"
                            :label="meta.label"
                            :required="!!meta.required"
                            :hint="meta.help"
                            :error="errors?.[`credentials.${field}`]"
                        >
                            <div v-if="meta.type === 'password'" class="space-y-1.5">
                                <PasswordInput
                                    v-model="form.credentials[field]"
                                    :placeholder="gateway.credentials_masked?.[field] || (meta.required ? 'Required' : 'Optional')"
                                    :error="!!errors?.[`credentials.${field}`]"
                                />
                                <button
                                    v-if="gateway.credentials_masked?.[field] && !revealed[field]"
                                    type="button"
                                    class="text-[11.5px] text-primary-600 hover:underline disabled:opacity-50 dark:text-primary-400"
                                    :disabled="revealing === field"
                                    @click="reveal(field)"
                                >
                                    {{ revealing === field ? 'Revealing…' : 'Show saved value' }}
                                </button>
                                <p v-else-if="revealed[field]" class="text-[11.5px] text-warning-600 dark:text-warning-400">
                                    Saved value shown — this reveal was recorded in the audit log.
                                </p>
                            </div>
                            <Input
                                v-else
                                v-model="form.credentials[field]"
                                :placeholder="meta.required ? 'Required' : 'Optional'"
                                :error="!!errors?.[`credentials.${field}`]"
                                autocomplete="new-password"
                            />
                        </FormField>
                    </div>
                </Card>
            </div>

            <!-- Sidebar -->
            <aside class="space-y-6 xl:col-span-1">
                <Card mode="flat" title="Status">
                    <dl class="space-y-2.5 text-[13px]">
                        <div class="flex items-center justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Provider</dt>
                            <dd class="font-mono text-gray-900 dark:text-white/95">{{ gateway.code }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Mode</dt>
                            <dd>
                                <Badge :variant="form.mode === 'live' ? 'success' : 'warning'" size="sm" dot>
                                    {{ form.mode === 'live' ? 'Live' : 'Test' }}
                                </Badge>
                            </dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Enabled</dt>
                            <dd>
                                <Badge :variant="form.is_active ? 'success' : 'neutral'" dot size="sm">
                                    {{ form.is_active ? 'Yes' : 'No' }}
                                </Badge>
                            </dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Default</dt>
                            <dd>
                                <Badge :variant="form.is_default ? 'neutral' : 'neutral'" :solid="form.is_default" size="sm">
                                    {{ form.is_default ? 'Yes' : 'No' }}
                                </Badge>
                            </dd>
                        </div>
                        <div v-if="supports?.length" class="pt-2 border-t border-gray-100 dark:border-white/[0.06]">
                            <dt class="text-eyebrow text-gray-500 dark:text-gray-400 mb-2">Supports</dt>
                            <dd class="flex flex-wrap gap-1">
                                <Badge v-for="s in supports" :key="s" size="sm" variant="neutral">{{ s }}</Badge>
                            </dd>
                        </div>
                    </dl>
                </Card>

                <Card mode="flat" title="Security">
                    <ul class="space-y-2.5 text-[12.5px] text-gray-600 dark:text-gray-400">
                        <li class="flex items-start gap-2">
                            <ShieldCheck :size="14" class="mt-0.5 shrink-0 text-success-600 dark:text-success-400" />
                            <span>Credentials are encrypted at rest using the app key.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <ShieldCheck :size="14" class="mt-0.5 shrink-0 text-success-600 dark:text-success-400" />
                            <span>Secrets are masked and only sent to the browser when revealed.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <ShieldCheck :size="14" class="mt-0.5 shrink-0 text-success-600 dark:text-success-400" />
                            <span>All changes are recorded in the admin audit log.</span>
                        </li>
                    </ul>
                </Card>
            </aside>
        </form>
    </SettingsShell>
</template>

<script>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import Layout from '@/Layout/MainLayout.vue';
import SettingsShell from '@/Components/SettingsShell.vue';
import { Card, Button, Badge, Input, Textarea, Switch, FormField, PasswordInput } from '@/Components/ui';
import { ArrowLeft, Zap, ShieldCheck } from '@lucide/vue';

export default {
    layout: Layout,
    components: {
        Head, SettingsShell,
        Card, Button, Badge, Input, Textarea, Switch, FormField, PasswordInput,
        ArrowLeft, Zap, ShieldCheck,
    },
    props: {
        gateway: { type: Object, required: true },
        schema: { type: Object, default: () => ({}) },
        supports: { type: Array, default: () => [] },
    },
    data() {
        // Non-secret fields (a publishable key ID, an account slug) come back
        // in full so the admin can see what is configured and edit it in place.
        // Secrets are never sent to the browser, so they start blank and an
        // empty submit leaves the stored value untouched.
        const initialCreds = {};
        Object.entries(this.schema || {}).forEach(([k, meta]) => {
            initialCreds[k] = meta.type === 'password'
                ? ''
                : (this.gateway.credentials_public?.[k] ?? '');
        });
        return {
            crumbs: [
                { label: 'Dashboard', href: route('admin.dashboard') },
                { label: 'Settings' },
                { label: 'Payment gateways', href: route('admin.payment_gateways.index') },
                { label: this.gateway.name },
            ],
            modeOptions: [
                { value: 'test', label: 'Test / Sandbox' },
                { value: 'live', label: 'Live' },
            ],
            form: useForm({
                name: this.gateway.name || '',
                description: this.gateway.description || '',
                mode: this.gateway.mode || 'test',
                is_active: !!this.gateway.is_active,
                is_default: !!this.gateway.is_default,
                priority: this.gateway.priority ?? 0,
                credentials: initialCreds,
                config: this.gateway.config || {},
            }),
            submitting: false,
            testing: false,
            revealing: null,
            revealed: {},
        };
    },
    computed: {
        errors() { return usePage().props.errors || {}; },
    },
    methods: {
        submit() {
            this.submitting = true;
            this.form.put(route('admin.payment_gateways.update', this.gateway.id), {
                preserveScroll: true,
                onFinish: () => { this.submitting = false; },
            });
        },
        // Secrets are not in the page payload, so fetch the one that was asked
        // for. The server audits the call; dropping it into the input means an
        // unchanged submit round-trips the same value.
        async reveal(field) {
            this.revealing = field;
            try {
                const { data } = await window.axios.post(
                    route('admin.payment_gateways.reveal', this.gateway.id),
                    { field },
                );
                this.form.credentials[field] = data.value;
                this.revealed = { ...this.revealed, [field]: true };
            } catch (e) {
                window.alert(e?.response?.data?.message || 'Could not reveal that credential.');
            } finally {
                this.revealing = null;
            }
        },
        test() {
            this.testing = true;
            router.post(route('admin.payment_gateways.test', this.gateway.id), {}, {
                preserveScroll: true,
                onFinish: () => { this.testing = false; },
            });
        },
    },
};
</script>
