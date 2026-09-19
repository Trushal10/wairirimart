<template>
    <div class="file-uploader">
        <div
            :class="[
                'relative rounded-2xl border-2 border-dashed transition-colors',
                dragOver
                    ? 'border-brand-500 bg-brand-50/50 dark:bg-brand-500/5'
                    : error
                        ? 'border-error-300 bg-error-50/40 dark:border-error-500/40 dark:bg-error-500/5'
                        : 'border-gray-200 bg-gray-50/60 hover:border-brand-400 dark:border-gray-800 dark:bg-white/[0.02] dark:hover:border-brand-500/60',
                disabled ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer',
            ]"
            role="button"
            :tabindex="disabled ? -1 : 0"
            :aria-disabled="disabled"
            :aria-label="`${title}. ${subtitle}`"
            @click="openPicker"
            @keydown="onKeydown"
            @dragover.prevent="onDragOver"
            @dragleave.prevent="onDragLeave"
            @drop.prevent="onDrop"
        >
            <input
                ref="fileInput"
                type="file"
                class="sr-only"
                :multiple="multiple"
                :accept="accept"
                :disabled="disabled"
                @change="onInputChange"
            />

            <div class="flex flex-col items-center justify-center px-6 py-10 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-white shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700 mb-4">
                    <UploadCloud :size="24" class="text-brand-500" />
                </div>
                <h4 class="text-theme-sm font-semibold text-gray-800 dark:text-white/90">{{ title }}</h4>
                <p class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400 max-w-sm">{{ subtitle }}</p>
                <p v-if="hint" class="mt-2 text-[11px] text-gray-400 dark:text-gray-500">{{ hint }}</p>
                <button
                    type="button"
                    class="mt-4 inline-flex items-center gap-1.5 text-theme-xs font-semibold text-brand-500 hover:text-brand-600"
                    @click.stop="openPicker"
                >
                    <FolderOpen :size="14" />
                    Browse files
                </button>
            </div>
        </div>

        <p v-if="errorMessage" class="mt-2 text-theme-xs text-error-500 flex items-center gap-1.5">
            <AlertCircle :size="14" />
            {{ errorMessage }}
        </p>

        <ul v-if="files.length" class="mt-4 flex flex-col gap-2">
            <li
                v-for="(f, i) in files"
                :key="f.id"
                class="flex items-center gap-3 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-3"
            >
                <div class="h-10 w-10 shrink-0 rounded-lg bg-gray-100 dark:bg-white/[0.04] overflow-hidden flex items-center justify-center">
                    <img v-if="f.preview" :src="f.preview" :alt="f.name" class="h-full w-full object-cover" />
                    <FileIcon v-else :size="18" class="text-gray-400" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-theme-sm font-medium text-gray-800 dark:text-white/90 truncate">{{ f.name }}</p>
                    <p class="text-theme-xs text-gray-500 dark:text-gray-400">{{ formatSize(f.size) }}</p>
                    <div v-if="f.progress != null && f.progress < 100" class="mt-1 h-1 rounded-full bg-gray-100 dark:bg-white/[0.05] overflow-hidden">
                        <div class="h-full bg-brand-500 transition-all" :style="`width:${f.progress}%`"></div>
                    </div>
                </div>
                <button
                    type="button"
                    class="text-gray-400 hover:text-error-500 focus:outline-none focus:ring-2 focus:ring-error-500/30 rounded p-1"
                    :aria-label="`Remove ${f.name}`"
                    @click="removeAt(i)"
                >
                    <X :size="16" />
                </button>
            </li>
        </ul>
    </div>
</template>

<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { AlertCircle, File as FileIcon, FolderOpen, UploadCloud, X } from '@lucide/vue';

const props = defineProps({
    modelValue: { type: [Array, Object, null], default: null },
    multiple: { type: Boolean, default: false },
    accept: { type: String, default: 'image/jpeg,image/png,image/gif,image/webp,image/svg+xml' },
    maxFiles: { type: Number, default: 10 },
    maxSize: { type: Number, default: 5 * 1024 * 1024 },
    title: { type: String, default: 'Drop files here or click to browse' },
    subtitle: { type: String, default: 'PNG, JPG, WebP or SVG up to 5MB' },
    hint: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    error: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue', 'files-selected', 'error']);

const fileInput = ref(null);
const dragOver = ref(false);
const errorMessage = ref('');
const files = ref([]);

function makeEntry(file) {
    const entry = {
        id: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
        file,
        name: file.name,
        size: file.size,
        type: file.type,
        preview: '',
        progress: null,
    };
    if (file.type.startsWith('image/')) {
        entry.preview = URL.createObjectURL(file);
    }
    return entry;
}

function openPicker() {
    if (props.disabled) return;
    fileInput.value?.click();
}

function onKeydown(e) {
    if (props.disabled) return;
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        openPicker();
    }
}

function onDragOver() { if (!props.disabled) dragOver.value = true; }
function onDragLeave() { dragOver.value = false; }

function onDrop(e) {
    dragOver.value = false;
    if (props.disabled) return;
    const dropped = Array.from(e.dataTransfer?.files || []);
    addFiles(dropped);
}

function onInputChange(e) {
    const chosen = Array.from(e.target.files || []);
    addFiles(chosen);
    e.target.value = '';
}

function validate(file) {
    if (props.maxSize && file.size > props.maxSize) {
        return `${file.name} exceeds ${formatSize(props.maxSize)}`;
    }
    if (props.accept) {
        const allowed = props.accept.split(',').map(s => s.trim()).filter(Boolean);
        const match = allowed.some(pattern => {
            if (pattern.endsWith('/*')) return file.type.startsWith(pattern.slice(0, -1));
            if (pattern.startsWith('.')) return file.name.toLowerCase().endsWith(pattern.toLowerCase());
            return file.type === pattern;
        });
        if (!match) return `${file.name} is not an allowed file type`;
    }
    return '';
}

function addFiles(incoming) {
    if (!incoming.length) return;
    errorMessage.value = '';
    const accepted = [];
    for (const f of incoming) {
        const err = validate(f);
        if (err) {
            errorMessage.value = err;
            emit('error', err);
            continue;
        }
        accepted.push(makeEntry(f));
    }
    if (!accepted.length) return;
    if (!props.multiple) {
        revokeAll();
        files.value = accepted.slice(0, 1);
    } else {
        const room = Math.max(0, props.maxFiles - files.value.length);
        if (accepted.length > room) {
            errorMessage.value = `You can upload up to ${props.maxFiles} files.`;
            emit('error', errorMessage.value);
        }
        files.value = [...files.value, ...accepted.slice(0, room)];
    }
    emitValue();
}

function removeAt(i) {
    const [removed] = files.value.splice(i, 1);
    if (removed?.preview) URL.revokeObjectURL(removed.preview);
    files.value = [...files.value];
    emitValue();
}

function emitValue() {
    const raw = files.value.map(x => x.file);
    const value = props.multiple ? raw : (raw[0] || null);
    emit('update:modelValue', value);
    emit('files-selected', value);
}

function formatSize(bytes) {
    if (bytes == null) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function revokeAll() {
    files.value.forEach(f => f.preview && URL.revokeObjectURL(f.preview));
}

defineExpose({
    setProgress(id, progress) {
        const f = files.value.find(x => x.id === id);
        if (f) f.progress = progress;
    },
    clear() {
        revokeAll();
        files.value = [];
        emitValue();
    },
});

watch(() => props.modelValue, (v) => {
    if (v == null || (Array.isArray(v) && !v.length)) {
        if (files.value.length) {
            revokeAll();
            files.value = [];
        }
    }
});

onBeforeUnmount(() => revokeAll());
</script>
