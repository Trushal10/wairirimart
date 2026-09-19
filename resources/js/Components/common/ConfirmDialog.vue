<template>
    <Modal
        :model-value="open"
        :title="title"
        size="sm"
        :closable="!loading"
        @update:modelValue="v => !v && handleCancel()"
    >
        <div class="flex items-start gap-3">
            <span
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                :class="iconWrapClass"
                aria-hidden="true"
            >
                <component :is="iconComponent" :size="18" />
            </span>
            <div class="min-w-0 flex-1 space-y-1.5">
                <p v-if="message" class="text-[13.5px] leading-6 text-gray-700 dark:text-gray-300">
                    {{ message }}
                </p>
                <p v-if="details" class="text-h2 num-tabular text-gray-900 dark:text-white/95">
                    {{ details }}
                </p>
            </div>
        </div>

        <div v-if="askReason" class="mt-4">
            <FormField label="Reason" optional>
                <Input
                    v-model="reason"
                    maxlength="200"
                    :placeholder="reasonPlaceholder"
                    size="sm"
                />
            </FormField>
        </div>

        <template #footer>
            <Button variant="secondary" size="sm" @click="handleCancel" :disabled="loading">
                {{ cancelLabel }}
            </Button>
            <Button
                :variant="confirmVariant"
                size="sm"
                :loading="loading"
                @click="handleConfirm"
            >
                {{ confirmLabel }}
            </Button>
        </template>
    </Modal>
</template>

<script>
import { Modal, Button, Input, FormField } from '@/Components/ui';
import { TriangleAlert, AlertTriangle, Info, Sparkles } from '@lucide/vue';

export default {
    name: 'ConfirmDialog',
    components: { Modal, Button, Input, FormField },
    props: {
        open: { type: Boolean, default: false },
        title: { type: String, default: 'Are you sure?' },
        message: { type: String, default: '' },
        details: { type: String, default: '' },
        confirmLabel: { type: String, default: 'Confirm' },
        cancelLabel: { type: String, default: 'Cancel' },
        variant: {
            type: String,
            default: 'primary',
            validator: (v) => ['primary', 'danger', 'warning', 'info'].includes(v),
        },
        loading: { type: Boolean, default: false },
        askReason: { type: Boolean, default: false },
        reasonPlaceholder: { type: String, default: '' },
    },
    emits: ['confirm', 'cancel', 'update:open'],
    data() {
        return { reason: '' };
    },
    computed: {
        iconComponent() {
            return {
                danger: TriangleAlert,
                warning: AlertTriangle,
                info: Info,
                primary: Sparkles,
            }[this.variant] || Sparkles;
        },
        iconWrapClass() {
            return {
                danger:  'bg-error-50 text-error-600 dark:bg-error-500/12 dark:text-error-400',
                warning: 'bg-warning-50 text-warning-700 dark:bg-warning-500/12 dark:text-warning-400',
                info:    'bg-blue-light-50 text-blue-light-700 dark:bg-blue-light-500/12 dark:text-blue-light-400',
                primary: 'bg-gray-100 text-gray-900 dark:bg-white/[0.08] dark:text-white/95',
            }[this.variant] || 'bg-gray-100 text-gray-900 dark:bg-white/[0.08] dark:text-white/95';
        },
        confirmVariant() {
            return {
                danger:  'danger',
                warning: 'warning',
                info:    'primary',
                primary: 'primary',
            }[this.variant] || 'primary';
        },
    },
    watch: {
        open(v) {
            if (v) this.reason = '';
        },
    },
    methods: {
        handleCancel() {
            if (this.loading) return;
            this.$emit('cancel');
            this.$emit('update:open', false);
        },
        handleConfirm() {
            if (this.loading) return;
            this.$emit('confirm', { reason: this.reason });
        },
    },
};
</script>
