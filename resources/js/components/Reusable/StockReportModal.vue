<template>
  <div class="modal fade modal-fullscreen modal-backdrop-transparent" id="pdfModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <!-- Header -->
        <div class="modal-header">
          <h5 class="modal-title">{{ title }}</h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close" @click="close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <!-- Body -->
        <div class="modal-body p-0 position-relative" style="height: 80vh;">
          <div v-if="isLoading" class="position-absolute top-0 start-0 w-100 h-100 d-flex justify-content-center align-items-center" style="z-index:10;">
            <div class="text-center">
              <i class="spinner-border spinner-border-lg text-primary"></i>
              <p class="mt-2 mb-0">Generating Report...</p>
            </div>
          </div>

          <iframe v-if="pdfUrl" :src="pdfUrl" style="width:100%; height:100%; border:0;" @load="isLoading = false"></iframe>
        </div>

        <!-- Footer -->
        <div class="modal-footer position-relative">
          <button class="btn btn-primary" :disabled="isDownloading" @click="downloadPdf">
            <i v-if="isDownloading" class="spinner-border spinner-border-sm me-1"></i>
            {{ isDownloading ? 'Downloading...' : 'Download PDF' }}
          </button>
          <div v-if="isDownloading" class="position-absolute top-0 start-0 w-100 h-100 d-flex justify-content-center align-items-center" style="z-index:20; background: rgba(255,255,255,0.5);"></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'

const props = defineProps({
  title: { type: String, default: 'PDF Viewer' }
})

const pdfUrl = ref(null)
const isLoading = ref(false)
const isDownloading = ref(false)

// Open modal with POST request
const open = async (url, data = {}) => {
  try {
    isLoading.value = true
    pdfUrl.value = null
    $('#pdfModal').modal('show')

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    const res = await axios.post(url, data, {
      responseType: 'blob',
      headers: { 'X-CSRF-TOKEN': csrfToken }
    })

    const blob = new Blob([res.data], { type: 'application/pdf' })
    pdfUrl.value = URL.createObjectURL(blob)
  } catch (err) {
    console.error(err)
    alert('Failed to load PDF')
  } finally {
    isLoading.value = false
  }
}

// Download current PDF
const downloadPdf = async () => {
  if (!pdfUrl.value) return
  try {
    isDownloading.value = true
    const link = document.createElement('a')
    link.href = pdfUrl.value
    link.download = 'Stock_Report.pdf'
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  } finally {
    isDownloading.value = false
  }
}

// Close modal
const close = () => {
  $('#pdfModal').modal('hide')
  if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value)
  pdfUrl.value = null
}

defineExpose({ open })
</script>
