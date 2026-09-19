<template>
    <Head title="Profile" />

    <PageHeader
        title="Your profile"
        subtitle="Update your personal information and change your password."
        :crumbs="crumbs"
    />

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <!-- Identity card (left sidebar on desktop) -->
        <aside class="xl:col-span-1 space-y-6">
            <Card mode="flat">
                <div class="flex flex-col items-center text-center">
                    <span
                        class="flex h-20 w-20 items-center justify-center rounded-full text-white font-semibold text-2xl shadow-elevation-1"
                        :style="{ background: avatarColor(user?.name) }"
                        aria-hidden="true"
                    >{{ initials(user?.name) }}</span>
                    <h3 class="mt-4 text-h2 text-gray-900 dark:text-white/95 truncate max-w-full">
                        {{ user?.name || '—' }}
                    </h3>
                    <p v-if="user?.email" class="mt-0.5 text-[13px] text-gray-500 dark:text-gray-400 truncate max-w-full">
                        {{ user.email }}
                    </p>
                    <Badge variant="brand" size="sm" dot class="mt-3 capitalize">
                        {{ user?.role || 'Administrator' }}
                    </Badge>
                </div>

                <dl class="mt-6 pt-4 border-t border-gray-100 dark:border-white/[0.06] space-y-3 text-[13px]">
                    <div v-if="user?.email" class="flex items-center gap-2.5 text-gray-700 dark:text-gray-300">
                        <Mail :size="14" class="text-gray-400 shrink-0" />
                        <span class="truncate">{{ user.email }}</span>
                    </div>
                    <div v-if="user?.phone" class="flex items-center gap-2.5 text-gray-700 dark:text-gray-300">
                        <Phone :size="14" class="text-gray-400 shrink-0" />
                        <span class="num-tabular truncate">{{ user.phone }}</span>
                    </div>
                    <div v-if="user?.created_at" class="flex items-center gap-2.5 text-gray-500 dark:text-gray-400">
                        <CalendarDays :size="14" class="text-gray-400 shrink-0" />
                        <span>Joined {{ formatDate(user.created_at) }}</span>
                    </div>
                </dl>
            </Card>

            <Card mode="flat" title="Account security">
                <ul class="space-y-3 text-[13px] text-gray-600 dark:text-gray-400">
                    <li class="flex gap-2.5">
                        <ShieldCheck :size="14" class="mt-0.5 shrink-0 text-success-600 dark:text-success-400" />
                        <span>Passwords are hashed with bcrypt. Use at least 8 characters.</span>
                    </li>
                    <li class="flex gap-2.5">
                        <ShieldCheck :size="14" class="mt-0.5 shrink-0 text-success-600 dark:text-success-400" />
                        <span>Signing out ends all your active sessions.</span>
                    </li>
                    <li class="flex gap-2.5">
                        <ShieldCheck :size="14" class="mt-0.5 shrink-0 text-success-600 dark:text-success-400" />
                        <span>Changing your email won't affect customer-facing accounts.</span>
                    </li>
                </ul>
            </Card>
        </aside>

        <!-- Forms -->
        <div class="xl:col-span-2 space-y-6">
            <!-- Personal information -->
            <Card mode="flat" title="Personal information" subtitle="Basic details shown on invoices and in your account menu.">
                <form @submit.prevent="submitProfile" class="space-y-5">
                    <FormField label="Name" required for-id="pf-name" :error="profileForm.errors?.name">
                        <Input
                            id="pf-name"
                            v-model="profileForm.name"
                            placeholder="Enter your name"
                            :error="!!profileForm.errors?.name"
                            required
                        >
                            <template #leading><UserRound :size="14" /></template>
                        </Input>
                    </FormField>

                    <FormField label="Email" required for-id="pf-email" :error="profileForm.errors?.email">
                        <Input
                            id="pf-email"
                            v-model="profileForm.email"
                            type="email"
                            placeholder="you@example.com"
                            :error="!!profileForm.errors?.email"
                            required
                        >
                            <template #leading><Mail :size="14" /></template>
                        </Input>
                    </FormField>

                    <FormField label="Phone" required for-id="pf-phone" :error="profileForm.errors?.phone">
                        <Input
                            id="pf-phone"
                            v-model="profileForm.phone"
                            placeholder="+91 98765 43210"
                            :error="!!profileForm.errors?.phone"
                            required
                        >
                            <template #leading><Phone :size="14" /></template>
                        </Input>
                    </FormField>

                    <Alert v-if="profile_message" variant="success" :message="profile_message" />

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-gray-100 dark:border-white/[0.06]">
                        <Button variant="primary" size="sm" type="submit" :loading="profileForm.processing">
                            <template #leading><Save :size="14" /></template>
                            Save changes
                        </Button>
                    </div>
                </form>
            </Card>

            <!-- Password -->
            <Card mode="flat" title="Password" subtitle="Choose a strong password you don't reuse anywhere else.">
                <form @submit.prevent="submitPassword" class="space-y-5">
                    <FormField label="Current password" required for-id="pf-cur-pass" :error="passwordForm.errors?.current_password">
                        <PasswordInput
                            id="pf-cur-pass"
                            v-model="passwordForm.current_password"
                            placeholder="Enter your current password"
                            :error="!!passwordForm.errors?.current_password"
                            autocomplete="current-password"
                            required
                        />
                    </FormField>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormField label="New password" required for-id="pf-new-pass" :error="passwordForm.errors?.password" hint="At least 8 characters.">
                            <PasswordInput
                                id="pf-new-pass"
                                v-model="passwordForm.password"
                                placeholder="Enter a new password"
                                :error="!!passwordForm.errors?.password"
                                autocomplete="new-password"
                                required
                            />
                        </FormField>

                        <FormField label="Confirm password" required for-id="pf-pass-conf" :error="passwordForm.errors?.password_confirmation">
                            <PasswordInput
                                id="pf-pass-conf"
                                v-model="passwordForm.password_confirmation"
                                placeholder="Re-enter the new password"
                                :error="!!passwordForm.errors?.password_confirmation"
                                autocomplete="new-password"
                                required
                            />
                        </FormField>
                    </div>

                    <Alert v-if="passowrd_message" variant="success" :message="passowrd_message" />

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-gray-100 dark:border-white/[0.06]">
                        <Button variant="primary" size="sm" type="submit" :loading="passwordForm.processing">
                            <template #leading><KeyRound :size="14" /></template>
                            Update password
                        </Button>
                    </div>
                </form>
            </Card>
        </div>
    </div>
</template>

<script>
import { Head, useForm } from '@inertiajs/vue3';
import Layout from '@/Layout/MainLayout.vue';
import {
    PageHeader, Card, Button, Input, PasswordInput, FormField, Badge, Alert,
} from '@/Components/ui';
import {
    UserRound, Mail, Phone, CalendarDays, ShieldCheck, Save, KeyRound,
} from '@lucide/vue';

const AVATAR_COLORS = ['#3641F5', '#12B76A', '#F79009', '#0BA5EC', '#7A5AF8', '#EE46BC', '#F04438'];
function hashString(s = '') {
    let h = 0;
    for (let i = 0; i < s.length; i++) h = ((h << 5) - h + s.charCodeAt(i)) | 0;
    return Math.abs(h);
}

export default {
    layout: Layout,
    components: {
        Head,
        PageHeader, Card, Button, Input, PasswordInput, FormField, Badge, Alert,
        UserRound, Mail, Phone, CalendarDays, ShieldCheck, Save, KeyRound,
    },
    props: {
        user: Object,
        profile_message: { type: String, default: '' },
        passowrd_message: { type: String, default: '' },
    },
    data() {
        return {
            crumbs: [
                { label: 'Dashboard', href: route('admin.dashboard') },
                { label: 'Profile' },
            ],
            profileForm: useForm({
                name: this.user?.name || '',
                email: this.user?.email || '',
                phone: this.user?.phone || '',
            }),
            passwordForm: useForm({
                current_password: '',
                password: '',
                password_confirmation: '',
            }),
        };
    },
    methods: {
        submitProfile() {
            this.profileForm.patch(route('admin.profile.update'), { preserveScroll: true });
        },
        submitPassword() {
            this.passwordForm.put(route('admin.password.update'), {
                preserveScroll: true,
                onSuccess: () => this.passwordForm.reset(),
            });
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
        formatDate(v) {
            if (!v) return '—';
            const d = new Date(v);
            if (Number.isNaN(d.getTime())) return v;
            return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
        },
    },
};
</script>
