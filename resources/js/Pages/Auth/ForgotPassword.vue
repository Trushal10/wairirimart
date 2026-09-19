<template>
    <Head title="Forgot password" />

    <AuthLayout
        title="Reset your password"
        subtitle="Enter the email tied to your account and we'll send you a reset link."
    >
        <Alert
            v-if="status"
            variant="success"
            :message="status"
            class="mb-5"
        />

        <form @submit.prevent="submit" class="space-y-5">
            <FormField label="Email" required for-id="fp-email" :error="form.errors?.email">
                <Input
                    id="fp-email"
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

            <Button variant="primary" size="lg" type="submit" :loading="form.processing" block>
                <template #leading><Send :size="15" /></template>
                Send reset link
            </Button>
        </form>

        <p class="mt-6 text-center text-[13px] text-gray-600 dark:text-gray-400">
            Remembered it after all?
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
import { Button, Input, FormField, Alert } from '@/Components/ui';
import { Mail, Send } from '@lucide/vue';

export default {
    components: {
        Head, Link, AuthLayout,
        Button, Input, FormField, Alert,
        Mail, Send,
    },
    props: {
        status: { type: String, default: '' },
    },
    data() {
        return {
            form: useForm({ email: '' }),
        };
    },
    methods: {
        submit() {
            this.form.post(route('admin.password.email'), {
                onFinish: () => this.form.reset(),
            });
        },
    },
};
</script>
