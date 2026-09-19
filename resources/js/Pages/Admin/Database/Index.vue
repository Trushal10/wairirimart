<template>
    <Head title="Database backup &amp; reset" />

    <PageHeader
        title="Database"
        subtitle="Download a SQL backup of your store, review past backups, or reset the database to a fresh state."
        :crumbs="crumbs"
    />

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <!-- Main column -->
        <div class="xl:col-span-2 space-y-6">
            <Card mode="flat" title="Backup" subtitle="Runs mysqldump on your live database and downloads the .sql file.">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="text-[13px] text-gray-600 dark:text-gray-400">
                        Backups also land in
                        <code class="font-mono text-[12px] px-1 py-0.5 rounded bg-gray-100 dark:bg-white/[0.06]">storage/backups/</code>
                        on the server so you can re-download them below.
                    </div>
                    <Button variant="primary" size="sm" :loading="backupBusy" tag="a" :href="route('admin.database.backup')">
                        <template #leading><Download :size="14" /></template>
                        Download backup
                    </Button>
                </div>
            </Card>

            <Card mode="flat" title="Recent backups" subtitle="Latest 20 files in storage/backups/.">
                <div v-if="!backups.length" class="py-8 text-center text-[13px] text-gray-500 dark:text-gray-400">
                    No backups on disk yet.
                </div>
                <table v-else class="w-full text-[13px]">
                    <thead>
                        <tr class="text-left text-[11.5px] uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-white/[0.06]">
                            <th class="py-2 pr-4">File</th>
                            <th class="py-2 pr-4">Size</th>
                            <th class="py-2 pr-4">Created</th>
                            <th class="py-2 text-right"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="b in backups" :key="b.name" class="border-b border-gray-100 dark:border-white/[0.04]">
                            <td class="py-2.5 pr-4 font-mono text-[12px] text-gray-900 dark:text-white/95 truncate">{{ b.name }}</td>
                            <td class="py-2.5 pr-4 num-tabular text-gray-600 dark:text-gray-400">{{ b.size_kb.toFixed(1) }} KB</td>
                            <td class="py-2.5 pr-4 num-tabular text-gray-600 dark:text-gray-400">{{ relTime(b.created_at) }}</td>
                            <td class="py-2.5 text-right">
                                <a :href="route('admin.database.download', b.name)"
                                    class="inline-flex items-center gap-1 text-[12px] font-medium text-brand-600 dark:text-brand-400 hover:underline">
                                    <Download :size="12" /> Download
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </Card>

            <!-- Danger zone -->
            <div class="rounded-xl border-2 border-error-200 dark:border-error-500/30 bg-error-50/40 dark:bg-error-500/[0.04] p-5">
                <div class="flex items-start gap-3">
                    <div class="shrink-0 mt-0.5 h-8 w-8 rounded-lg bg-error-100 dark:bg-error-500/10 flex items-center justify-center text-error-600 dark:text-error-400">
                        <AlertTriangle :size="16" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-body-strong text-error-700 dark:text-error-300 mb-1">Clear database (full reset)</h3>
                        <p class="text-[13px] text-error-700/80 dark:text-error-300/80 mb-4">
                            Drops every table and re-runs all migrations. <strong>All customers, orders, products,
                            categories, settings, sliders, blogs, coupons, wishlists — everything — will be permanently
                            deleted.</strong> A safety backup is auto-created before the reset runs; your admin login is
                            preserved. There is no undo.
                        </p>

                        <label class="block text-[12.5px] font-medium text-error-700 dark:text-error-300 mb-1.5">
                            Type <span class="font-mono font-bold">{{ confirm_phrase }}</span> to confirm
                        </label>
                        <input
                            v-model="clearConfirm"
                            type="text"
                            class="w-full max-w-sm px-3 py-2 rounded-md border border-error-300 dark:border-error-500/40 bg-white dark:bg-[color:var(--color-surface-dark)] font-mono text-[13px] focus:outline-none focus:ring-2 focus:ring-error-500 focus:border-error-500"
                            :placeholder="confirm_phrase"
                            autocomplete="off"
                            spellcheck="false"
                        />

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <Button
                                variant="danger"
                                size="sm"
                                :disabled="!canClear"
                                :loading="clearBusy"
                                @click="performClear"
                            >
                                <template #leading><Trash2 :size="14" /></template>
                                Clear database now
                            </Button>
                            <span class="text-[12px] text-error-600/80 dark:text-error-400/80">
                                Auto-backup will save to <code class="font-mono">storage/backups/pre-clear-*.sql</code>.
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar column -->
        <aside class="space-y-6 xl:col-span-1">
            <Card mode="flat" title="Connection">
                <dl class="text-[13px] space-y-3">
                    <div>
                        <dt class="text-eyebrow text-gray-500 dark:text-gray-400">Driver</dt>
                        <dd class="mt-0.5 text-gray-900 dark:text-white/95 capitalize font-mono">{{ db.driver }}</dd>
                    </div>
                    <div>
                        <dt class="text-eyebrow text-gray-500 dark:text-gray-400">Database</dt>
                        <dd class="mt-0.5 text-gray-900 dark:text-white/95 font-mono">{{ db.database }}</dd>
                    </div>
                    <div>
                        <dt class="text-eyebrow text-gray-500 dark:text-gray-400">Host</dt>
                        <dd class="mt-0.5 text-gray-900 dark:text-white/95 font-mono">{{ db.host }}:{{ db.port }}</dd>
                    </div>
                    <div>
                        <dt class="text-eyebrow text-gray-500 dark:text-gray-400">Tables</dt>
                        <dd class="mt-0.5 text-gray-900 dark:text-white/95 num-tabular">{{ db.table_count }}</dd>
                    </div>
                    <div v-if="db.size_mb !== null">
                        <dt class="text-eyebrow text-gray-500 dark:text-gray-400">Size on disk</dt>
                        <dd class="mt-0.5 text-gray-900 dark:text-white/95 num-tabular">{{ db.size_mb }} MB</dd>
                    </div>
                </dl>
            </Card>

            <Card mode="flat" title="What gets reset">
                <ul class="text-[12.5px] space-y-1.5 list-disc pl-4 text-gray-600 dark:text-gray-400">
                    <li>All catalog data (products, categories, variants, media, sliders)</li>
                    <li>All customer data (accounts, addresses, wishlists, reviews)</li>
                    <li>All commerce data (orders, payments, shipments, returns, coupons)</li>
                    <li>All content (blogs, contact messages)</li>
                    <li>Store settings and payment/courier gateway configs</li>
                </ul>
                <div class="mt-3 pt-3 border-t border-gray-200 dark:border-white/[0.06] text-[12.5px] text-success-700 dark:text-success-400">
                    <strong>Preserved:</strong> your admin login (so you're not locked out).
                </div>
            </Card>
        </aside>
    </div>
</template>

<script>
import { Head, router } from '@inertiajs/vue3';
import MainLayout from '../../../Layout/MainLayout.vue';
import { PageHeader, Card, Button } from '@/Components/ui';
import { Download, Trash2, AlertTriangle } from '@lucide/vue';

export default {
    layout: MainLayout,
    components: {
        Head,
        PageHeader, Card, Button,
        Download, Trash2, AlertTriangle,
    },
    props: {
        db: Object,
        backups: Array,
        confirm_phrase: String,
    },
    data() {
        return {
            crumbs: [
                { label: 'Dashboard', href: route('admin.dashboard') },
                { label: 'Database' },
            ],
            clearConfirm: '',
            backupBusy: false,
            clearBusy: false,
        };
    },
    computed: {
        canClear() {
            return this.clearConfirm === this.confirm_phrase && !this.clearBusy;
        },
    },
    methods: {
        relTime(iso) {
            if (!iso) return '—';
            const secs = (Date.now() - new Date(iso).getTime()) / 1000;
            if (secs < 60) return 'just now';
            if (secs < 3600) return Math.floor(secs / 60) + 'm ago';
            if (secs < 86400) return Math.floor(secs / 3600) + 'h ago';
            if (secs < 2592000) return Math.floor(secs / 86400) + 'd ago';
            return new Date(iso).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
        },
        performClear() {
            if (!this.canClear) return;
            if (!window.confirm('Last chance. This deletes ALL data. Continue?')) return;
            this.clearBusy = true;
            router.post(route('admin.database.clear'), { confirm: this.clearConfirm }, {
                preserveScroll: true,
                onFinish: () => {
                    this.clearBusy = false;
                    this.clearConfirm = '';
                },
            });
        },
    },
};
</script>
