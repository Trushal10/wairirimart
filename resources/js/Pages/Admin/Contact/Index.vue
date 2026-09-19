<template>
    <Head title="Contact messages" />

    <PageHeader
        title="Contact messages"
        subtitle="Inquiries submitted through the public contact form."
        :crumbs="crumbs"
    >
        <template #actions>
            <Badge variant="brand" size="md">
                <Mail :size="12" class="mr-1" />
                <span class="num-tabular">{{ contacts.total ?? contacts.data.length }}</span>
                <span class="ml-1">total</span>
            </Badge>
        </template>
    </PageHeader>

    <DataTable
        :columns="columns"
        :rows="contacts.data"
        row-key="id"
        :filter-chips="chips"
        :empty-text="hasFilters ? 'No messages match your search.' : 'No contact messages yet.'"
        @remove-filter="onRemoveChip"
        @clear-filters="clearFilters"
        @row-click="openMessage"
    >
        <template #toolbar>
            <div class="flex flex-wrap items-center gap-2 flex-1">
                <SearchInput v-model="search" placeholder="Name, email, subject, message…" class="max-w-md" />
            </div>
            <span class="text-[12px] text-gray-500 dark:text-gray-400">
                <span class="num-tabular font-semibold text-gray-800 dark:text-white/90">{{ contacts.total ?? contacts.data.length }}</span>
                {{ (contacts.total ?? contacts.data.length) === 1 ? 'message' : 'messages' }}
            </span>
        </template>

        <template #cell-sender="{ row }">
            <div class="flex items-center gap-2.5 min-w-0">
                <span
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-white text-[11px] font-semibold"
                    :style="{ background: avatarColor(row.name) }"
                    aria-hidden="true"
                >{{ initials(row.name) }}</span>
                <div class="min-w-0">
                    <div class="text-body-strong text-gray-900 dark:text-white/95 truncate">{{ row.name || '—' }}</div>
                    <div v-if="row.email" class="text-[11.5px] text-gray-500 dark:text-gray-400 truncate">
                        {{ row.email }}
                    </div>
                </div>
            </div>
        </template>

        <template #cell-phone="{ row }">
            <span v-if="row.phone" class="num-tabular text-[13px] text-gray-700 dark:text-gray-300">{{ row.phone }}</span>
            <span v-else class="text-[12px] text-gray-400 dark:text-gray-500 italic">—</span>
        </template>

        <template #cell-city="{ row }">
            <span v-if="row.city" class="text-[13px] text-gray-700 dark:text-gray-300">{{ row.city }}</span>
            <span v-else class="text-[12px] text-gray-400 dark:text-gray-500 italic">—</span>
        </template>

        <template #cell-subject="{ row }">
            <div class="min-w-0">
                <div class="text-body text-gray-900 dark:text-white/95 truncate">{{ row.subject || '—' }}</div>
                <div v-if="row.message" class="mt-0.5 text-[11.5px] text-gray-500 dark:text-gray-400 truncate">
                    {{ row.message }}
                </div>
            </div>
        </template>

        <template #cell-received="{ row }">
            <span class="text-[12.5px] text-gray-600 dark:text-gray-400 num-tabular">
                {{ formatDate(row.created_at) }}
            </span>
        </template>

        <template #rowActions="{ row }">
            <div class="flex items-center justify-end gap-0.5">
                <Tooltip content="View message">
                    <button
                        type="button"
                        @click.stop="openMessage(row)"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/[0.08] dark:hover:text-white/95 transition-colors focus-ring"
                        :aria-label="`Open message from ${row.name}`"
                    >
                        <Eye :size="14" />
                    </button>
                </Tooltip>
                <Tooltip v-if="row.email" content="Reply by email">
                    <a
                        :href="mailtoUrl(row)"
                        @click.stop
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/[0.08] dark:hover:text-white/95 transition-colors focus-ring"
                        aria-label="Reply by email"
                    >
                        <Reply :size="14" />
                    </a>
                </Tooltip>
                <Tooltip content="Delete">
                    <button
                        type="button"
                        @click.stop="askDelete(row)"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-500 hover:bg-error-50 hover:text-error-600 dark:text-gray-400 dark:hover:bg-error-500/10 dark:hover:text-error-400 transition-colors focus-ring"
                        :aria-label="`Delete message from ${row.name}`"
                    >
                        <Trash2 :size="14" />
                    </button>
                </Tooltip>
            </div>
        </template>

        <template #footer>
            <Pagination :links="contacts.links" />
        </template>
    </DataTable>

    <!-- Message drawer -->
    <Drawer
        v-model="drawerOpen"
        :title="active?.subject || 'Message'"
        :subtitle="active ? `Received ${formatDate(active.created_at, true)}` : ''"
        size="lg"
    >
        <div v-if="active" class="space-y-6">
            <!-- Sender card -->
            <div class="rounded-xl border border-gray-200 dark:border-white/[0.06] p-4">
                <div class="flex items-start gap-3">
                    <span
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-white text-[13px] font-semibold"
                        :style="{ background: avatarColor(active.name) }"
                        aria-hidden="true"
                    >{{ initials(active.name) }}</span>
                    <div class="min-w-0 flex-1">
                        <div class="text-body-strong text-gray-900 dark:text-white/95 truncate">{{ active.name }}</div>
                        <div v-if="active.email" class="text-[12.5px] text-gray-500 dark:text-gray-400 truncate">
                            {{ active.email }}
                        </div>
                    </div>
                </div>
                <dl class="mt-4 grid gap-x-6 gap-y-2 sm:grid-cols-2 text-[13px]">
                    <div v-if="active.phone" class="flex items-center gap-2">
                        <Phone :size="13" class="text-gray-400 shrink-0" />
                        <dt class="sr-only">Phone</dt>
                        <dd class="num-tabular text-gray-700 dark:text-gray-300">{{ active.phone }}</dd>
                    </div>
                    <div v-if="active.city" class="flex items-center gap-2">
                        <MapPin :size="13" class="text-gray-400 shrink-0" />
                        <dt class="sr-only">City</dt>
                        <dd class="text-gray-700 dark:text-gray-300">{{ active.city }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Message body -->
            <div>
                <div class="text-eyebrow text-gray-500 dark:text-gray-400 mb-2">Message</div>
                <div class="rounded-xl bg-gray-50 dark:bg-white/[0.03] border border-gray-100 dark:border-white/[0.06] p-4">
                    <p class="text-[13.5px] leading-6 text-gray-800 dark:text-gray-200 whitespace-pre-line">
                        {{ active.message || '—' }}
                    </p>
                </div>
            </div>
        </div>

        <template #footer>
            <Button variant="danger-outline" size="sm" @click="askDelete(active)">
                <template #leading><Trash2 :size="14" /></template>
                Delete
            </Button>
            <Button variant="secondary" size="sm" @click="drawerOpen = false">Close</Button>
            <Button
                v-if="active?.email"
                variant="primary"
                size="sm"
                tag="a"
                :href="mailtoUrl(active)"
            >
                <template #leading><Reply :size="14" /></template>
                Reply
            </Button>
        </template>
    </Drawer>

    <DeleteAlert
        v-if="pendingDelete"
        @confirmDelete="confirmDelete"
        @cancelDelete="cancelDelete"
        title="Delete message"
        :message="`Delete message from “${pendingDelete.name}”? This cannot be undone.`"
    />
</template>

<script>
import Layout from '@/Layout/MainLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import DeleteAlert from '@/Components/common/DeleteAlert.vue';
import Pagination from '@/Components/common/Pagination.vue';
import {
    PageHeader, Button, Badge, DataTable, SearchInput, Tooltip, Drawer,
} from '@/Components/ui';
import { Eye, Reply, Trash2, Mail, Phone, MapPin } from '@lucide/vue';

const AVATAR_COLORS = ['#3641F5', '#12B76A', '#F79009', '#0BA5EC', '#7A5AF8', '#EE46BC', '#F04438'];
function hashString(s = '') {
    let h = 0;
    for (let i = 0; i < s.length; i++) h = ((h << 5) - h + s.charCodeAt(i)) | 0;
    return Math.abs(h);
}

export default {
    layout: Layout,
    components: {
        Head, DeleteAlert, Pagination,
        PageHeader, Button, Badge, DataTable, SearchInput, Tooltip, Drawer,
        Eye, Reply, Trash2, Mail, Phone, MapPin,
    },
    props: {
        contacts: { type: Object, default: () => ({ data: [], links: [] }) },
        filters: { type: Object, default: () => ({}) },
    },
    data() {
        return {
            crumbs: [
                { label: 'Dashboard', href: route('admin.dashboard') },
                { label: 'Contacts' },
            ],
            columns: [
                { key: 'sender', label: 'Sender' },
                { key: 'phone', label: 'Phone', headerClass: 'w-40' },
                { key: 'city', label: 'City', headerClass: 'w-32' },
                { key: 'subject', label: 'Subject / message', wrap: true },
                { key: 'received', label: 'Received', headerClass: 'w-32' },
            ],
            search: this.filters?.search || '',
            searchTimer: null,
            pendingDelete: null,
            drawerOpen: false,
            active: null,
        };
    },
    computed: {
        hasFilters() { return !!this.search; },
        chips() {
            return this.search ? [{ key: 'search', label: `“${this.search}”` }] : [];
        },
    },
    watch: {
        search() {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => {
                router.get(route('admin.contacts'), {
                    search: this.search || undefined,
                }, { preserveState: true, preserveScroll: true, replace: true });
            }, 400);
        },
    },
    beforeUnmount() {
        if (this.searchTimer) clearTimeout(this.searchTimer);
    },
    methods: {
        clearFilters() { this.search = ''; },
        onRemoveChip(key) { if (key === 'search') this.search = ''; },
        formatDate(v, withTime = false) {
            if (!v) return '—';
            const d = new Date(v);
            if (Number.isNaN(d.getTime())) return v;
            return d.toLocaleString('en-IN', withTime
                ? { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }
                : { day: '2-digit', month: 'short', year: 'numeric' });
        },
        openMessage(row) {
            this.active = row;
            this.drawerOpen = true;
        },
        askDelete(row) {
            this.pendingDelete = row;
            this.drawerOpen = false;
        },
        confirmDelete() {
            if (this.pendingDelete) {
                router.delete(route('admin.contact.delete', this.pendingDelete.id), {
                    preserveScroll: true,
                });
            }
            this.pendingDelete = null;
        },
        cancelDelete() { this.pendingDelete = null; },
        mailtoUrl(row) {
            if (!row?.email) return '#';
            const subj = row.subject ? `Re: ${row.subject}` : 'Regarding your message';
            return `mailto:${row.email}?subject=${encodeURIComponent(subj)}`;
        },
        initials(name) {
            const n = (name || '').trim();
            if (!n) return '·';
            const parts = n.split(/\s+/).filter(Boolean);
            const first = parts[0]?.[0] || '';
            const last = parts.length > 1 ? parts[parts.length - 1][0] : '';
            return (first + last).toUpperCase() || first.toUpperCase();
        },
        avatarColor(name) {
            return AVATAR_COLORS[hashString(name || '') % AVATAR_COLORS.length];
        },
    },
};
</script>
