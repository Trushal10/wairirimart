<template>
    <Modal
        :model-value="true"
        :title="title"
        size="sm"
        :closable="true"
        @update:modelValue="v => !v && $emit('cancelDelete')"
    >
        <div class="flex items-start gap-3">
            <span
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-error-50 text-error-600 dark:bg-error-500/12 dark:text-error-400"
                aria-hidden="true"
            >
                <TriangleAlert :size="18" />
            </span>
            <p class="text-[13.5px] leading-6 text-gray-700 dark:text-gray-300">
                {{ message }}
            </p>
        </div>

        <template #footer>
            <Button variant="secondary" size="sm" @click="$emit('cancelDelete')">
                {{ cancelLabel }}
            </Button>
            <Button variant="danger" size="sm" @click="$emit('confirmDelete')">
                <template #leading><Trash2 :size="14" /></template>
                {{ confirmLabel }}
            </Button>
        </template>
    </Modal>
</template>

<script>
import { Modal, Button } from '@/Components/ui';
import { TriangleAlert, Trash2 } from '@lucide/vue';

export default {
    components: { Modal, Button, TriangleAlert, Trash2 },
    props: {
        title: { type: String, default: 'Delete this item?' },
        message: { type: String, default: 'This action cannot be undone.' },
        confirmLabel: { type: String, default: 'Delete' },
        cancelLabel: { type: String, default: 'Cancel' },
    },
    emits: ['confirmDelete', 'cancelDelete'],
};
</script>
