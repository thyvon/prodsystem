<script setup>
import { ref, computed, nextTick } from 'vue'

/* ========================
 * Refs
 * ======================== */
const modalRef = ref(null)
const fileUrl = ref('')
const fileName = ref('')
const component = ref(null)
const componentProps = ref({})

/* ========================
 * Props
 * ======================== */
const props = defineProps({
  title: { type: String, default: 'File Viewer' },
  fallbackUrl: { type: String, default: '/pdfjs/sample.pdf' }
})

/* ========================
 * Computed
 * ======================== */
const isPdf = computed(
  () => fileUrl.value && fileName.value.toLowerCase().endsWith('.pdf')
)

const isImage = computed(
  () => /\.(jpg|jpeg|png|gif|bmp|tiff)$/i.test(fileName.value)
)

const pdfViewerUrl = computed(() =>
  `/pdfjs/web/viewer.html?file=${encodeURIComponent(fileUrl.value)}`
)

/* ========================
 * Methods
 * ======================== */

// Open modal with file URL
const openModal = async (url, name) => {
  fileUrl.value = url
  fileName.value = name
  component.value = null
  componentProps.value = {}

  await nextTick()
  $(modalRef.value).modal('show')
}

// Open modal with custom component
const openCustom = async (comp, propsData = {}, titleText = 'Preview') => {
  component.value = comp
  componentProps.value = propsData
  fileName.value = titleText
  fileUrl.value = ''

  await nextTick()
  $(modalRef.value).modal('show')
}

// Close modal
const closeModal = () => {
  $(modalRef.value).modal('hide')
}

// Download file
const downloadFile = () => {
  if (!fileUrl.value) return

  const link = document.createElement('a')
  link.href = fileUrl.value
  link.download = fileName.value || 'download'
  link.target = '_blank'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

/* ========================
 * Expose
 * ======================== */
defineExpose({
  openModal,
  openCustom,
  closeModal,
  downloadFile
})
</script>

<template>
  <div
    class="modal fade modal-fullscreen modal-backdrop-transparent"
    tabindex="-1"
    role="dialog"
    ref="modalRef"
  >
    <div class="modal-dialog" role="document">
      <div class="modal-content">

        <!-- Header -->
        <div class="modal-header bg-dark text-white d-flex justify-content-between align-items-center">
          <h5 class="modal-title">
            {{ fileName || props.title }}
          </h5>

          <div class="d-flex gap-2 align-items-center">
            <button
              type="button"
              class="close text-white"
              @click="closeModal"
            >
              &times;
            </button>
          </div>
        </div>

        <!-- Body -->
        <div class="modal-body p-0" style="height:80vh;">

          <!-- Custom Vue component -->
          <component
            v-if="component"
            :is="component"
            v-bind="componentProps"
          />

          <!-- PDF Viewer -->
          <iframe
            v-else-if="isPdf"
            :src="pdfViewerUrl"
            style="width:100%; height:100%; border:none;"
            allowfullscreen
          ></iframe>

          <!-- Image Viewer -->
          <div
            v-else-if="isImage"
            class="h-100 d-flex justify-content-center align-items-center bg-light p-2"
          >
            <img
              :src="fileUrl"
              class="img-fluid"
              style="max-height:100%;"
              :alt="fileName"
            />
          </div>

          <!-- Fallback -->
          <iframe
            v-else
            :src="props.fallbackUrl"
            style="width:100%; height:100%; border:none;"
            allowfullscreen
          ></iframe>

        </div>

        <!-- Footer -->
        <div class="modal-footer bg-dark">
            <button
              v-if="fileUrl"
              type="button"
              class="btn btn-sm btn-outline-light"
              @click="downloadFile"
            >
              <i class="fal fa-download"></i> Download
            </button>
          <button
            type="button"
            class="btn btn-secondary btn-sm"
            @click="closeModal"
          >
            Close
          </button>
        </div>

      </div>
    </div>
  </div>
</template>
