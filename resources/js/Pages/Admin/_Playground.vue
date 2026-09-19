<template>
    <Head title="Design system playground" />

    <PageHeader
        title="Design system playground"
        subtitle="A visual reference of every primitive. Use this to spot regressions when refactoring pages."
        :crumbs="[
            { label: 'Admin', href: route('admin.dashboard') },
            { label: 'Playground' },
        ]"
    >
        <template #titleBadge>
            <Badge variant="brand" size="sm" dot>Phase 0</Badge>
        </template>
        <template #actions>
            <Button variant="secondary" size="sm" @click="isDark = !isDark">
                <template #leading>
                    <component :is="isDark ? Sun : Moon" :size="15" />
                </template>
                {{ isDark ? 'Light' : 'Dark' }} preview
            </Button>
            <Button variant="primary" size="sm">
                <template #leading>
                    <Plus :size="15" />
                </template>
                Primary action
            </Button>
        </template>
    </PageHeader>

    <!-- Section: Typography -->
    <Section title="Typography" description="Consistent scale — use utilities, not ad-hoc size classes.">
        <Card mode="flat">
            <div class="space-y-4">
                <div>
                    <p class="text-eyebrow text-gray-500 dark:text-gray-400 mb-2">text-display</p>
                    <p class="text-display text-gray-900 dark:text-white/95">Modern SaaS admin</p>
                </div>
                <div>
                    <p class="text-eyebrow text-gray-500 dark:text-gray-400 mb-2">text-h1</p>
                    <p class="text-h1 text-gray-900 dark:text-white/95">Products</p>
                </div>
                <div>
                    <p class="text-eyebrow text-gray-500 dark:text-gray-400 mb-2">text-h2</p>
                    <p class="text-h2 text-gray-900 dark:text-white/95">Recent orders</p>
                </div>
                <div>
                    <p class="text-eyebrow text-gray-500 dark:text-gray-400 mb-2">text-h3</p>
                    <p class="text-h3 text-gray-900 dark:text-white/95">Shipping details</p>
                </div>
                <div>
                    <p class="text-eyebrow text-gray-500 dark:text-gray-400 mb-2">text-body</p>
                    <p class="text-body text-gray-700 dark:text-gray-300">
                        The quick brown fox jumps over the lazy dog. Body copy sits at 14px with generous leading.
                    </p>
                </div>
                <div>
                    <p class="text-eyebrow text-gray-500 dark:text-gray-400 mb-2">text-label / text-caption / text-eyebrow</p>
                    <div class="space-y-1">
                        <p class="text-label text-gray-800 dark:text-gray-200">Field label</p>
                        <p class="text-caption">Helper caption — smaller and lighter</p>
                        <p class="text-eyebrow text-gray-500 dark:text-gray-400">Section eyebrow</p>
                    </div>
                </div>
                <div class="pt-2 border-t border-gray-100 dark:border-white/[0.06]">
                    <p class="text-eyebrow text-gray-500 dark:text-gray-400 mb-2">Tabular numerics</p>
                    <p class="num-tabular text-h2 text-gray-900 dark:text-white/95">
                        $12,847.00 &nbsp; $ 9,102.55 &nbsp; $   340.00
                    </p>
                </div>
            </div>
        </Card>
    </Section>

    <!-- Section: Colors -->
    <Section title="Colors" description="Neutral canvas + indigo accent + semantic scales.">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <ColorSwatch label="Canvas" var="var(--color-canvas)" hex="#FAFAF9" />
            <ColorSwatch label="Ink 900 (primary)" var="var(--color-gray-900)" hex="#101828" />
            <ColorSwatch label="Brand 500 (accent)" var="var(--color-brand-500)" hex="#465FFF" />
            <ColorSwatch label="Success 500" var="var(--color-success-500)" hex="#12B76A" />
            <ColorSwatch label="Warning 500" var="var(--color-warning-500)" hex="#F79009" />
            <ColorSwatch label="Error 500" var="var(--color-error-500)" hex="#F04438" />
            <ColorSwatch label="Info 500" var="var(--color-blue-light-500)" hex="#0BA5EC" />
            <ColorSwatch label="Gray 200 (border)" var="var(--color-gray-200)" hex="#E4E7EC" />
        </div>
    </Section>

    <!-- Section: Buttons -->
    <Section title="Buttons" description="Near-black primary. Brand indigo reserved for `accent`, links and focus rings.">
        <Card mode="flat">
            <div class="space-y-6">
                <Row label="Variants">
                    <Button variant="primary">Primary</Button>
                    <Button variant="accent">Accent</Button>
                    <Button variant="secondary">Secondary</Button>
                    <Button variant="subtle">Subtle</Button>
                    <Button variant="ghost">Ghost</Button>
                    <Button variant="danger">Danger</Button>
                    <Button variant="danger-outline">Danger outline</Button>
                    <Button variant="success">Success</Button>
                    <Button variant="link">Link style</Button>
                </Row>
                <Row label="Sizes (primary)">
                    <Button variant="primary" size="xs">Extra small</Button>
                    <Button variant="primary" size="sm">Small</Button>
                    <Button variant="primary" size="md">Medium</Button>
                    <Button variant="primary" size="lg">Large</Button>
                    <Button variant="primary" size="xl">Extra large</Button>
                </Row>
                <Row label="With icons">
                    <Button variant="primary">
                        <template #leading><Plus :size="15" /></template>
                        New product
                    </Button>
                    <Button variant="secondary">
                        <template #leading><Download :size="15" /></template>
                        Export CSV
                    </Button>
                    <Button variant="ghost">
                        <template #trailing><ChevronDown :size="15" /></template>
                        Options
                    </Button>
                    <Button variant="subtle" size="sm">
                        <template #leading><Filter :size="14" /></template>
                        Filter
                    </Button>
                </Row>
                <Row label="States">
                    <Button variant="primary" loading>Saving…</Button>
                    <Button variant="primary" disabled>Disabled</Button>
                    <Button variant="secondary" disabled>Disabled</Button>
                    <Button variant="primary" block>Block button</Button>
                </Row>
            </div>
        </Card>
    </Section>

    <!-- Section: Badges -->
    <Section title="Badges" description="Soft by default. Use dots for status meaning.">
        <Card mode="flat">
            <div class="space-y-5">
                <Row label="Soft">
                    <Badge variant="neutral">Neutral</Badge>
                    <Badge variant="brand">Brand</Badge>
                    <Badge variant="success">Paid</Badge>
                    <Badge variant="warning">Pending</Badge>
                    <Badge variant="error">Cancelled</Badge>
                    <Badge variant="info">Info</Badge>
                    <Badge variant="outline">Outline</Badge>
                </Row>
                <Row label="Solid">
                    <Badge variant="neutral" solid>Neutral</Badge>
                    <Badge variant="brand" solid>Brand</Badge>
                    <Badge variant="success" solid>Success</Badge>
                    <Badge variant="warning" solid>Warning</Badge>
                    <Badge variant="error" solid>Error</Badge>
                </Row>
                <Row label="With dot">
                    <Badge variant="success" dot>Fulfilled</Badge>
                    <Badge variant="warning" dot>Awaiting payment</Badge>
                    <Badge variant="error" dot>Refunded</Badge>
                    <Badge variant="brand" dot>Draft</Badge>
                </Row>
                <Row label="Sizes / shapes">
                    <Badge variant="brand" size="sm">Small</Badge>
                    <Badge variant="brand" size="md">Medium</Badge>
                    <Badge variant="brand" size="lg">Large</Badge>
                    <Badge variant="neutral" shape="rounded">Rounded</Badge>
                </Row>
            </div>
        </Card>
    </Section>

    <!-- Section: Cards -->
    <Section title="Cards" description="`flat` for content, `raised` for lifted panels, `interactive` for clickable ones.">
        <div class="grid gap-4 md:grid-cols-3">
            <Card mode="flat" title="Flat card" subtitle="Border only, no shadow.">
                <p class="text-body text-gray-600 dark:text-gray-400">
                    The default surface. Use for most content grouping.
                </p>
            </Card>
            <Card mode="raised" title="Raised card" subtitle="Subtle shadow lift.">
                <p class="text-body text-gray-600 dark:text-gray-400">
                    Use for KPI tiles and modules that should stand off the canvas slightly.
                </p>
            </Card>
            <Card mode="interactive" title="Interactive card" subtitle="Elevates on hover.">
                <p class="text-body text-gray-600 dark:text-gray-400">
                    Use when the whole card is clickable — hover reveals affordance.
                </p>
                <template #footer>
                    <Button variant="ghost" size="sm">
                        View details
                        <template #trailing><ArrowRight :size="14" /></template>
                    </Button>
                </template>
            </Card>
        </div>
    </Section>

    <!-- Section: Form inputs -->
    <Section title="Form inputs" description="Everything opts into the same border, hover, focus, and error language.">
        <Card mode="flat">
            <div class="grid gap-5 md:grid-cols-2">
                <FormField label="Product name" required for-id="pf-name" hint="Appears on the storefront card and PDP.">
                    <Input id="pf-name" v-model="form.name" placeholder="e.g. Merino wool tee" />
                </FormField>
                <FormField label="SKU" for-id="pf-sku" optional>
                    <Input id="pf-sku" v-model="form.sku" placeholder="AUTO-GENERATED">
                        <template #leading><Hash :size="14" /></template>
                    </Input>
                </FormField>
                <FormField label="Price" required for-id="pf-price">
                    <Input id="pf-price" type="number" v-model="form.price">
                        <template #leading><span class="text-[13px]">$</span></template>
                        <template #trailing><span class="text-[12px] text-gray-400">USD</span></template>
                    </Input>
                </FormField>
                <FormField label="Category" required for-id="pf-cat">
                    <Select
                        id="pf-cat"
                        v-model="form.category"
                        placeholder="Choose a category"
                        :options="[
                            { value: 'apparel', label: 'Apparel' },
                            { value: 'accessories', label: 'Accessories' },
                            { value: 'home', label: 'Home' },
                            { value: 'gift-cards', label: 'Gift cards', disabled: true },
                        ]"
                    />
                </FormField>
                <FormField label="Tags" for-id="pf-tags">
                    <MultiSelect
                        id="pf-tags"
                        v-model="form.tags"
                        placeholder="Add tags"
                        :options="[
                            { value: 'new', label: 'New arrival' },
                            { value: 'sale', label: 'On sale' },
                            { value: 'limited', label: 'Limited edition' },
                            { value: 'seasonal', label: 'Seasonal' },
                            { value: 'bestseller', label: 'Bestseller' },
                        ]"
                    />
                </FormField>
                <FormField label="Publish date" for-id="pf-date">
                    <DatePicker id="pf-date" v-model="form.date" />
                </FormField>
                <FormField label="Description" for-id="pf-desc" class="md:col-span-2">
                    <Textarea id="pf-desc" v-model="form.desc" placeholder="Tell customers about this product…" :rows="4" />
                </FormField>
                <FormField label="Discount code" for-id="pf-err" error="This code is already taken.">
                    <Input id="pf-err" model-value="SUMMER20" error />
                </FormField>
                <FormField label="Admin password" for-id="pf-pass">
                    <PasswordInput id="pf-pass" v-model="form.pass" placeholder="Enter password" />
                </FormField>
            </div>

            <div class="mt-8 grid gap-6 md:grid-cols-2 pt-6 border-t border-gray-100 dark:border-white/[0.06]">
                <div>
                    <p class="text-eyebrow text-gray-500 dark:text-gray-400 mb-3">Checkbox</p>
                    <div class="space-y-3">
                        <Checkbox v-model="form.terms">I agree to the terms and conditions</Checkbox>
                        <Checkbox model-value>Pre-selected option</Checkbox>
                        <Checkbox :indeterminate="true">Indeterminate state</Checkbox>
                        <Checkbox disabled>Disabled option</Checkbox>
                    </div>
                </div>
                <div>
                    <p class="text-eyebrow text-gray-500 dark:text-gray-400 mb-3">Switch</p>
                    <div class="space-y-3">
                        <Switch v-model="form.notify">Email me when this ships</Switch>
                        <Switch v-model="form.sms" size="sm">Small switch</Switch>
                        <Switch model-value size="lg">Large switch (on)</Switch>
                        <Switch disabled>Disabled switch</Switch>
                    </div>
                </div>
                <div class="md:col-span-2">
                    <p class="text-eyebrow text-gray-500 dark:text-gray-400 mb-3">Radio group (card variant)</p>
                    <RadioGroup
                        v-model="form.shipping"
                        variant="card"
                        orientation="horizontal"
                        :options="[
                            { value: 'std', label: 'Standard', description: '3-5 business days' },
                            { value: 'exp', label: 'Express', description: '1-2 business days' },
                            { value: 'ovr', label: 'Overnight', description: 'Next-day delivery' },
                        ]"
                    />
                </div>
            </div>
        </Card>
    </Section>

    <!-- Section: DataTable -->
    <Section title="Data table" description="Sortable header, selectable rows, filter chips, empty state.">
        <DataTable
            :columns="tableColumns"
            :rows="tableRows"
            row-key="id"
            :sort-by.sync="sortBy"
            :sort-dir.sync="sortDir"
            :filter-chips="[
                { key: 'status', label: 'Status: Active' },
                { key: 'stock', label: 'Stock: In stock' },
            ]"
            selectable
        >
            <template #toolbar>
                <div class="flex flex-wrap items-center gap-3 flex-1">
                    <SearchInput v-model="tableSearch" placeholder="Search products…" class="max-w-xs" />
                    <Select v-model="tableStatus" :options="statusOptions" class="max-w-[160px]" />
                    <Button variant="ghost" size="sm">
                        <template #leading><Filter :size="14" /></template>
                        More filters
                    </Button>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="secondary" size="sm">
                        <template #leading><Download :size="14" /></template>
                        Export
                    </Button>
                    <Button variant="primary" size="sm">
                        <template #leading><Plus :size="14" /></template>
                        Add product
                    </Button>
                </div>
            </template>

            <template #bulkActions>
                <Button variant="ghost" size="sm">
                    <template #leading><Archive :size="14" class="text-white" /></template>
                    Archive
                </Button>
                <Button variant="danger" size="sm">
                    <template #leading><Trash2 :size="14" /></template>
                    Delete
                </Button>
            </template>

            <template #cell-product="{ row }">
                <div class="flex items-center gap-3">
                    <div class="h-9 w-9 shrink-0 rounded-md bg-gradient-to-br from-gray-100 to-gray-200 dark:from-white/[0.06] dark:to-white/[0.03] border border-gray-200 dark:border-white/[0.08]" />
                    <div class="min-w-0">
                        <div class="text-body-strong text-gray-900 dark:text-white/90 truncate">{{ row.product }}</div>
                        <div class="text-caption">SKU · {{ row.sku }}</div>
                    </div>
                </div>
            </template>

            <template #cell-status="{ row }">
                <Badge :variant="row.status === 'active' ? 'success' : row.status === 'draft' ? 'neutral' : 'warning'" dot>
                    {{ row.status === 'active' ? 'Active' : row.status === 'draft' ? 'Draft' : 'Archived' }}
                </Badge>
            </template>

            <template #cell-stock="{ row }">
                <span :class="row.stock === 0 ? 'text-error-600 dark:text-error-400 font-medium' : 'text-gray-700 dark:text-gray-300'">
                    {{ row.stock === 0 ? 'Out of stock' : `${row.stock} in stock` }}
                </span>
            </template>

            <template #rowActions>
                <div class="flex items-center justify-end gap-1">
                    <Tooltip content="Edit">
                        <Button variant="ghost" size="sm">
                            <template #leading><Pencil :size="14" /></template>
                        </Button>
                    </Tooltip>
                    <Tooltip content="Delete">
                        <Button variant="ghost" size="sm">
                            <template #leading><Trash2 :size="14" class="text-error-500" /></template>
                        </Button>
                    </Tooltip>
                </div>
            </template>

            <template #footer>
                <span class="text-caption">Showing 5 of 128</span>
                <div class="flex items-center gap-1">
                    <Button variant="ghost" size="sm" disabled>
                        <template #leading><ChevronLeft :size="14" /></template>
                    </Button>
                    <Button variant="ghost" size="sm">
                        <template #trailing><ChevronRight :size="14" /></template>
                    </Button>
                </div>
            </template>
        </DataTable>
    </Section>

    <!-- Section: Tabs -->
    <Section title="Tabs" description="Underline, pills, and segmented.">
        <div class="space-y-5">
            <Card mode="flat" padding="lg">
                <Tabs
                    v-model="activeTab"
                    :tabs="[
                        { value: 'all', label: 'All orders', badge: 128 },
                        { value: 'unfulfilled', label: 'Unfulfilled', badge: 12 },
                        { value: 'unpaid', label: 'Unpaid' },
                        { value: 'refunds', label: 'Refund requests', badge: 3 },
                    ]"
                    variant="underline"
                />
                <p class="pt-4 text-body text-gray-600 dark:text-gray-400">
                    Underline tabs — best for top-of-page navigation between related lists.
                </p>
            </Card>
            <Card mode="flat" padding="lg">
                <div class="flex flex-wrap items-center gap-4">
                    <Tabs
                        v-model="activeTab2"
                        :tabs="[
                            { value: '7d', label: '7 days' },
                            { value: '30d', label: '30 days' },
                            { value: '90d', label: '90 days' },
                        ]"
                        variant="segmented"
                        panel-class=""
                    />
                    <Tabs
                        v-model="activeTab3"
                        :tabs="[
                            { value: 'chart', label: 'Chart' },
                            { value: 'table', label: 'Table' },
                        ]"
                        variant="pills"
                        panel-class=""
                    />
                </div>
            </Card>
        </div>
    </Section>

    <!-- Section: Alerts -->
    <Section title="Alerts" description="Contextual banners with Lucide iconography.">
        <div class="grid gap-3">
            <Alert variant="info" title="Heads up" message="You've used 78% of your monthly bandwidth quota." dismissible />
            <Alert variant="success" title="Payment settled" message="₹12,847.00 has been transferred to your bank account." dismissible />
            <Alert variant="warning" title="Low stock" message="4 products are running low on inventory. Restock soon." />
            <Alert variant="error" title="Sync failed" message="Shiprocket credentials rejected. Reconnect the courier partner." dismissible />
        </div>
    </Section>

    <!-- Section: Overlays -->
    <Section title="Overlays" description="Modal, drawer, tooltip.">
        <Card mode="flat">
            <div class="flex flex-wrap items-center gap-3">
                <Button variant="secondary" @click="showModal = true">Open modal</Button>
                <Button variant="secondary" @click="showDrawer = true">Open drawer</Button>
                <Tooltip content="This is a tooltip">
                    <Button variant="ghost">Hover me</Button>
                </Tooltip>
                <Tooltip content="Bottom placement" placement="bottom">
                    <Button variant="ghost">Bottom</Button>
                </Tooltip>
            </div>
        </Card>

        <Modal v-model="showModal" title="Delete product" subtitle="This action cannot be undone.">
            <p class="text-body text-gray-600 dark:text-gray-400">
                You're about to permanently delete <strong class="text-gray-900 dark:text-white/90">Merino wool tee</strong>.
                Any pending orders referencing this SKU will remain unaffected.
            </p>
            <template #footer>
                <Button variant="secondary" @click="showModal = false">Cancel</Button>
                <Button variant="danger" @click="showModal = false">Delete product</Button>
            </template>
        </Modal>

        <Drawer v-model="showDrawer" title="Order #A-10298" subtitle="Placed on Jul 16, 2026 · Paid" size="lg">
            <div class="space-y-5">
                <div>
                    <p class="text-eyebrow text-gray-500 dark:text-gray-400 mb-2">Customer</p>
                    <p class="text-body-strong text-gray-900 dark:text-white/95">Ashley Chen</p>
                    <p class="text-body text-gray-500 dark:text-gray-400">ashley@example.com · +1 (415) 555-0132</p>
                </div>
                <div>
                    <p class="text-eyebrow text-gray-500 dark:text-gray-400 mb-2">Items</p>
                    <ul class="text-body text-gray-700 dark:text-gray-300 space-y-1">
                        <li>2 × Merino wool tee — $89.00</li>
                        <li>1 × Slim chino, olive — $69.00</li>
                    </ul>
                </div>
            </div>
            <template #footer>
                <Button variant="secondary" @click="showDrawer = false">Close</Button>
                <Button variant="primary">Fulfill</Button>
            </template>
        </Drawer>
    </Section>

    <!-- Section: Empty & loading -->
    <Section title="Empty & loading states" description="Consistent affordances for zero-data and pending views.">
        <div class="grid gap-4 md:grid-cols-2">
            <Card mode="flat" padding="none">
                <EmptyState
                    title="No products yet"
                    description="Add your first product to start selling. You'll be able to manage inventory, variants, and media from here."
                >
                    <template #actions>
                        <Button variant="primary" size="sm">
                            <template #leading><Plus :size="14" /></template>
                            Add product
                        </Button>
                        <Button variant="ghost" size="sm">Import CSV</Button>
                    </template>
                </EmptyState>
            </Card>
            <Card mode="flat" padding="none">
                <SkeletonTable :columns="4" :rows="5" />
            </Card>
        </div>
        <Card mode="flat" title="Skeleton primitives" class="mt-4">
            <div class="space-y-3">
                <div class="flex items-center gap-3">
                    <Skeleton :width="40" :height="40" circle />
                    <div class="flex-1 space-y-1.5">
                        <Skeleton :width="'40%'" :height="12" />
                        <Skeleton :width="'70%'" :height="10" />
                    </div>
                </div>
                <Skeleton :width="'90%'" :height="10" />
                <Skeleton :width="'85%'" :height="10" />
                <Skeleton :width="'60%'" :height="10" />
            </div>
        </Card>
    </Section>
</template>

<script>
import { Head } from '@inertiajs/vue3';
import MainLayout from '../../Layout/MainLayout.vue';
import {
    PageHeader, Button, Badge, Card, Input, Textarea, Select, MultiSelect,
    DatePicker, Switch, Checkbox, RadioGroup, FormField, Alert,
    EmptyState, SearchInput, PasswordInput, Skeleton, SkeletonTable,
    Modal, Drawer, Tabs, Tooltip, DataTable,
} from '@/Components/ui';
import {
    Plus, Download, ChevronDown, ChevronLeft, ChevronRight, Filter,
    Archive, Trash2, Pencil, ArrowRight, Sun, Moon, Hash,
} from '@lucide/vue';

// Small inline helpers for the playground layout only.
const Section = {
    props: { title: String, description: String },
    template: `
        <section class="mb-10">
            <div class="mb-3">
                <h2 class="text-h2 text-gray-900 dark:text-white/95">{{ title }}</h2>
                <p v-if="description" class="mt-0.5 text-body text-gray-500 dark:text-gray-400">{{ description }}</p>
            </div>
            <slot />
        </section>
    `,
};
const Row = {
    props: { label: String },
    template: `
        <div class="grid gap-3 md:grid-cols-[140px_1fr] md:items-start">
            <div class="text-eyebrow text-gray-500 dark:text-gray-400 pt-2">{{ label }}</div>
            <div class="flex flex-wrap items-center gap-3">
                <slot />
            </div>
        </div>
    `,
};
const ColorSwatch = {
    props: { label: String, var: String, hex: String },
    template: `
        <div class="rounded-xl border border-gray-200 dark:border-white/[0.06] overflow-hidden bg-white dark:bg-[color:var(--color-surface-dark)]">
            <div class="h-16" :style="{ background: $props.var }"></div>
            <div class="px-3 py-2">
                <div class="text-body-strong text-gray-900 dark:text-white/95">{{ label }}</div>
                <div class="text-caption num-tabular">{{ hex }}</div>
            </div>
        </div>
    `,
};

export default {
    layout: MainLayout,
    components: {
        Head, PageHeader, Button, Badge, Card, Input, Textarea, Select, MultiSelect,
        DatePicker, Switch, Checkbox, RadioGroup, FormField, Alert,
        EmptyState, SearchInput, PasswordInput, Skeleton, SkeletonTable,
        Modal, Drawer, Tabs, Tooltip, DataTable,
        Section, Row, ColorSwatch,
    },
    setup() {
        return { Plus, Download, ChevronDown, ChevronLeft, ChevronRight, Filter, Archive, Trash2, Pencil, ArrowRight, Sun, Moon, Hash };
    },
    data() {
        return {
            isDark: false,
            showModal: false,
            showDrawer: false,
            activeTab: 'all',
            activeTab2: '30d',
            activeTab3: 'chart',
            sortBy: 'product',
            sortDir: 'asc',
            tableSearch: '',
            tableStatus: 'active',
            statusOptions: [
                { value: 'active', label: 'All statuses' },
                { value: 'draft', label: 'Draft' },
                { value: 'archived', label: 'Archived' },
            ],
            form: {
                name: '',
                sku: '',
                price: '89.00',
                category: 'apparel',
                tags: ['new', 'bestseller'],
                date: null,
                desc: '',
                pass: '',
                terms: false,
                notify: true,
                sms: false,
                shipping: 'exp',
            },
            tableColumns: [
                { key: 'product', label: 'Product', sortable: true },
                { key: 'status', label: 'Status' },
                { key: 'stock', label: 'Inventory' },
                { key: 'price', label: 'Price', align: 'right', sortable: true, format: (v) => `$${Number(v).toFixed(2)}` },
            ],
            tableRows: [
                { id: 1, product: 'Merino wool tee', sku: 'MWT-001', status: 'active', stock: 128, price: 89 },
                { id: 2, product: 'Slim chino, olive', sku: 'SCO-032', status: 'active', stock: 47, price: 69 },
                { id: 3, product: 'Canvas weekender', sku: 'CWK-118', status: 'draft', stock: 0, price: 149 },
                { id: 4, product: 'Leather passport wallet', sku: 'LPW-004', status: 'archived', stock: 12, price: 45 },
                { id: 5, product: 'Cashmere beanie', sku: 'CBM-021', status: 'active', stock: 8, price: 39 },
            ],
        };
    },
    watch: {
        isDark(v) {
            if (typeof document !== 'undefined') {
                document.documentElement.classList.toggle('dark', v);
            }
        },
    },
};
</script>
