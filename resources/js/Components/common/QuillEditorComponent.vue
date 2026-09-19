<template>
  <div class="quill-editor-wrapper">
    <QuillEditor
      v-model:content="localContent"
      content-type="html"
      :toolbar="toolbar"
      theme="snow"
      :placeholder="placeholder || 'Write something…'"
      @update:content="updateContent"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { QuillEditor } from '@vueup/vue-quill'
import '@vueup/vue-quill/dist/vue-quill.snow.css'

// Props
const props = defineProps<{
  modelValue: object | string | null
  placeholder?: string
}>()

// Emit
const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
}>()

// Toolbar config
const toolbar = [
  [{ header: [1, 2, 3, false] }],
  ['bold', 'italic', 'underline'],
  [{ list: 'ordered' }, { list: 'bullet' }, { align: [] }],
  ['blockquote', 'code-block', 'link', 'image'],
  [{ color: [] }, 'clean']
]

// Coerce any Quill payload (Delta object, null, undefined) into an HTML string
// so the parent form.content is always a serialisable string. Without this,
// v-model:content can hand back a Delta and the backend rejects it with
// "The content field must be a string."
const toHtmlString = (val: unknown): string => {
  if (val == null) return ''
  if (typeof val === 'string') return val
  return String(val)
}

// Local content mirror
const localContent = ref<string>(toHtmlString(props.modelValue))

// Sync prop → local
watch(
  () => props.modelValue,
  (val) => {
    const next = toHtmlString(val)
    if (next !== localContent.value) {
      localContent.value = next
    }
  }
)

// Sync local → parent
const updateContent = (newContent: unknown) => {
  const html = toHtmlString(newContent)
  localContent.value = html
  emit('update:modelValue', html)
}
</script>

<!--
  Unscoped on purpose: Quill renders its toolbar and editor outside this
  component's template, so scoped styles wouldn't reach them without :deep()
  on every rule — and :deep() composes badly with the `.dark` class that sits
  on <html>. Every selector is namespaced under .quill-editor-wrapper instead.
  Values mirror Components/ui/Textarea.vue so the editor reads as one of the
  form's own fields rather than a bolted-on widget.
-->
<style>
.quill-editor-wrapper {
  width: 100%;
}

/* Toolbar — top half of the field */
.quill-editor-wrapper .ql-toolbar.ql-snow {
  border-color: #e5e7eb;
  border-top-left-radius: 0.5rem;
  border-top-right-radius: 0.5rem;
  background: #f9fafb;
  padding: 0.5rem;
}

/* Editing surface — bottom half */
.quill-editor-wrapper .ql-container.ql-snow {
  border-color: #e5e7eb;
  border-bottom-left-radius: 0.5rem;
  border-bottom-right-radius: 0.5rem;
  background: #fff;
  font-family: inherit;
  font-size: 13.5px;
  line-height: 1.5;
}

.quill-editor-wrapper .ql-editor {
  min-height: 220px;
  color: #111827;
}

.quill-editor-wrapper .ql-editor.ql-blank::before {
  color: #9ca3af;
  font-style: normal;
}

/* Focus ring, matching the 4px brand ring on other inputs */
.quill-editor-wrapper:focus-within .ql-toolbar.ql-snow,
.quill-editor-wrapper:focus-within .ql-container.ql-snow {
  border-color: var(--color-brand-400, #7592ff);
}
.quill-editor-wrapper:focus-within .ql-container.ql-snow {
  box-shadow: 0 0 0 4px rgb(70 95 255 / 0.15);
}

/* Dark mode — .dark lives on <html> */
.dark .quill-editor-wrapper .ql-toolbar.ql-snow {
  border-color: rgb(255 255 255 / 0.08);
  background: rgb(255 255 255 / 0.03);
}
.dark .quill-editor-wrapper .ql-container.ql-snow {
  border-color: rgb(255 255 255 / 0.08);
  background: rgb(255 255 255 / 0.03);
}
.dark .quill-editor-wrapper .ql-editor {
  color: rgb(255 255 255 / 0.9);
}
.dark .quill-editor-wrapper .ql-editor.ql-blank::before {
  color: rgb(255 255 255 / 0.3);
}

/* Toolbar icons/labels need explicit colours in dark mode — Quill's snow
   theme hard-codes near-black strokes that vanish on a dark surface. */
.dark .quill-editor-wrapper .ql-snow .ql-stroke {
  stroke: rgb(255 255 255 / 0.7);
}
.dark .quill-editor-wrapper .ql-snow .ql-fill {
  fill: rgb(255 255 255 / 0.7);
}
.dark .quill-editor-wrapper .ql-snow .ql-picker {
  color: rgb(255 255 255 / 0.7);
}
.dark .quill-editor-wrapper .ql-snow .ql-picker-options {
  background: #1f2937;
  border-color: rgb(255 255 255 / 0.08);
}
</style>
