<template>
    <Head title="General settings" />

    <SettingsShell
        title="General settings"
        subtitle="Storefront branding, contact information, and social links."
    >
        <template #actions>
            <Button variant="primary" size="sm" :loading="form.processing" @click="submit">
                Save changes
            </Button>
        </template>

        <form @submit.prevent="submit" enctype="multipart/form-data" class="space-y-6">
            <!-- Contact -->
            <Card mode="flat" title="Store & contact" subtitle="Displayed on the storefront footer and contact page.">
                <div class="space-y-5">
                    <FormField label="Store name" :error="errors?.name">
                        <Input v-model="form.name" placeholder="Your store name" :error="!!errors?.name">
                            <template #leading><Store :size="14" /></template>
                        </Input>
                    </FormField>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <FormField label="Email" :error="errors?.email">
                            <Input v-model="form.email" type="email" placeholder="hello@example.com" :error="!!errors?.email">
                                <template #leading><Mail :size="14" /></template>
                            </Input>
                        </FormField>
                        <FormField label="Phone" :error="errors?.phone">
                            <Input v-model="form.phone" placeholder="+91 98765 43210" :error="!!errors?.phone">
                                <template #leading><Phone :size="14" /></template>
                            </Input>
                        </FormField>
                    </div>
                    <FormField label="City" :error="errors?.city">
                        <Input v-model="form.city" placeholder="Mumbai" :error="!!errors?.city">
                            <template #leading><MapPin :size="14" /></template>
                        </Input>
                    </FormField>
                    <FormField label="Address" :error="errors?.address">
                        <Textarea v-model="form.address" :rows="3" placeholder="Street, area, PIN…" :error="!!errors?.address" />
                    </FormField>
                </div>
            </Card>

            <!-- Social -->
            <Card mode="flat" title="Social links" subtitle="Optional — footer links for social presence.">
                <div class="space-y-5">
                    <FormField label="Facebook" :error="errors?.['social_links.facebook']">
                        <Input
                            v-model="form.social_links.facebook"
                            type="url"
                            placeholder="https://facebook.com/username"
                            :error="!!errors?.['social_links.facebook']"
                        >
                            <template #leading>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M22 12a10 10 0 10-11.5 9.87v-6.98H8v-2.89h2.5V9.79c0-2.5 1.5-3.88 3.77-3.88 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56v1.87h2.77l-.44 2.89h-2.33v6.98A10 10 0 0022 12z"/>
                                </svg>
                            </template>
                        </Input>
                    </FormField>
                    <FormField label="Twitter / X" :error="errors?.['social_links.twitter']">
                        <Input
                            v-model="form.social_links.twitter"
                            type="url"
                            placeholder="https://twitter.com/username"
                            :error="!!errors?.['social_links.twitter']"
                        >
                            <template #leading>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                                </svg>
                            </template>
                        </Input>
                    </FormField>
                    <FormField label="LinkedIn" :error="errors?.['social_links.linkedin']">
                        <Input
                            v-model="form.social_links.linkedin"
                            type="url"
                            placeholder="https://linkedin.com/in/username"
                            :error="!!errors?.['social_links.linkedin']"
                        >
                            <template #leading>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M20.45 20.45h-3.55v-5.57c0-1.33-.03-3.04-1.85-3.04-1.85 0-2.13 1.45-2.13 2.95v5.66H9.36V9h3.41v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.45zM5.34 7.43a2.06 2.06 0 11.01-4.13 2.06 2.06 0 010 4.13zM7.12 20.45H3.56V9h3.56z"/>
                                </svg>
                            </template>
                        </Input>
                    </FormField>
                    <FormField label="Instagram" :error="errors?.['social_links.instagram']">
                        <Input
                            v-model="form.social_links.instagram"
                            type="url"
                            placeholder="https://instagram.com/username"
                            :error="!!errors?.['social_links.instagram']"
                        >
                            <template #leading>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M12 2.16c3.2 0 3.58.02 4.85.07 3.25.15 4.77 1.69 4.92 4.92.06 1.27.07 1.65.07 4.85s-.02 3.58-.07 4.85c-.15 3.23-1.66 4.77-4.92 4.92-1.27.06-1.64.07-4.85.07s-3.58-.02-4.85-.07c-3.26-.15-4.77-1.7-4.92-4.92-.06-1.27-.07-1.64-.07-4.85s.02-3.58.07-4.85c.15-3.23 1.66-4.77 4.92-4.92C8.42 2.18 8.8 2.16 12 2.16zM12 0C8.74 0 8.33.01 7.05.07 2.7.27.27 2.69.07 7.05.01 8.33 0 8.74 0 12s.01 3.67.07 4.95c.2 4.36 2.62 6.78 6.98 6.98C8.33 23.99 8.74 24 12 24s3.67-.01 4.95-.07c4.35-.2 6.78-2.62 6.98-6.98.06-1.28.07-1.69.07-4.95s-.01-3.67-.07-4.95c-.2-4.35-2.62-6.78-6.98-6.98C15.67.01 15.26 0 12 0zm0 5.84a6.16 6.16 0 100 12.32 6.16 6.16 0 000-12.32zM12 16a4 4 0 110-8 4 4 0 010 8zm6.41-11.85a1.44 1.44 0 100 2.88 1.44 1.44 0 000-2.88z"/>
                                </svg>
                            </template>
                        </Input>
                    </FormField>
                    <FormField
                        label="WhatsApp"
                        hint="Number for the floating chat button on the storefront. Leave blank to use the contact Phone above. 10 digits = India; use full international format otherwise (e.g. +15551234567)."
                        :error="errors?.['social_links.whatsapp']"
                    >
                        <Input
                            v-model="form.social_links.whatsapp"
                            type="tel"
                            placeholder="+91 98765 43210"
                            :error="!!errors?.['social_links.whatsapp']"
                        >
                            <template #leading>
                                <svg width="14" height="14" viewBox="0 0 32 32" fill="currentColor" aria-hidden="true">
                                    <path d="M19.11 17.79c-.29-.15-1.71-.85-1.98-.94-.27-.1-.46-.15-.66.15-.19.29-.75.94-.92 1.13-.17.19-.34.22-.63.07-.29-.15-1.22-.45-2.32-1.43-.86-.77-1.44-1.72-1.61-2-.17-.29-.02-.45.13-.6.13-.13.29-.34.44-.51.15-.17.19-.29.29-.48.1-.19.05-.36-.02-.51-.07-.15-.66-1.59-.9-2.18-.24-.57-.48-.5-.66-.51l-.56-.01c-.19 0-.51.07-.78.36-.27.29-1.02 1-1.02 2.44 0 1.44 1.05 2.83 1.19 3.02.15.19 2.07 3.16 5.02 4.43.7.3 1.25.48 1.68.62.7.22 1.34.19 1.85.12.56-.08 1.71-.7 1.96-1.38.24-.68.24-1.26.17-1.38-.07-.12-.27-.19-.56-.34zM16.04 27.02h-.01c-1.87 0-3.71-.5-5.31-1.45l-.38-.23-3.94 1.03 1.05-3.84-.25-.4A11.05 11.05 0 0 1 5.02 16C5.02 9.94 9.98 5 16.04 5c2.94 0 5.7 1.15 7.78 3.23A10.94 10.94 0 0 1 27.05 16c0 6.06-4.96 11.02-11.01 11.02zm9.37-20.4A13.14 13.14 0 0 0 16.04 3C8.83 3 3.02 8.81 3.02 16.01c0 2.31.6 4.55 1.74 6.53L3 30l7.66-2.01a13.13 13.13 0 0 0 5.38 1.16h.01C23.25 29.15 29 23.34 29 16.13c0-3.55-1.4-6.85-3.59-9.51z"/>
                                </svg>
                            </template>
                        </Input>
                    </FormField>
                </div>
            </Card>

            <!-- Branding -->
            <Card mode="flat" title="Branding" subtitle="Logo shown in the header and the icon (favicon).">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <FormField label="Icon (favicon)" :error="errors?.icon">
                        <label
                            for="icon-dropzone"
                            class="group flex min-h-[9rem] w-full cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-200 dark:border-white/[0.1] bg-gray-50/50 dark:bg-white/[0.02] p-4 transition-colors hover:border-gray-400 dark:hover:border-white/[0.2] hover:bg-gray-50 dark:hover:bg-white/[0.04]"
                        >
                            <input
                                id="icon-dropzone"
                                @change="handleIconFileChange"
                                type="file"
                                ref="iconInput"
                                accept="image/png,image/svg+xml,image/x-icon,image/vnd.microsoft.icon"
                                class="sr-only"
                            />
                            <div v-if="previewIcon" class="flex w-full flex-col items-center gap-2">
                                <img :src="previewIcon" class="max-h-24 rounded-lg object-contain bg-white p-2 border border-gray-200 dark:border-white/[0.06]" alt="Icon preview" />
                                <span class="text-[12px] font-medium text-gray-700 dark:text-gray-300 group-hover:text-gray-900 dark:group-hover:text-white/95 transition-colors">
                                    Click to replace
                                </span>
                            </div>
                            <div v-else class="flex flex-col items-center text-center">
                                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400 mb-2 group-hover:bg-gray-200 dark:group-hover:bg-white/[0.1] transition-colors">
                                    <UploadCloud :size="18" />
                                </span>
                                <p class="text-body-strong text-gray-800 dark:text-white/90">Add icon</p>
                                <p class="mt-1 text-caption">PNG, SVG, or ICO · 32×32 or 64×64</p>
                            </div>
                        </label>
                        <button
                            v-if="previewIcon"
                            type="button"
                            @click="removeIcon"
                            class="mt-2 inline-flex items-center gap-1 text-[12px] font-medium text-error-600 dark:text-error-400 hover:underline focus-ring rounded"
                        >
                            <X :size="12" />
                            Remove icon
                        </button>
                    </FormField>

                    <FormField label="Logo" :error="errors?.image">
                        <label
                            for="logo-dropzone"
                            class="group flex min-h-[9rem] w-full cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-200 dark:border-white/[0.1] bg-gray-50/50 dark:bg-white/[0.02] p-4 transition-colors hover:border-gray-400 dark:hover:border-white/[0.2] hover:bg-gray-50 dark:hover:bg-white/[0.04]"
                        >
                            <input
                                id="logo-dropzone"
                                @change="handleFileChange"
                                type="file"
                                ref="fileInput"
                                accept="image/jpeg,image/png,image/webp,image/svg+xml"
                                class="sr-only"
                            />
                            <div v-if="previewImage" class="flex w-full flex-col items-center gap-2">
                                <img :src="previewImage" class="max-h-24 rounded-lg object-contain bg-white p-2 border border-gray-200 dark:border-white/[0.06]" alt="Logo preview" />
                                <span class="text-[12px] font-medium text-gray-700 dark:text-gray-300 group-hover:text-gray-900 dark:group-hover:text-white/95 transition-colors">
                                    Click to replace
                                </span>
                            </div>
                            <div v-else class="flex flex-col items-center text-center">
                                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400 mb-2 group-hover:bg-gray-200 dark:group-hover:bg-white/[0.1] transition-colors">
                                    <UploadCloud :size="18" />
                                </span>
                                <p class="text-body-strong text-gray-800 dark:text-white/90">Add logo</p>
                                <p class="mt-1 text-caption">PNG, JPG, SVG, or WebP</p>
                            </div>
                        </label>
                        <button
                            v-if="previewImage"
                            type="button"
                            @click="removeImage"
                            class="mt-2 inline-flex items-center gap-1 text-[12px] font-medium text-error-600 dark:text-error-400 hover:underline focus-ring rounded"
                        >
                            <X :size="12" />
                            Remove logo
                        </button>
                    </FormField>

                    <FormField
                        label="Page banner"
                        hint="One image for the banner across every page except the home page — shop, about, contact, cart, blog and the rest. Wide artwork works best, around 1774×696."
                        :error="errors?.page_hero_image"
                    >
                        <label
                            for="page-hero-dropzone"
                            class="group flex min-h-[9rem] w-full cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-200 dark:border-white/[0.1] bg-gray-50/50 dark:bg-white/[0.02] p-4 transition-colors hover:border-gray-400 dark:hover:border-white/[0.2] hover:bg-gray-50 dark:hover:bg-white/[0.04]"
                        >
                            <input
                                id="page-hero-dropzone"
                                @change="handlePageHeroChange"
                                type="file"
                                ref="pageHeroInput"
                                accept="image/jpeg,image/png,image/webp"
                                class="sr-only"
                            />
                            <div v-if="previewPageHero" class="flex w-full flex-col items-center gap-2">
                                <img :src="previewPageHero" class="max-h-28 w-full rounded-lg object-cover border border-gray-200 dark:border-white/[0.06]" alt="Page banner preview" />
                                <span class="text-[12px] font-medium text-gray-700 dark:text-gray-300 group-hover:text-gray-900 dark:group-hover:text-white/95 transition-colors">
                                    Click to replace
                                </span>
                            </div>
                            <div v-else class="flex flex-col items-center text-center">
                                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400 mb-2 group-hover:bg-gray-200 dark:group-hover:bg-white/[0.1] transition-colors">
                                    <UploadCloud :size="18" />
                                </span>
                                <p class="text-body-strong text-gray-800 dark:text-white/90">Add page banner</p>
                                <p class="mt-1 text-caption">JPG, PNG or WebP · up to 2 MB · the theme's own artwork is used until you add one</p>
                            </div>
                        </label>
                        <button
                            v-if="previewPageHero"
                            type="button"
                            @click="removePageHero"
                            class="mt-2 inline-flex items-center gap-1 text-[12px] font-medium text-error-600 dark:text-error-400 hover:underline focus-ring rounded"
                        >
                            <X :size="12" />
                            Remove banner
                        </button>
                    </FormField>
                </div>
            </Card>

            <!-- Storefront content: editable copy blocks shown on the public site -->
            <Card mode="flat" title="Storefront content" subtitle="Copy shown across the public storefront — product pages, top-bar, and homepage features.">
                <div class="space-y-8">
                    <!-- Product-detail policy strings -->
                    <div>
                        <div class="mb-4 flex items-center gap-2">
                            <Package :size="14" class="text-gray-400" />
                            <h3 class="text-h3 text-gray-900 dark:text-white/95">Product detail policies</h3>
                        </div>
                        <div class="space-y-5">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <FormField label="Delivery estimate" hint="Shown next to the ‘Estimated Delivery’ line.">
                                    <Input v-model="form.storefront_content.product.delivery_estimate" placeholder="3–5 business days across India" />
                                </FormField>
                                <FormField label="Return window" hint="Short one-liner shown below the delivery estimate.">
                                    <Input v-model="form.storefront_content.product.return_window" placeholder="Return within 14 days of delivery." />
                                </FormField>
                            </div>
                            <FormField label="Shipping copy" hint="Longer paragraph shown in the product-page ‘Shipping & Returns’ tab.">
                                <Textarea v-model="form.storefront_content.product.shipping_copy" :rows="3" placeholder="One flat delivery fee across India…" />
                            </FormField>
                            <FormField label="Return policy copy" hint="Longer paragraph shown in the product-page ‘Return Policies’ tab and delivery modal.">
                                <Textarea v-model="form.storefront_content.product.return_copy" :rows="4" placeholder="If it's not a hit with the little one…" />
                            </FormField>
                        </div>
                    </div>

                    <!-- Top-bar messages -->
                    <div class="pt-6 border-t border-gray-100 dark:border-white/[0.06]">
                        <div class="mb-3 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Megaphone :size="14" class="text-gray-400" />
                                <h3 class="text-h3 text-gray-900 dark:text-white/95">Top-bar messages</h3>
                            </div>
                            <Button variant="secondary" size="sm" type="button" @click="addTopbarMessage" :disabled="form.storefront_content.topbar_messages.length >= 6">
                                <template #leading><Plus :size="13" /></template>
                                Add message
                            </Button>
                        </div>
                        <p class="mb-3 text-[12px] text-gray-500 dark:text-gray-400">Short promo lines that rotate at the top of every storefront page. Max 6.</p>
                        <div class="space-y-2">
                            <div
                                v-for="(msg, i) in form.storefront_content.topbar_messages"
                                :key="i"
                                class="flex items-center gap-2"
                            >
                                <span class="w-6 text-center text-[11.5px] font-semibold text-gray-400 num-tabular">{{ i + 1 }}.</span>
                                <Input
                                    v-model="form.storefront_content.topbar_messages[i]"
                                    placeholder="e.g. Free shipping on orders over ₹999"
                                    class="flex-1"
                                />
                                <button
                                    type="button"
                                    @click="removeTopbarMessage(i)"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 hover:text-error-600 hover:bg-error-50 dark:hover:bg-error-500/10 transition-colors focus-ring"
                                    :aria-label="`Remove message ${i + 1}`"
                                >
                                    <Trash2 :size="14" />
                                </button>
                            </div>
                            <p v-if="!form.storefront_content.topbar_messages.length" class="text-[12.5px] text-gray-500 dark:text-gray-400 italic">
                                No messages yet — add one to show a rotating banner above the header.
                            </p>
                        </div>
                    </div>

                    <!-- Home feature grid -->
                    <div class="pt-6 border-t border-gray-100 dark:border-white/[0.06]">
                        <div class="mb-3 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Sparkles :size="14" class="text-gray-400" />
                                <h3 class="text-h3 text-gray-900 dark:text-white/95">Homepage feature grid</h3>
                            </div>
                            <Button variant="secondary" size="sm" type="button" @click="addFeature" :disabled="form.storefront_content.features.length >= 8">
                                <template #leading><Plus :size="13" /></template>
                                Add feature
                            </Button>
                        </div>
                        <p class="mb-3 text-[12px] text-gray-500 dark:text-gray-400">Trust badges displayed on the home page. Icon uses your theme's icon class (e.g. <code class="bg-gray-100 dark:bg-white/[0.06] px-1 rounded text-[11px]">icon-shield-check</code>).</p>
                        <div class="space-y-3">
                            <div
                                v-for="(feat, i) in form.storefront_content.features"
                                :key="i"
                                class="rounded-xl border border-gray-200 dark:border-white/[0.06] p-3"
                            >
                                <div class="mb-2 flex items-center justify-between">
                                    <span class="text-eyebrow text-gray-500 dark:text-gray-400">Feature {{ i + 1 }}</span>
                                    <button
                                        type="button"
                                        @click="removeFeature(i)"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-md text-gray-400 hover:text-error-600 hover:bg-error-50 dark:hover:bg-error-500/10 transition-colors focus-ring"
                                        :aria-label="`Remove feature ${i + 1}`"
                                    >
                                        <Trash2 :size="13" />
                                    </button>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-[160px_1fr] gap-3">
                                    <FormField label="Icon class">
                                        <Input v-model="feat.icon" placeholder="icon-shield-check" class="font-mono" />
                                    </FormField>
                                    <FormField label="Title">
                                        <Input v-model="feat.title" placeholder="Safety First" />
                                    </FormField>
                                </div>
                                <FormField label="Description" class="mt-3">
                                    <Textarea v-model="feat.description" :rows="2" placeholder="Short benefit copy shown under the title." />
                                </FormField>
                            </div>
                            <p v-if="!form.storefront_content.features.length" class="text-[12.5px] text-gray-500 dark:text-gray-400 italic">
                                No features yet — add one to show a trust-badge grid on the home page.
                            </p>
                        </div>
                    </div>

                    <!-- Order tracking -->
                    <div class="pt-6 border-t border-gray-100 dark:border-white/[0.06]">
                        <div class="mb-3 flex items-center gap-2">
                            <Package :size="14" class="text-gray-400" />
                            <h3 class="text-h3 text-gray-900 dark:text-white/95">Order tracking</h3>
                        </div>
                        <p class="mb-3 text-[12px] text-gray-500 dark:text-gray-400">
                            Offers order tracking to shoppers. Turning it off removes it from the whole storefront — the menu,
                            the footer, product pages, the order confirmation, the customer's profile and the links in order
                            emails — and the tracking pages themselves stop responding, so nothing dead-ends. Leave it on if
                            customers should be able to track their own orders without contacting you.
                        </p>
                        <label
                            class="flex items-center gap-3 rounded-xl border border-gray-200 dark:border-white/[0.06] p-3 cursor-pointer hover:border-gray-300 dark:hover:border-white/[0.12] transition-colors"
                            :class="{ 'opacity-60': !form.storefront_content.show_track_order }"
                        >
                            <input
                                type="checkbox"
                                v-model="form.storefront_content.show_track_order"
                                class="h-4 w-4 accent-brand-600 cursor-pointer"
                                aria-label="Show track order links on the storefront"
                            />
                            <span class="text-body text-gray-800 dark:text-white/85">
                                Offer order tracking on the storefront
                            </span>
                        </label>
                    </div>

                    <!-- Payment badges -->
                    <div class="pt-6 border-t border-gray-100 dark:border-white/[0.06]">
                        <div class="mb-3 flex items-center gap-2">
                            <CreditCard :size="14" class="text-gray-400" />
                            <h3 class="text-h3 text-gray-900 dark:text-white/95">Footer payment badges</h3>
                        </div>
                        <p class="mb-3 text-[12px] text-gray-500 dark:text-gray-400">
                            Toggle which payment method logos show in the storefront footer. Turn off any method you don't actually accept — otherwise the badge is misleading.
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <label
                                v-for="(pm, i) in form.storefront_content.payment_methods"
                                :key="pm.key || i"
                                class="flex items-center gap-3 rounded-xl border border-gray-200 dark:border-white/[0.06] p-3 cursor-pointer hover:border-gray-300 dark:hover:border-white/[0.12] transition-colors"
                                :class="{ 'opacity-60': !pm.enabled }"
                            >
                                <input
                                    type="checkbox"
                                    v-model="pm.enabled"
                                    class="h-4 w-4 accent-brand-600 cursor-pointer"
                                    :aria-label="`Enable ${pm.label}`"
                                />
                                <img
                                    :src="`/client/images/payment/${pm.image}`"
                                    :alt="pm.label"
                                    class="h-6 w-auto object-contain"
                                    onerror="this.style.display='none'"
                                />
                                <span class="text-body text-gray-800 dark:text-white/85">{{ pm.label }}</span>
                            </label>
                        </div>
                        <p v-if="!form.storefront_content.payment_methods.length" class="text-[12.5px] text-gray-500 dark:text-gray-400 italic mt-2">
                            No payment methods configured.
                        </p>
                    </div>

                    <!-- About page -->
                    <div class="pt-6 border-t border-gray-100 dark:border-white/[0.06]">
                        <div class="mb-3 flex items-center gap-2">
                            <BookOpen :size="14" class="text-gray-400" />
                            <h3 class="text-h3 text-gray-900 dark:text-white/95">About page</h3>
                        </div>
                        <div class="space-y-5">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <FormField label="Page banner title" hint="Shown in the breadcrumb hero section.">
                                    <Input v-model="form.storefront_content.about.page_title" placeholder="About Our Store" />
                                </FormField>
                                <FormField label="Main heading" hint="Big headline next to the image.">
                                    <Input v-model="form.storefront_content.about.main_title" placeholder="Thoughtful toys for growing minds." />
                                </FormField>
                            </div>
                            <FormField label="CTA button label" hint="Text on the button that links to the shop.">
                                <Input v-model="form.storefront_content.about.cta_label" placeholder="Shop toys" class="max-w-xs" />
                            </FormField>

                            <div>
                                <div class="mb-2 flex items-center justify-between">
                                    <span class="text-eyebrow text-gray-500 dark:text-gray-400">Tabs · shown below the main heading</span>
                                    <Button variant="secondary" size="sm" type="button" @click="addAboutTab" :disabled="form.storefront_content.about.tabs.length >= 6">
                                        <template #leading><Plus :size="13" /></template>
                                        Add tab
                                    </Button>
                                </div>
                                <div class="space-y-3">
                                    <div
                                        v-for="(tab, i) in form.storefront_content.about.tabs"
                                        :key="i"
                                        class="rounded-xl border border-gray-200 dark:border-white/[0.06] p-3"
                                    >
                                        <div class="mb-2 flex items-center justify-between">
                                            <span class="text-eyebrow text-gray-500 dark:text-gray-400">Tab {{ i + 1 }}</span>
                                            <button
                                                type="button"
                                                @click="removeAboutTab(i)"
                                                class="inline-flex h-7 w-7 items-center justify-center rounded-md text-gray-400 hover:text-error-600 hover:bg-error-50 dark:hover:bg-error-500/10 transition-colors focus-ring"
                                                :aria-label="`Remove tab ${i + 1}`"
                                            >
                                                <Trash2 :size="13" />
                                            </button>
                                        </div>
                                        <FormField label="Tab label">
                                            <Input v-model="tab.label" placeholder="e.g. Our Story" />
                                        </FormField>
                                        <FormField label="Tab content" class="mt-3">
                                            <Textarea v-model="tab.content" :rows="3" placeholder="Paragraph shown when this tab is active." />
                                        </FormField>
                                    </div>
                                    <p v-if="!form.storefront_content.about.tabs.length" class="text-[12.5px] text-gray-500 dark:text-gray-400 italic">
                                        No tabs yet — add one to show a tabbed content block on the About page.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Card>

            <div class="flex items-center justify-end gap-2">
                <Button variant="primary" size="sm" type="submit" :loading="form.processing">Save changes</Button>
            </div>
        </form>
    </SettingsShell>
</template>

<script>
import { Head, router, usePage } from '@inertiajs/vue3';
import Layout from '@/Layout/MainLayout.vue';
import SettingsShell from '@/Components/SettingsShell.vue';
import { Card, Button, Input, Textarea, FormField } from '@/Components/ui';
import {
    Store, Mail, Phone, MapPin, UploadCloud, X,
    Package, Megaphone, Sparkles, Plus, Trash2, BookOpen, CreditCard,
} from '@lucide/vue';

function normalizeContent(sc) {
    const src = sc && typeof sc === 'object' ? sc : {};
    return {
        // Defaults to on: an absent key means the admin has never touched this
        // toggle, and the storefront has always shown the track links.
        show_track_order: src?.show_track_order !== false,
        product: {
            delivery_estimate: src?.product?.delivery_estimate || '',
            return_window: src?.product?.return_window || '',
            shipping_copy: src?.product?.shipping_copy || '',
            return_copy: src?.product?.return_copy || '',
        },
        topbar_messages: Array.isArray(src?.topbar_messages) ? [...src.topbar_messages] : [],
        features: Array.isArray(src?.features)
            ? src.features.map((f) => ({
                icon: f?.icon || '',
                title: f?.title || '',
                description: f?.description || '',
            }))
            : [],
        payment_methods: Array.isArray(src?.payment_methods)
            ? src.payment_methods.map((p) => ({
                key: p?.key || '',
                label: p?.label || '',
                image: p?.image || '',
                enabled: !!p?.enabled,
            }))
            : [],
        about: {
            page_title: src?.about?.page_title || '',
            main_title: src?.about?.main_title || '',
            cta_label: src?.about?.cta_label || '',
            tabs: Array.isArray(src?.about?.tabs)
                ? src.about.tabs.map((t) => ({ label: t?.label || '', content: t?.content || '' }))
                : [],
        },
    };
}

export default {
    layout: Layout,
    components: {
        Head, SettingsShell,
        Card, Button, Input, Textarea, FormField,
        Store, Mail, Phone, MapPin, UploadCloud, X,
        Package, Megaphone, Sparkles, Plus, Trash2, BookOpen, CreditCard,
    },
    props: {
        setting: Object,
    },
    data() {
        return {
            form: {
                name: this.setting?.name || '',
                email: this.setting?.email || '',
                phone: this.setting?.phone || '',
                city: this.setting?.city || '',
                address: this.setting?.address || '',
                social_links: this.setting?.social_links || {},
                image: this.setting?.image || '',
                icon: this.setting?.icon || '',
                page_hero_image: this.setting?.page_hero_image || '',
                storefront_content: normalizeContent(this.setting?.storefront_content),
                processing: false,
                errors: {},
            },
            previewImage: null,
            previewIcon: null,
            previewPageHero: null,
        };
    },
    computed: {
        errors() { return usePage().props.errors || {}; },
    },
    mounted() {
        this.previewImage = this.setting?.image ? `/storage/setting/${this.setting.image}` : null;
        this.previewIcon = this.setting?.icon ? `/storage/setting/${this.setting.icon}` : null;
        this.previewPageHero = this.setting?.page_hero_image ? `/storage/setting/${this.setting.page_hero_image}` : null;
    },
    methods: {
        addTopbarMessage() {
            if (this.form.storefront_content.topbar_messages.length >= 6) return;
            this.form.storefront_content.topbar_messages.push('');
        },
        removeTopbarMessage(i) {
            this.form.storefront_content.topbar_messages.splice(i, 1);
        },
        addFeature() {
            if (this.form.storefront_content.features.length >= 8) return;
            this.form.storefront_content.features.push({ icon: '', title: '', description: '' });
        },
        removeFeature(i) {
            this.form.storefront_content.features.splice(i, 1);
        },
        addAboutTab() {
            if (this.form.storefront_content.about.tabs.length >= 6) return;
            this.form.storefront_content.about.tabs.push({ label: '', content: '' });
        },
        removeAboutTab(i) {
            this.form.storefront_content.about.tabs.splice(i, 1);
        },
        handleFileChange(e) {
            const file = e.target.files[0];
            if (file) {
                this.form.image = file;
                this.previewImage = URL.createObjectURL(file);
            }
        },
        removeImage() {
            this.form.image = null;
            this.previewImage = null;
            if (this.$refs.fileInput) this.$refs.fileInput.value = '';
        },
        handleIconFileChange(e) {
            const file = e.target.files[0];
            if (file) {
                this.form.icon = file;
                this.previewIcon = URL.createObjectURL(file);
            }
        },
        removeIcon() {
            this.form.icon = null;
            this.previewIcon = null;
            if (this.$refs.iconInput) this.$refs.iconInput.value = '';
        },
        handlePageHeroChange(e) {
            const file = e.target.files[0];
            if (file) {
                this.form.page_hero_image = file;
                this.previewPageHero = URL.createObjectURL(file);
            }
        },
        removePageHero() {
            // Cleared, not deleted-and-left-blank: the storefront falls back to
            // the artwork bundled with the theme.
            this.form.page_hero_image = null;
            this.previewPageHero = null;
            if (this.$refs.pageHeroInput) this.$refs.pageHeroInput.value = '';
        },
        submit() {
            const id = this.setting?.id;
            if (!id) {
                alert('Setting record is missing. Please reload the page.');
                return;
            }
            this.form._method = 'put';
            this.form.processing = true;
            this.form.errors = {};

            router.post(route('admin.setting.update', id), this.form, {
                forceFormData: true,
                onError: (errors) => {
                    this.form.errors = errors;
                    this.form.processing = false;
                },
                onFinish: () => {
                    this.form.processing = false;
                },
            });
        },
    },
};
</script>
