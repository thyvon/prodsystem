<template>
  <div class="modal fade" tabindex="-1" role="dialog" ref="modalRef">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ title }}</h5>
          <button type="button" class="close" @click="close">&times;</button>
        </div>
        <div class="modal-body p-0" style="height:80vh;">
          <!-- PDF Viewer -->
          <iframe v-if="isPdf" :src="pdfViewerUrl" style="width:100%; height:100%; border:none;" allowfullscreen></iframe>

          <!-- Image Viewer -->
          <div v-else-if="isImage" class="h-100 d-flex justify-content-center align-items-center bg-light p-2">
            <img :src="fileUrl" class="img-fluid" style="max-height:100%;" :alt="fileName" />
          </div>

          <!-- Fallback Viewer -->
          <iframe v-else :src="fallbackUrl" style="width:100%; height:100%; border:none;" allowfullscreen></iframe>
        </div>
        <div class="modal-footer">
          <a :href="fileUrl" target="_blank" class="btn btn-outline-primary btn-sm">
            <i class="fal fa-external-link"></i> Open
          </a>
          <a :href="fileUrl" download class="btn btn-outline-secondary btn-sm">
            <i class="fal fa-download"></i> Download
          </a>
          <button type="button" class="btn btn-secondary btn-sm" @click="close">Close</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, nextTick, watch } from 'vue'

const props = defineProps({
  fileUrl: { type: String, required: true },
  fileName: { type: String, default: '' },
  title: { type: String, default: 'File Viewer' },
  fallbackUrl: { type: String, default: '/pdfjs/sample.pdf' }
})

const modalRef = ref(null)

// Detect file type
const isPdf = computed(() => props.fileName.toLowerCase().endsWith('.pdf'))
const isImage = computed(() => /\.(jpg|jpeg|png|gif|bmp|tiff)$/i.test(props.fileName))

const pdfViewerUrl = computed(() => {
  return `/pdfjs/web/viewer.html?file=${encodeURIComponent(props.fileUrl)}`
})

const open = async () => {
  await nextTick()
  $(modalRef.value).modal('show')
}

const close = () => {
  $(modalRef.value).modal('hide')
}
</script>

<style scoped>
.modal { overflow: visible !important; }
</style>
