<template>
    <Head title="Create account" />

    <AuthLayout
        title="Create your account"
        subtitle="Get set up in under a minute."
    >
        <form @submit.prevent="submitForm" class="space-y-5">
            <FormField label="Name" required for-id="reg-name" :error="form.errors?.name">
                <Input
                    id="reg-name"
                    v-model="form.name"
                    placeholder="Your full name"
                    autocomplete="name"
                    required
                    :error="!!form.errors?.name"
                >
                    <template #leading><UserRound :size="14" /></template>
                </Input>
            </FormField>

            <FormField label="Email" required for-id="reg-email" :error="form.errors?.email">
                <Input
                    id="reg-email"
                    v-model="form.email"
                    type="email"
                    placeholder="you@example.com"
                    autocomplete="email"
                    required
                    :error="!!form.errors?.email"
                >
                    <template #leading><Mail :size="14" /></template>
                </Input>
            </FormField>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <FormField
                    label="Password"
                    required
                    for-id="reg-password"
                    :error="form.errors?.password"
                    hint="At least 8 characters."
                >
                    <PasswordInput
                        id="reg-password"
                        v-model="form.password"
                        placeholder="Choose a password"
                        autocomplete="new-password"
                        required
                        :error="!!form.errors?.password"
                    />
                </FormField>

                <FormField
                    label="Confirm"
                    required
                    for-id="reg-password-confirm"
                    :error="confirmError"
                >
                    <PasswordInput
                        id="reg-password-confirm"
                        v-model="form.password_confirmation"
                        placeholder="Re-enter password"
                        autocomplete="new-password"
                        required
                        :error="!!confirmError"
                    />
                </FormField>
            </div>

            <Checkbox v-model="form.tac">
                <span class="text-[13px] leading-5">
                    I agree to the
                    <a href="#" class="font-medium text-gray-900 dark:text-white/95 hover:underline underline-offset-2">Terms of Service</a>
                    and
                    <a href="#" class="font-medium text-gray-900 dark:text-white/95 hover:underline underline-offset-2">Privacy Policy</a>.
                </span>
            </Checkbox>

            <Button
                variant="primary"
                size="lg"
                type="submit"
                :loading="form.processing"
                :disabled="!form.tac"
                block
            >
                <template #leading><UserPlus :size="16" /></template>
                Create account
            </Button>
        </form>

        <p class="mt-6 text-center text-[13px] text-gray-600 dark:text-gray-400">
            Already have an account?
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
import { Button, Input, PasswordInput, FormField, Checkbox } from '@/Components/ui';
import { Mail, UserRound, UserPlus } from '@lucide/vue';

export default {
    components: {
        Head, Link, AuthLayout,
        Button, Input, PasswordInput, FormField, Checkbox,
        Mail, UserRound, UserPlus,
    },
    data() {
        return {
            form: useForm({
                name: '',
                email: '',
                password: '',
                password_confirmation: '',
                tac: false,
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
        submitForm() {
            this.form.transform((data) => ({ ...data, tac: data.tac ? 1 : 0 })).post(route('admin.register'), {
                onSuccess: () => this.form.reset('password', 'password_confirmation'),
            });
        },
    },
};
</script>
