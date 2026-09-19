<template>
    <Head :title="isEdit ? 'Edit product' : 'New product'" />

    <PageHeader
        :title="isEdit ? product.name : 'New product'"
        :subtitle="isEdit ? 'Update product details, pricing, media, and SEO.' : 'Add a product to your catalog. Enable variants for multiple SKUs.'"
        :crumbs="crumbs"
    >
        <template #titleBadge>
            <Badge :variant="form.status ? 'success' : 'neutral'" size="sm" dot>
                {{ form.status ? 'Active' : 'Draft' }}
            </Badge>
        </template>
        <template #actions>
            <Button variant="secondary" size="sm" tag="a" :href="route('admin.products')">Cancel</Button>
            <Button variant="primary" size="sm" :loading="form.processing" @click="submitForm">
                {{ isEdit ? 'Save changes' : 'Publish product' }}
            </Button>
        </template>
    </PageHeader>

    <form @submit.prevent="submitForm" class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <!-- Main content -->
        <div class="space-y-6 xl:col-span-2">
            <!-- Details -->
            <Card mode="flat" title="Details" subtitle="Basic information shown on product pages.">
                <div class="space-y-5">
                    <FormField label="Name" required :error="errors?.name">
                        <Input
                            v-model="form.name"
                            placeholder="e.g. Ceramic Kitchen Kit"
                            :error="!!errors?.name"
                            @input="generateSlug"
                        />
                    </FormField>

                    <FormField label="Slug" hint="URL-friendly identifier." :error="errors?.slug">
                        <Input v-model="form.slug" placeholder="ceramic-kitchen-kit" :error="!!errors?.slug" />
                    </FormField>

                    <FormField label="Short description" :error="errors?.short_description">
                        <Textarea
                            v-model="form.short_description"
                            rows="3"
                            placeholder="One-line tagline shown on product cards…"
                            :error="!!errors?.short_description"
                        />
                    </FormField>

                    <FormField label="Full description" :error="errors?.description">
                        <QuillEditorComponent
                            v-model="form.description"
                            placeholder="Detailed description shown on the product page…"
                        />
                    </FormField>
                </div>
            </Card>

            <!-- Media -->
            <Card mode="flat" title="Media" subtitle="Shared product gallery. Per-variant images live in the variants section.">
                <label
                    for="dropzone-file"
                    class="group flex min-h-[13rem] w-full cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-200 dark:border-white/[0.1] bg-gray-50/50 dark:bg-white/[0.02] p-6 transition-colors hover:border-gray-400 dark:hover:border-white/[0.2] hover:bg-gray-50 dark:hover:bg-white/[0.04]"
                >
                    <div v-if="form.gallery.length === 0" class="flex flex-col items-center text-center">
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400 mb-3 group-hover:bg-gray-200 dark:group-hover:bg-white/[0.1] transition-colors">
                            <UploadCloud :size="20" />
                        </span>
                        <p class="text-body-strong text-gray-800 dark:text-white/90">
                            Drop images or <span class="text-gray-900 dark:text-white underline underline-offset-2">browse</span>
                        </p>
                        <p class="mt-1 text-caption">JPG, PNG or WebP · auto-compressed on upload</p>
                    </div>

                    <div v-if="compressingImage" class="mt-3 flex items-center gap-2 text-[12px] text-gray-600 dark:text-gray-400">
                        <Loader2 :size="14" class="animate-spin" />
                        Compressing image…
                    </div>
                    <p v-if="totalCompression() && !compressingImage" class="mt-2 text-[12px] text-success-700 dark:text-success-400">
                        Compressed {{ imageStats.length }} image{{ imageStats.length > 1 ? 's' : '' }}:
                        {{ formatBytes(totalCompression().orig) }} → {{ formatBytes(totalCompression().comp) }}
                        (saved {{ totalCompression().pct }}%)
                    </p>

                    <div v-if="form.gallery.length" class="mt-4 flex flex-wrap gap-2 justify-center">
                        <div
                            v-for="(image, index) in form.gallery"
                            :key="image.id ?? image.url"
                            class="group/img relative w-24 h-24 overflow-hidden rounded-lg border border-gray-200 dark:border-white/[0.08]"
                        >
                            <img
                                :src="image.id ? `/storage/product/${image.url}` : image.url"
                                class="w-full h-full object-cover"
                            />
                            <button
                                @click.prevent="removeImage(index)"
                                type="button"
                                class="absolute top-1 right-1 inline-flex h-6 w-6 items-center justify-center rounded-full bg-gray-900/80 text-white opacity-0 group-hover/img:opacity-100 hover:bg-error-600 transition-all backdrop-blur-sm"
                                title="Remove"
                            >
                                <X :size="12" />
                            </button>
                        </div>
                    </div>

                    <input
                        @change="handleSingleImageUpload"
                        id="dropzone-file"
                        type="file"
                        accept="image/jpeg,image/png,image/webp,image/jpg"
                        multiple
                        class="sr-only"
                    />
                </label>

                <template v-for="(error, key) in form.errors" :key="key">
                    <p v-if="key.startsWith('gallery.') && key.includes('.file')" class="mt-2 text-[12px] text-error-500">
                        {{ error }}
                    </p>
                </template>
            </Card>

            <!-- Pricing & stock -->
            <Card mode="flat" title="Pricing & inventory">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <FormField label="Base price" required :error="errors?.price">
                        <Input v-model="form.price" type="number" step="0.01" placeholder="0.00" :error="!!errors?.price">
                            <template #leading><span class="text-[13px]">₹</span></template>
                        </Input>
                    </FormField>
                    <FormField label="Compare price" hint="Shown as strike-through." :error="errors?.compere_price">
                        <Input v-model="form.compere_price" type="number" step="0.01" placeholder="0.00" :error="!!errors?.compere_price">
                            <template #leading><span class="text-[13px]">₹</span></template>
                        </Input>
                    </FormField>
                    <FormField label="SKU" :error="errors?.sku">
                        <Input v-model="form.sku" placeholder="SKU-001" :error="!!errors?.sku">
                            <template #leading><Hash :size="13" /></template>
                        </Input>
                    </FormField>
                    <FormField
                        label="Stock"
                        :hint="form.enable_variants ? 'Governed by variants.' : ''"
                        :error="errors?.stock"
                    >
                        <Input
                            v-model="form.stock"
                            type="number"
                            placeholder="0"
                            :disabled="form.enable_variants"
                            :error="!!errors?.stock"
                        />
                    </FormField>
                </div>
            </Card>

            <!-- Variants -->
            <Card
                v-if="form.enable_variants"
                mode="flat"
                title="Options & variants"
                subtitle="Define option types (Size, Colour, etc.), then generate the SKU matrix."
            >
                <div class="pb-6 mb-6 border-b border-gray-100 dark:border-white/[0.06]">
                    <p class="mb-4 text-[12.5px] text-gray-600 dark:text-gray-400">
                        Name each group (Variety, Weight, Colour…), choose how shoppers pick it — text buttons, colour dots or image
                        thumbnails — and enter the values as a comma-separated list, e.g.
                        <code class="bg-gray-100 dark:bg-white/[0.06] text-gray-700 dark:text-gray-200 px-1.5 py-0.5 rounded text-[11px] num-tabular">1 kg, 5 kg, 10 kg</code>.
                    </p>

                    <div
                        v-for="(ot, oi) in form.option_types"
                        :key="ot._key"
                        class="mb-3 grid grid-cols-1 gap-3 md:grid-cols-[1fr,180px,2fr,auto] md:items-start"
                    >
                        <Input v-model="ot.name" placeholder="Option name (e.g. Variety)" />
                        <Select
                            v-model="ot.type"
                            :options="[
                                { value: 'text', label: 'Text buttons' },
                                { value: 'color', label: 'Colour swatch' },
                                { value: 'image', label: 'Image swatch' },
                            ]"
                            @update:model-value="syncOptionValues(ot)"
                        />
                        <div>
                            <Input
                                v-model="ot._valuesInput"
                                placeholder="Comma-separated values (e.g. 1 kg, 5 kg)"
                                @update:model-value="syncOptionValues(ot)"
                            />

                            <!-- Text options need nothing beyond the labels themselves. -->
                            <div v-if="ot.type === 'text' && ot.values.length" class="mt-1.5 flex flex-wrap gap-1">
                                <Badge v-for="v in ot.values" :key="v._key" size="sm">{{ v.value }}</Badge>
                            </div>

                            <!-- Colour swatches: one hex picker per value. The hex is what
                                 the storefront paints the pill's dot with. -->
                            <div v-else-if="ot.type === 'color' && ot.values.length" class="mt-2 flex flex-wrap gap-2">
                                <label
                                    v-for="v in ot.values"
                                    :key="v._key"
                                    class="inline-flex items-center gap-2 rounded-lg border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-white/[0.03] py-1 pl-1 pr-2.5"
                                >
                                    <input
                                        type="color"
                                        :value="v.swatch || '#cccccc'"
                                        @input="v.swatch = $event.target.value"
                                        class="h-7 w-7 cursor-pointer rounded-md border-0 bg-transparent p-0"
                                        :aria-label="`Colour for ${v.value}`"
                                    />
                                    <span class="text-[12px] text-gray-700 dark:text-gray-200">{{ v.value }}</span>
                                </label>
                            </div>

                            <!-- Image swatches: one thumbnail upload per value. Leaving a
                                 value empty falls back to that variant's own photo. -->
                            <div v-else-if="ot.type === 'image' && ot.values.length" class="mt-2 flex flex-wrap gap-2">
                                <div
                                    v-for="v in ot.values"
                                    :key="v._key"
                                    class="inline-flex items-center gap-2 rounded-lg border border-gray-200 dark:border-white/[0.1] bg-white dark:bg-white/[0.03] p-1.5"
                                >
                                    <label class="relative flex h-9 w-9 cursor-pointer items-center justify-center overflow-hidden rounded-md border border-dashed border-gray-300 dark:border-white/[0.15] bg-gray-50 dark:bg-white/[0.02]">
                                        <img v-if="v._preview" :src="v._preview" class="h-full w-full object-cover" alt="" />
                                        <img v-else-if="v.swatch" :src="`/storage/product/${v.swatch}`" class="h-full w-full object-cover" alt="" />
                                        <ImagePlus v-else :size="13" class="text-gray-400" />
                                        <input
                                            type="file"
                                            accept="image/jpeg,image/png,image/webp,image/jpg"
                                            class="sr-only"
                                            @change="handleSwatchImage($event, v)"
                                        />
                                    </label>
                                    <span class="text-[12px] text-gray-700 dark:text-gray-200">{{ v.value }}</span>
                                </div>
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="removeOptionType(oi)"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-gray-500 hover:bg-error-50 hover:text-error-600 dark:text-gray-400 dark:hover:bg-error-500/10 dark:hover:text-error-400 transition-colors focus-ring"
                            aria-label="Remove option type"
                        >
                            <Trash2 :size="15" />
                        </button>
                    </div>

                    <div class="flex flex-wrap gap-2 mt-4">
                        <Button variant="secondary" size="sm" @click="addOptionType">
                            <template #leading><Plus :size="14" /></template>
                            Add option type
                        </Button>
                        <Button variant="subtle" size="sm" :disabled="!canGenerateMatrix" @click="generateMatrix">
                            <template #leading><Wand2 :size="14" /></template>
                            Generate matrix
                        </Button>
                    </div>
                    <p v-if="matrixConflict" class="mt-3 inline-flex items-start gap-1.5 text-[12px] text-warning-700 dark:text-warning-400">
                        <TriangleAlert :size="14" class="mt-0.5 shrink-0" />
                        <span>Regenerating replaces unsaved variants. Existing saved variants matching a new combination are preserved.</span>
                    </p>
                </div>

                <div
                    v-if="form.variants.length === 0"
                    class="rounded-xl border border-dashed border-gray-200 dark:border-white/[0.1] py-10 px-4 text-center bg-gray-50/40 dark:bg-white/[0.02]"
                >
                    <Layers :size="24" class="mx-auto text-gray-400 mb-2" />
                    <p class="text-body-strong text-gray-800 dark:text-white/90 mb-1">No variants yet</p>
                    <p class="text-[12px] text-gray-500 dark:text-gray-400 max-w-md mx-auto">
                        Define option types + values above and click <b>Generate matrix</b>, or add a variant manually.
                    </p>
                </div>

                <div v-if="form.variants.length > 0" class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/[0.06]">
                    <div class="overflow-x-auto custom-scrollbar">
                        <table class="w-full text-[13px]">
                            <thead class="bg-gray-50/60 dark:bg-white/[0.02] text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-white/[0.06]">
                                <tr>
                                    <th class="py-3 px-4">Image</th>
                                    <th class="py-3 px-4">Options</th>
                                    <th class="py-3 px-4">SKU</th>
                                    <th class="py-3 px-4">Price</th>
                                    <th class="py-3 px-4">Compare</th>
                                    <th class="py-3 px-4">Stock</th>
                                    <th class="py-3 px-4 text-center">Default</th>
                                    <th class="py-3 px-4 text-center">Active</th>
                                    <th class="py-3 px-4 w-12"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/[0.04]">
                                <tr
                                    v-for="(variant, vIndex) in form.variants"
                                    :key="variant._key"
                                    class="align-top hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors"
                                >
                                    <td class="py-4 px-4">
                                        <label class="group relative flex h-16 w-16 items-center justify-center rounded-lg border-2 border-dashed border-gray-200 dark:border-white/[0.1] hover:border-gray-400 dark:hover:border-white/[0.2] bg-gray-50 dark:bg-white/[0.02] overflow-hidden cursor-pointer transition-colors">
                                            <img v-if="variant._previewUrl" :src="variant._previewUrl" class="w-full h-full object-cover" alt="Variant" />
                                            <img v-else-if="variant.image_url" :src="`/storage/product/${variant.image_url}`" class="w-full h-full object-cover" alt="Variant" />
                                            <div v-else class="flex flex-col items-center gap-0.5 text-gray-400">
                                                <ImagePlus :size="16" />
                                                <span class="text-[10px] font-medium">Image</span>
                                            </div>
                                            <input type="file" accept="image/jpeg,image/png,image/webp,image/jpg" class="sr-only" @change="handleVariantImage($event, vIndex)" />
                                        </label>
                                        <button
                                            type="button"
                                            v-if="variant._previewUrl || variant.image_url"
                                            @click="clearVariantImage(vIndex)"
                                            class="mt-1.5 inline-flex items-center gap-1 text-[10px] font-medium text-gray-500 hover:text-error-500 focus-ring rounded"
                                        >
                                            <X :size="10" />
                                            Remove
                                        </button>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div v-if="declaredOptionNames.length === 0" class="text-[12px] text-gray-400 dark:text-gray-500 italic">
                                            Define option types above
                                        </div>
                                        <div v-else class="flex flex-col gap-2 min-w-[160px]">
                                            <div v-for="name in declaredOptionNames" :key="name">
                                                <div class="text-eyebrow text-gray-500 dark:text-gray-400 mb-1">{{ name }}</div>
                                                <!-- Constrained to the option type's declared values so a
                                                     variant can never carry a value the storefront picker
                                                     has no pill for, which silently made the combination
                                                     unselectable. Falls back to free text only while the
                                                     admin has entered no values yet. -->
                                                <Select
                                                    v-if="optionValueChoices(name).length"
                                                    size="sm"
                                                    :model-value="variant.options[name] ?? ''"
                                                    :options="optionValueChoices(name)"
                                                    placeholder="—"
                                                    @update:model-value="setVariantOption(vIndex, name, $event)"
                                                />
                                                <Input
                                                    v-else
                                                    size="sm"
                                                    :model-value="variant.options[name] ?? ''"
                                                    placeholder="—"
                                                    @update:model-value="setVariantOption(vIndex, name, $event)"
                                                />
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="w-32">
                                            <Input v-model="variant.sku" size="sm" placeholder="SKU" />
                                        </div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="w-28">
                                            <Input v-model="variant.price" size="sm" type="number" step="0.01" :placeholder="form.price || '0.00'">
                                                <template #leading><span class="text-gray-400 text-[12px]">₹</span></template>
                                            </Input>
                                        </div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="w-28">
                                            <Input v-model="variant.compare_price" size="sm" type="number" step="0.01" placeholder="—">
                                                <template #leading><span class="text-gray-400 text-[12px]">₹</span></template>
                                            </Input>
                                        </div>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="w-24">
                                            <Input v-model="variant.stock" size="sm" type="number" placeholder="0" />
                                        </div>
                                    </td>
                                    <td class="py-4 px-4 text-center">
                                        <label class="inline-flex items-center justify-center cursor-pointer group">
                                            <input
                                                type="radio"
                                                name="default_variant"
                                                :checked="variant.is_default"
                                                :disabled="!variant.status"
                                                :title="variant.status ? 'Open the product page on this variant' : 'Activate the variant before making it the default'"
                                                @change="markDefault(vIndex)"
                                                class="sr-only peer"
                                            />
                                            <span
                                                :class="[
                                                    'flex h-4.5 w-4.5 items-center justify-center rounded-full border-2 shadow-theme-xs transition-colors peer-focus-visible:ring-4 peer-focus-visible:ring-brand-500/25',
                                                    variant.is_default
                                                        ? 'border-gray-900 bg-white dark:border-white dark:bg-white/[0.04]'
                                                        : 'border-gray-300 hover:border-gray-400 dark:border-white/[0.15] dark:hover:border-white/[0.25] bg-white dark:bg-white/[0.04]',
                                                ]"
                                            >
                                                <span v-if="variant.is_default" class="h-1.5 w-1.5 rounded-full bg-gray-900 dark:bg-white"></span>
                                            </span>
                                        </label>
                                    </td>
                                    <td class="py-4 px-4">
                                        <div class="flex justify-center">
                                            <Checkbox :model-value="variant.status" @update:model-value="setVariantStatus(vIndex, $event)" />
                                        </div>
                                    </td>
                                    <td class="py-4 px-4 text-right">
                                        <button
                                            type="button"
                                            @click="removeVariant(vIndex)"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-500 hover:bg-error-50 hover:text-error-600 dark:text-gray-400 dark:hover:bg-error-500/10 dark:hover:text-error-400 transition-colors focus-ring"
                                            :aria-label="`Remove variant ${vIndex + 1}`"
                                        >
                                            <Trash2 :size="14" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-4">
                    <Button variant="secondary" size="sm" @click="addVariant">
                        <template #leading><Plus :size="14" /></template>
                        Add variant
                    </Button>
                </div>

                <div v-if="variantSetupErrors.length" class="variant-setup-errors mt-4 rounded-lg border border-error-200 bg-error-50 px-3.5 py-3 dark:border-error-500/30 dark:bg-error-500/10">
                    <p class="inline-flex items-center gap-1.5 text-[12.5px] font-semibold text-error-700 dark:text-error-400">
                        <TriangleAlert :size="14" />
                        Fix before saving
                    </p>
                    <ul class="mt-1.5 list-disc space-y-1 pl-5 text-[12px] text-error-700 dark:text-error-400">
                        <li v-for="problem in variantSetupErrors" :key="problem">{{ problem }}</li>
                    </ul>
                </div>

                <template v-for="(error, key) in form.errors" :key="key">
                    <p v-if="key.startsWith('variants.') || key.startsWith('option_types.')" class="text-[12px] text-error-500 mt-2">
                        {{ error }}
                    </p>
                </template>
            </Card>

            <!-- SEO -->
            <Card mode="flat" title="SEO & social" subtitle="Search-engine snippets and canonical URL.">
                <div class="space-y-4">
                    <FormField label="Meta title" :error="errors?.meta_title">
                        <Input v-model="form.meta_title" maxlength="255" placeholder="Displayed in browser tab & search results" :error="!!errors?.meta_title" />
                    </FormField>
                    <FormField label="Meta description">
                        <Textarea v-model="form.meta_description" maxlength="500" rows="2" placeholder="Short paragraph shown in search results" />
                    </FormField>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormField label="Meta keywords">
                            <Input v-model="form.meta_keywords" placeholder="comma,separated,keywords" />
                        </FormField>
                        <FormField label="Canonical URL">
                            <Input v-model="form.canonical_url" type="url" placeholder="https://example.com/product-x" />
                        </FormField>
                    </div>
                </div>
            </Card>
        </div>

        <!-- Sidebar -->
        <aside class="space-y-6 xl:col-span-1">
            <!-- Status -->
            <Card mode="flat" title="Status">
                <div class="space-y-4">
                    <Switch v-model="form.status">
                        <span class="font-medium text-gray-900 dark:text-white/95">Active</span>
                        <span class="block text-[12px] text-gray-500 dark:text-gray-400">Show on storefront.</span>
                    </Switch>
                    <Switch v-model="form.featured">
                        <span class="font-medium text-gray-900 dark:text-white/95">Featured</span>
                        <span class="block text-[12px] text-gray-500 dark:text-gray-400">Highlight on the home page.</span>
                    </Switch>
                    <Switch v-model="form.enable_variants">
                        <span class="font-medium text-gray-900 dark:text-white/95">Enable variants</span>
                        <span class="block text-[12px] text-gray-500 dark:text-gray-400">Multiple SKUs per product.</span>
                    </Switch>
                    <Switch v-model="form.show_quantity">
                        <span class="font-medium text-gray-900 dark:text-white/95">Quantity selector</span>
                        <span class="block text-[12px] text-gray-500 dark:text-gray-400">
                            Let shoppers choose how many. Turn off for one-per-order items.
                            Even when on, it hides itself when only one is left to buy.
                        </span>
                    </Switch>
                </div>
            </Card>

            <!-- Organization -->
            <Card mode="flat" title="Organization">
                <div class="space-y-4">
                    <FormField label="Categories" :error="errors?.categories">
                        <MultiSelect
                            v-model="form.categories"
                            :options="categoryOptions"
                            placeholder="Select categories…"
                            search-placeholder="Search categories…"
                            :error="!!errors?.categories"
                        />
                    </FormField>
                    <FormField label="Barcode">
                        <Input v-model="form.barcode" placeholder="EAN / UPC" />
                    </FormField>
                </div>
            </Card>

            <!-- Shipping -->
            <Card mode="flat" title="Shipping">
                <div class="space-y-4">
                    <FormField label="Weight (kg)">
                        <Input v-model="form.weight" type="number" step="0.001" placeholder="0.500" />
                    </FormField>
                    <div class="grid grid-cols-2 gap-3">
                        <FormField label="Tax class">
                            <Input v-model="form.tax_class" placeholder="standard" />
                        </FormField>
                        <FormField label="HS code">
                            <Input v-model="form.hs_code" placeholder="6109" />
                        </FormField>
                    </div>
                </div>
            </Card>

            <!-- Return policy -->
            <Card mode="flat" title="Return policy">
                <FormField hint="Optional — shown to shoppers on this product's page.">
                    <Textarea v-model="form.return_policy" rows="4" placeholder="Describe your return terms…" />
                </FormField>
            </Card>
        </aside>

        <!-- Upload progress banner (spans full width) -->
        <div
            v-if="form.processing && uploadProgress > 0"
            class="xl:col-span-3 rounded-xl border border-gray-200 dark:border-white/[0.06] bg-white dark:bg-[color:var(--color-surface-dark)] p-4"
        >
            <div class="flex items-center justify-between mb-1.5 text-[12px] text-gray-600 dark:text-gray-400">
                <span class="inline-flex items-center gap-1.5">
                    <Loader2 :size="12" class="animate-spin" />
                    Uploading…
                </span>
                <span class="num-tabular font-medium text-gray-900 dark:text-white/95">{{ uploadProgress }}%</span>
            </div>
            <div class="h-1.5 bg-gray-100 dark:bg-white/[0.06] rounded-full overflow-hidden">
                <div class="h-full bg-gray-900 dark:bg-white rounded-full transition-all duration-200" :style="{ width: uploadProgress + '%' }"></div>
            </div>
        </div>
    </form>

    <!-- Sticky footer action bar -->
    <div class="sticky bottom-0 z-30 mt-6 -mx-4 sm:-mx-6 lg:-mx-8">
        <div class="border-t border-gray-200 dark:border-white/[0.06] bg-[color:var(--color-canvas)]/90 dark:bg-[color:var(--color-canvas-dark)]/90 backdrop-blur-md">
            <div class="mx-auto flex max-w-[1440px] items-center justify-end gap-2 px-4 py-3 sm:px-6 lg:px-8">
                <span v-if="dirty" class="mr-auto text-[12px] text-gray-500 dark:text-gray-400 inline-flex items-center gap-1.5">
                    <span class="h-1.5 w-1.5 rounded-full bg-warning-500"></span>
                    Unsaved changes
                </span>
                <Button variant="secondary" size="sm" tag="a" :href="route('admin.products')">Cancel</Button>
                <Button variant="primary" size="sm" type="submit" :loading="form.processing" @click="submitForm">
                    {{ isEdit ? 'Save changes' : 'Publish product' }}
                </Button>
            </div>
        </div>
    </div>
</template>

<script>
import Layout from '@/Layout/MainLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import QuillEditorComponent from '@/components/common/QuillEditorComponent.vue';
import { compressImage, formatBytes } from '@/Utils/compressImage';
import {
    PageHeader, Card, Button, Input, Textarea, Select, MultiSelect,
    Switch, Checkbox, Badge, FormField,
} from '@/Components/ui';
import {
    ImagePlus, Layers, Plus, Trash2, TriangleAlert, Wand2, X,
    UploadCloud, Hash, Loader2,
} from '@lucide/vue';

let keyCounter = 0;
const nextKey = () => `k${++keyCounter}_${Date.now()}`;

const OPTION_TYPES = ['text', 'color', 'image'];
const HEX_RE = /^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i;

// Starting point for colour swatches: a value named after a common colour gets
// that colour pre-filled, so most of the time the admin only has to confirm.
const COLOUR_NAMES = {
    black: '#000000', white: '#ffffff', red: '#e53935', green: '#43a047', blue: '#1e88e5',
    yellow: '#fdd835', orange: '#fb8c00', purple: '#8e24aa', pink: '#ec407a', brown: '#795548',
    grey: '#9e9e9e', gray: '#9e9e9e', silver: '#c0c0c0', gold: '#d4af37', beige: '#f5f5dc',
    ivory: '#fffff0', cream: '#fffdd0', navy: '#1a237e', teal: '#00897b', maroon: '#800000',
    olive: '#808000', lime: '#c0ca33', cyan: '#00acc1', aqua: '#00ffff', magenta: '#d81b60',
    violet: '#7e57c2', indigo: '#3f51b5', turquoise: '#40e0d0', coral: '#ff7f50', salmon: '#fa8072',
    tan: '#d2b48c', khaki: '#c3b091', lavender: '#b39ddb', mint: '#98ff98', peach: '#ffcba4',
    mustard: '#e1ad01', rust: '#b7410e', charcoal: '#36454f', copper: '#b87333', bronze: '#cd7f32',
    rosegold: '#b76e79', skyblue: '#87ceeb', lightblue: '#90caf9', darkblue: '#0d47a1', royalblue: '#4169e1',
    darkgreen: '#1b5e20', lightgreen: '#a5d6a7', darkred: '#8b0000', wine: '#722f37', burgundy: '#800020',
    transparent: '#ffffff', clear: '#ffffff', natural: '#e8dcc8', wood: '#c19a6b', terracotta: '#e2725b',
    sand: '#e0c9a6', stone: '#a8a39d', slate: '#708090', chocolate: '#5d4037', caramel: '#c68e17',
};
const guessColour = (value) => COLOUR_NAMES[String(value || '').toLowerCase().replace(/[^a-z]/g, '')] || '';

export default {
    layout: Layout,
    components: {
        Head, Link, QuillEditorComponent,
        PageHeader, Card, Button, Input, Textarea, Select, MultiSelect,
        Switch, Checkbox, Badge, FormField,
        ImagePlus, Layers, Plus, Trash2, TriangleAlert, Wand2, X,
        UploadCloud, Hash, Loader2,
    },
    props: {
        categories: { type: Array, default: () => [] },
        product: Object,
    },
    data() {
        const initialVariants = (this.product?.variants_payload || []).map((v) => ({
            ...v,
            _key: nextKey(),
            _previewUrl: null,
            _file: null,
            status: v.status !== false,
            price: v.price ?? '',
            compare_price: v.compare_price ?? '',
            options: v.options && typeof v.options === 'object' ? { ...v.options } : {},
        }));

        let initialOptionTypes = this.buildOptionTypes(this.product?.option_types, initialVariants);

        return {
            compressingImage: false,
            uploadProgress: 0,
            imageStats: [],
            dirty: false,
            form: {
                name: this.product?.name || '',
                slug: this.product?.slug || '',
                short_description: this.product?.short_description || '',
                description: this.product?.description || '',
                categories: this.product?.categories || [],
                return_policy: this.product?.return_policy || '',
                price: this.product?.price ?? '',
                compere_price: this.product?.compere_price ?? '',
                stock: this.product?.stock ?? 0,
                sku: this.product?.sku || '',
                barcode: this.product?.barcode || '',
                weight: this.product?.weight ?? '',
                tax_class: this.product?.tax_class || '',
                hs_code: this.product?.hs_code || '',
                meta_title: this.product?.meta_title || '',
                meta_description: this.product?.meta_description || '',
                meta_keywords: this.product?.meta_keywords || '',
                canonical_url: this.product?.canonical_url || '',
                status: !!this.product?.status,
                featured: !!this.product?.featured,
                enable_variants: !!this.product?.has_variants,
                // Absent on a new product, so default it on rather than off.
                show_quantity: this.product ? this.product.show_quantity !== false : true,
                option_types: initialOptionTypes,
                variants: initialVariants,
                gallery: this.product?.medias || [],
                processing: false,
                errors: {},
            },
        };
    },
    computed: {
        // Reactive validation errors from Inertia's shared page props.
        errors() { return usePage().props.errors || {}; },
        isEdit() {
            return !!this.product?.id;
        },
        crumbs() {
            return [
                { label: 'Dashboard', href: route('admin.dashboard') },
                { label: 'Products', href: route('admin.products') },
                { label: this.isEdit ? 'Edit' : 'New' },
            ];
        },
        categoryOptions() {
            return (this.categories || []).map((c) => ({ value: c.id, label: c.label }));
        },
        declaredOptionNames() {
            return this.form.option_types
                .map((ot) => (ot.name || '').trim())
                .filter((n) => n.length > 0);
        },
        canGenerateMatrix() {
            return this.form.option_types.length > 0
                && this.form.option_types.every((ot) => (ot.name || '').trim() && this.parsedValues(ot).length > 0);
        },
        matrixConflict() {
            return this.form.variants.length > 0;
        },
        /**
         * Blocking problems with the variant setup. Without these the form saves
         * "successfully" but the product goes out on the storefront broken: an
         * empty matrix is submitted as variants:[], which the server reads as a
         * plain single-SKU product with stock 0, and duplicate option
         * combinations make every variant after the first unreachable in the
         * storefront picker.
         */
        variantSetupErrors() {
            if (!this.form.enable_variants) return [];
            const problems = [];

            if (this.form.variants.length === 0) {
                problems.push('Add at least one variant, or turn "Enable variants" off to sell this as a single SKU with its own stock.');
            }

            const names = this.declaredOptionNames;

            // Duplicate group names collide: the variant options dict is keyed by name.
            const lowered = names.map((n) => n.toLowerCase());
            const dupName = names.find((n, i) => lowered.indexOf(n.toLowerCase()) !== i);
            if (dupName) {
                problems.push(`Option type "${dupName}" is listed more than once.`);
            }

            // Two or more variants with nothing to tell them apart: the picker has
            // no groups to draw, so only the default could ever be bought.
            if (names.length === 0 && this.form.variants.length > 1) {
                problems.push('Add at least one option type (Size, Colour…) so shoppers can tell the variants apart, or keep a single variant.');
            }

            if (names.length > 0 && this.form.variants.length > 0) {
                const blank = this.form.variants.filter(
                    (v) => names.some((n) => !String(v.options?.[n] ?? '').trim()),
                );
                if (blank.length) {
                    problems.push(`${blank.length} variant${blank.length > 1 ? 's are' : ' is'} missing a value for one of the option types.`);
                }

                const seen = new Set();
                const dupes = new Set();
                this.form.variants.forEach((v) => {
                    const key = names.map((n) => `${n}=${String(v.options?.[n] ?? '').trim().toLowerCase()}`).join('|');
                    if (seen.has(key)) dupes.add(key);
                    seen.add(key);
                });
                if (dupes.size) {
                    problems.push(`${dupes.size} option combination${dupes.size > 1 ? 's are' : ' is'} used by more than one variant — shoppers can only ever reach the first.`);
                }
            }

            if (this.form.variants.length > 0 && !this.form.variants.some((v) => v.status)) {
                problems.push('At least one variant must be active.');
            }

            return problems;
        },
    },
    watch: {
        form: {
            handler() { this.dirty = true; },
            deep: true,
        },
    },
    mounted() {
        // Suppress the initial dirty-flag flip triggered by mount initializations.
        this.$nextTick(() => { this.dirty = false; });
    },
    beforeUnmount() {
        if (Array.isArray(this.form?.gallery)) {
            this.form.gallery.forEach((image) => {
                if (image?.url && typeof image.url === 'string' && image.url.startsWith('blob:')) {
                    URL.revokeObjectURL(image.url);
                }
            });
        }
        this.form.variants.forEach((v) => {
            if (v._previewUrl && v._previewUrl.startsWith('blob:')) URL.revokeObjectURL(v._previewUrl);
        });
        this.form.option_types.forEach((ot) => {
            (ot.values || []).forEach((v) => {
                if (v._preview?.startsWith('blob:')) URL.revokeObjectURL(v._preview);
            });
        });
    },
    methods: {
        buildOptionTypes(savedTypes, variants) {
            const rows = [];

            const makeRow = (name, type, savedValues) => {
                // Value list precedence: what the admin explicitly saved on the
                // option type, else the distinct values the variant rows actually
                // use (which covers products saved before option values carried
                // swatches, and the pre-migration `swatches` map shape).
                const fromSaved = Array.isArray(savedValues)
                    ? savedValues.map((v) => (typeof v === 'string' ? { value: v, swatch: null } : v))
                    : [];
                const list = fromSaved.length
                    ? fromSaved
                    : this.valuesFromVariants(name, variants).map((value) => ({ value, swatch: null }));

                const values = list
                    .filter((v) => v && String(v.value ?? '').trim().length > 0)
                    .map((v) => ({
                        _key: nextKey(),
                        value: String(v.value).trim(),
                        swatch: v.swatch ?? null,
                        _file: null,
                        _preview: null,
                    }));

                return {
                    _key: nextKey(),
                    name: name || '',
                    type: OPTION_TYPES.includes(type) ? type : 'text',
                    values,
                    _valuesInput: values.map((v) => v.value).join(', '),
                };
            };

            if (Array.isArray(savedTypes) && savedTypes.length) {
                savedTypes.forEach((ot) => {
                    // Legacy shape: { swatches: { "Red": "#ff0000" } } with the
                    // value list living only on the variants.
                    let saved = ot.values;
                    if (!Array.isArray(saved) && ot.swatches && typeof ot.swatches === 'object') {
                        saved = Object.keys(ot.swatches).map((value) => ({ value, swatch: ot.swatches[value] }));
                    }
                    rows.push(makeRow(ot.name, ot.type, saved));
                });
                return rows;
            }

            const namesSet = new Set();
            (variants || []).forEach((v) => {
                Object.keys(v.options || {}).forEach((n) => namesSet.add(n));
            });
            Array.from(namesSet).forEach((name) => rows.push(makeRow(name, 'text', null)));
            return rows;
        },
        valuesFromVariants(name, variants) {
            const seen = new Set();
            (variants || []).forEach((v) => {
                const val = v.options?.[name];
                if (val) seen.add(val);
            });
            return Array.from(seen);
        },
        parsedValues(ot) {
            return (ot.values || []).map((v) => v.value);
        },
        /**
         * Reconcile ot.values with the comma-separated text field, preserving the
         * swatch already attached to any value the admin kept. Runs on every
         * keystroke in the values input and whenever the option type changes.
         */
        syncOptionValues(ot) {
            const wanted = (ot._valuesInput || '')
                .split(',')
                .map((s) => s.trim())
                .filter((s) => s.length > 0);

            const deduped = [];
            const seen = new Set();
            wanted.forEach((v) => {
                if (!seen.has(v)) { seen.add(v); deduped.push(v); }
            });

            const previous = new Map((ot.values || []).map((v) => [v.value, v]));
            ot.values = deduped.map((value) => previous.get(value) || {
                _key: nextKey(),
                value,
                swatch: ot.type === 'color' ? guessColour(value) : null,
                _file: null,
                _preview: null,
            });

            // A colour row needs a hex even if it was created while the type was
            // still 'text'; an image row must not inherit a stray hex.
            ot.values.forEach((v) => {
                if (ot.type === 'color' && !HEX_RE.test(String(v.swatch || ''))) {
                    v.swatch = guessColour(v.value);
                } else if (ot.type === 'text') {
                    v.swatch = null;
                } else if (ot.type === 'image' && String(v.swatch || '').startsWith('#')) {
                    v.swatch = null;
                }
            });

            // Drop variant option values that no longer exist in the group, so the
            // matrix cannot keep a combination the storefront cannot render.
            if ((ot.name || '').trim()) {
                const name = ot.name.trim();
                this.form.variants.forEach((variant) => {
                    if (variant.options[name] && !seen.has(variant.options[name])) {
                        variant.options = { ...variant.options, [name]: '' };
                    }
                });
            }
        },
        optionValueChoices(name) {
            const ot = this.form.option_types.find((o) => (o.name || '').trim() === name);
            return (ot?.values || []).map((v) => ({ value: v.value, label: v.value }));
        },
        async handleSwatchImage(event, valueRow) {
            const file = event.target.files?.[0];
            event.target.value = null;
            if (!file) return;
            let output = file;
            try {
                output = await compressImage(file, { maxDimension: 240, quality: 0.85, mimeType: 'image/jpeg' });
            } catch (e) { /* fall back to the original file */ }
            if (valueRow._preview?.startsWith('blob:')) URL.revokeObjectURL(valueRow._preview);
            valueRow._file = output;
            valueRow._preview = URL.createObjectURL(output);
        },
        generateSlug() {
            if (this.isEdit) return;
            if (this.form.name) {
                this.form.slug = this.form.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
            }
        },
        addOptionType() {
            this.form.option_types.push({ _key: nextKey(), name: '', type: 'text', values: [], _valuesInput: '' });
        },
        removeOptionType(i) {
            const removed = this.form.option_types[i];
            (removed?.values || []).forEach((v) => {
                if (v._preview?.startsWith('blob:')) URL.revokeObjectURL(v._preview);
            });
            this.form.option_types.splice(i, 1);
            if (removed?.name) {
                this.form.variants.forEach((v) => { delete v.options[removed.name]; });
            }
        },
        generateMatrix() {
            const types = this.form.option_types
                .filter((ot) => (ot.name || '').trim() && this.parsedValues(ot).length > 0);
            if (types.length === 0) return;

            const combos = types.reduce((acc, ot) => {
                const values = this.parsedValues(ot);
                return acc.flatMap((partial) => values.map((val) => ({ ...partial, [ot.name.trim()]: val })));
            }, [{}]);

            const existingSaved = this.form.variants.filter((v) => v.id);
            const optionsKey = (opts) => Object.keys(opts).sort().map((k) => `${k}=${opts[k]}`).join('|');
            const savedByKey = new Map(existingSaved.map((v) => [optionsKey(v.options || {}), v]));

            const nextVariants = combos.map((options) => {
                const match = savedByKey.get(optionsKey(options));
                if (match) return match;
                return {
                    _key: nextKey(),
                    id: null,
                    sku: '',
                    price: '',
                    compare_price: '',
                    stock: 0,
                    image_url: null,
                    _previewUrl: null,
                    _file: null,
                    position: 0,
                    status: true,
                    is_default: false,
                    options: { ...options },
                };
            });

            if (nextVariants.length && !nextVariants.some((v) => v.is_default)) {
                nextVariants[0].is_default = true;
            }
            this.form.variants = nextVariants;
        },
        addVariant() {
            const blankOptions = {};
            this.declaredOptionNames.forEach((n) => { blankOptions[n] = ''; });
            this.form.variants.push({
                _key: nextKey(), id: null, sku: '', price: '', compare_price: '', stock: 0,
                image_url: null, _previewUrl: null, _file: null,
                position: this.form.variants.length, status: true,
                is_default: this.form.variants.length === 0,
                options: blankOptions,
            });
        },
        removeVariant(index) {
            const v = this.form.variants[index];
            if (v?._previewUrl?.startsWith('blob:')) URL.revokeObjectURL(v._previewUrl);
            const wasDefault = !!v?.is_default;
            this.form.variants.splice(index, 1);
            if (wasDefault && this.form.variants.length > 0) {
                const next = this.form.variants.find((row) => row.status) || this.form.variants[0];
                next.is_default = true;
            }
        },
        markDefault(index) {
            // Only a sellable row can be the default the product page opens on.
            if (!this.form.variants[index]?.status) return;
            this.form.variants.forEach((v, i) => { v.is_default = i === index; });
        },
        setVariantStatus(index, active) {
            const variant = this.form.variants[index];
            variant.status = !!active;
            // Deactivating the default hands the flag to the first active row.
            if (!variant.status && variant.is_default) {
                const next = this.form.variants.find((row) => row.status);
                if (next) {
                    variant.is_default = false;
                    next.is_default = true;
                }
            }
        },
        setVariantOption(variantIndex, name, newValue) {
            const variant = this.form.variants[variantIndex];
            variant.options = { ...variant.options, [name]: newValue };
        },

        async handleVariantImage(event, index) {
            const file = event.target.files?.[0];
            event.target.value = null;
            if (!file) return;
            let output = file;
            try {
                output = await compressImage(file, { maxDimension: 1200, quality: 0.82, mimeType: 'image/jpeg' });
            } catch (e) { /* fall back */ }
            const variant = this.form.variants[index];
            if (variant._previewUrl?.startsWith('blob:')) URL.revokeObjectURL(variant._previewUrl);
            variant._file = output;
            variant._previewUrl = URL.createObjectURL(output);
        },
        clearVariantImage(index) {
            const variant = this.form.variants[index];
            if (variant._previewUrl?.startsWith('blob:')) URL.revokeObjectURL(variant._previewUrl);
            variant._previewUrl = null;
            variant._file = null;
            variant.image_url = null;
        },
        async handleSingleImageUpload(event) {
            const files = Array.from(event.target.files || []);
            event.target.value = null;
            if (!files.length) return;
            this.compressingImage = true;
            try {
                for (const file of files) {
                    const original = file.size;
                    let output = file;
                    try {
                        output = await compressImage(file, { maxDimension: 1600, quality: 0.82, mimeType: 'image/jpeg' });
                    } catch (e) { /* fall back */ }
                    const url = URL.createObjectURL(output);
                    this.form.gallery.push({ file: output, url });
                    this.imageStats.push({ original, compressed: output.size });
                }
            } finally {
                this.compressingImage = false;
            }
        },
        removeImage(index) {
            const item = this.form.gallery[index];
            if (item?.url && typeof item.url === 'string' && item.url.startsWith('blob:')) URL.revokeObjectURL(item.url);
            this.form.gallery.splice(index, 1);
            if (item && item.file && this.imageStats.length) {
                this.imageStats.splice(Math.min(index, this.imageStats.length - 1), 1);
            }
        },
        formatBytes,
        totalCompression() {
            if (!this.imageStats.length) return null;
            const orig = this.imageStats.reduce((s, r) => s + r.original, 0);
            const comp = this.imageStats.reduce((s, r) => s + r.compressed, 0);
            if (orig === 0) return null;
            const saved = Math.max(0, orig - comp);
            return { orig, comp, saved, pct: Math.round((saved / orig) * 100) };
        },
        buildSubmitPayload() {
            const payload = { ...this.form };
            delete payload.errors;
            delete payload.processing;

            if (!payload.enable_variants) {
                payload.variants = [];
                payload.option_types = [];
                const s = Number(payload.stock);
                payload.stock = Number.isFinite(s) && s >= 0 ? Math.floor(s) : 0;
            } else {
                payload.option_types = this.form.option_types
                    .filter((ot) => (ot.name || '').trim())
                    .map((ot) => {
                        const type = OPTION_TYPES.includes(ot.type) ? ot.type : 'text';
                        return {
                            name: ot.name.trim(),
                            type,
                            // The value list travels with the group, so the admin
                            // owns the pill order and each value's swatch.
                            values: (ot.values || []).map((v) => {
                                const row = { value: v.value };
                                if (type === 'color') {
                                    row.swatch = v.swatch || '';
                                } else if (type === 'image') {
                                    if (v._file) row.swatch_file = v._file;
                                    if (v.swatch) row.swatch = v.swatch;
                                }
                                return row;
                            }),
                        };
                    });
                payload.variants = this.form.variants.map((v, index) => {
                    const row = {
                        id: v.id || null,
                        sku: v.sku,
                        price: v.price === '' ? null : v.price,
                        compare_price: v.compare_price === '' ? null : v.compare_price,
                        stock: v.stock === '' || v.stock == null ? 0 : Number(v.stock) || 0,
                        weight: v.weight ?? null,
                        image_url: v.image_url,
                        position: index,
                        status: v.status ? 1 : 0,
                        is_default: v.is_default ? 1 : 0,
                        options: v.options || {},
                    };
                    if (v._file) row.image_file = v._file;
                    return row;
                });
                payload.stock = 0;
            }
            // The server treats has_variants as the authoritative toggle rather
            // than inferring it from a non-empty variants array, so it has to be
            // on the wire even when it is off.
            payload.has_variants = this.form.enable_variants ? 1 : 0;
            delete payload.enable_variants;
            payload.status = this.form.status ? 1 : 0;
            payload.featured = this.form.featured ? 1 : 0;
            payload.show_quantity = this.form.show_quantity ? 1 : 0;
            return payload;
        },
        submitForm() {
            if (this.variantSetupErrors.length) {
                document.querySelector('.variant-setup-errors')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
            const url = this.isEdit ? route('admin.product.update', this.product.id) : route('admin.product.store');
            const payload = this.buildSubmitPayload();
            this.form.processing = true;
            this.uploadProgress = 0;
            if (this.isEdit) payload._method = 'put';
            this.form.errors = {};
            router.post(url, payload, {
                forceFormData: true,
                onProgress: (event) => {
                    if (event?.total) this.uploadProgress = Math.round((event.loaded / event.total) * 100);
                },
                onError: (errors) => {
                    this.form.errors = errors;
                    this.form.processing = false;
                    this.uploadProgress = 0;
                },
                onSuccess: () => {
                    this.dirty = false;
                },
                onFinish: () => {
                    this.form.processing = false;
                    setTimeout(() => { this.uploadProgress = 0; }, 800);
                },
            });
        },
    },
};
</script>
