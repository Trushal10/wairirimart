<template>
    <Head title="Sign in" />

    <AuthLayout
        title="Welcome back"
        subtitle="Enter your credentials to access the admin panel."
    >
        <form @submit.prevent="submitForm" class="space-y-5">
            <FormField label="Email" required for-id="login-email" :error="form.errors?.email">
                <Input
                    id="login-email"
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

            <FormField label="Password" required for-id="login-password" :error="form.errors?.password">
                <PasswordInput
                    id="login-password"
                    v-model="form.password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                    :error="!!form.errors?.password"
                />
            </FormField>

            <div class="flex items-center justify-between">
                <Checkbox v-model="form.remember">
                    <span class="text-[13px]">Keep me logged in</span>
                </Checkbox>
                <Link
                    :href="route('admin.password.request')"
                    class="text-[13px] font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300 hover:underline underline-offset-2 focus-ring rounded"
                >
                    Forgot password?
                </Link>
            </div>

            <Button variant="primary" size="lg" type="submit" :loading="form.processing" block>
                <template #leading><LogIn :size="16" /></template>
                Sign in
            </Button>
        </form>

        <!-- No "Sign up" link: staff self-registration is disabled in
             routes/auth.php, so route('admin.register') no longer exists and
             calling it here threw a Ziggy error that broke the whole page. -->
    </AuthLayout>
</template>

<script>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layout/AuthLayout.vue';
import { Button, Input, PasswordInput, FormField, Checkbox } from '@/Components/ui';
import { Mail, LogIn } from '@lucide/vue';

export default {
    components: {
        Head, Link, AuthLayout,
        Button, Input, PasswordInput, FormField, Checkbox,
        Mail, LogIn,
    },
    data() {
        return {
            form: useForm({
                email: '',
                password: '',
                remember: false,
            }),
        };
    },
    methods: {
        submitForm() {
            this.form.post(route('admin.login'), {
                onSuccess: () => this.form.reset('password'),
            });
        },
    },
};
</script>
