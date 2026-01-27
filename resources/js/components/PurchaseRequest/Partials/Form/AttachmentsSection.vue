<template>
  <div class="border rounded p-3 mb-4">
    <div class="form-group col-12">
      <label class="font-weight-bold">📎 Attachment</label>
      <div class="input-group mb-2">
        <input
          type="file"
          class="d-none"
          ref="attachmentInput"
          multiple
          accept=".pdf,.doc,.docx,.jpg,.png"
          @change="$emit('file-change', $event)"
        />
        <button
          type="button"
          class="btn btn-outline-secondary flex-fill"
          @click="attachmentInput?.click()"
        >
          <i class="fal fa-file-upload"></i> {{ fileLabel }}
        </button>
      </div>

      <div v-if="existingFileUrls.length">
        <small class="text-muted">Existing Files:</small>
        <div
          v-for="(file, i) in existingFileUrls"
          :key="file.id"
          class="d-flex align-items-center mb-1"
        >
          <button
            type="button"
            class="btn btn-sm btn-outline-info mr-1"
            @click="$emit('open-viewer', file.url, file.name)"
          >
            📄 {{ file.name }}
          </button>
          <button
            type="button"
            class="btn btn-sm btn-danger"
            @click="$emit('remove-file', i, true)"
          >
            <i class="fal fa-trash"></i>
          </button>
        </div>
      </div>

      <div v-if="newFiles.length">
        <small class="text-muted">New Files:</small>
        <div
          v-for="(f, i) in newFiles"
          :key="i"
          class="d-flex align-items-center mb-1"
        >
          <span class="mr-2">📄 {{ f.name }}</span>
          <button
            type="button"
            class="btn btn-sm btn-danger"
            @click="$emit('remove-new-file', i)"
          >
            <i class="fal fa-trash"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

defineProps({
  fileLabel: { type: String, required: true },
  existingFileUrls: { type: Array, required: true },
  newFiles: { type: Array, required: true },
})

defineEmits(['file-change', 'open-viewer', 'remove-file', 'remove-new-file'])

const attachmentInput = ref(null)
defineExpose({ attachmentInput })
</script>
