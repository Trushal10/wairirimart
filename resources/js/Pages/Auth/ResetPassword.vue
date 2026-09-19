<template>
    <Head title="Set new password" />

    <AuthLayout
        title="Set a new password"
        subtitle="Choose a strong password you don't reuse anywhere else."
    >
        <Alert
            v-if="form.errors?.email"
            variant="error"
            :message="form.errors.email"
            class="mb-5"
        />

        <form @submit.prevent="submit" class="space-y-5">
            <FormField label="Email" :hint="email" for-id="rp-email">
                <Input
                    id="rp-email"
                    :model-value="email"
                    type="email"
                    disabled
                    class="opacity-70"
                >
                    <template #leading><Mail :size="14" /></template>
                </Input>
            </FormField>

            <FormField
                label="New password"
                required
                for-id="rp-password"
                :error="form.errors?.password"
                hint="At least 8 characters."
            >
                <PasswordInput
                    id="rp-password"
                    v-model="form.password"
                    placeholder="Enter your new password"
                    autocomplete="new-password"
                    required
                    :error="!!form.errors?.password"
                />
            </FormField>

            <FormField
                label="Confirm password"
                required
                for-id="rp-password-confirm"
                :error="confirmError"
            >
                <PasswordInput
                    id="rp-password-confirm"
                    v-model="form.password_confirmation"
                    placeholder="Re-enter the new password"
                    autocomplete="new-password"
                    required
                    :error="!!confirmError"
                />
            </FormField>

            <Button variant="primary" size="lg" type="submit" :loading="form.processing" block>
                <template #leading><KeyRound :size="15" /></template>
                Update password
            </Button>
        </form>

        <p class="mt-6 text-center text-[13px] text-gray-600 dark:text-gray-400">
            Remember your password?
            <Link
                :href="route('admin.login')"
                class="ml-1 font-semibold text-gray-900 hover:text-gray-700 dark:text-white/95 dark:hover:text-white hover:underline underline-offset-2 focus-ring rounded"
            >
                Sign in
            </Link>
        </p>
    </AuthLayout>
</template>

<script>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layout/AuthLayout.vue';
import { Button, Input, PasswordInput, FormField, Alert } from '@/Components/ui';
import { Mail, KeyRound } from '@lucide/vue';

export default {
    components: {
        Head, Link, AuthLayout,
        Button, Input, PasswordInput, FormField, Alert,
        Mail, KeyRound,
    },
    props: {
        token: { type: String, required: true },
        email: { type: String, required: true },
    },
    data() {
        return {
            form: useForm({
                token: this.token,
                email: this.email,
                password: '',
                password_confirmation: '',
            }),
        };
    },
    computed: {
        confirmError() {
            if (this.form.errors?.password_confirmation) return this.form.errors.password_confirmation;
            if (
                this.form.password &&
                this.form.password_confirmation &&
                this.form.password !== this.form.password_confirmation
            ) {
                return 'Passwords do not match.';
            }
            return '';
        },
    },
    methods: {
        submit() {
            this.form.post(route('admin.password.store'), {
                onFinish: () => this.form.reset('password', 'password_confirmation'),
            });
        },
    },
};
</script>
