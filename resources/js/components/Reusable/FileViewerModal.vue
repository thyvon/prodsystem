<script setup>
import { ref, computed, nextTick } from 'vue'

const modalRef = ref(null)
const fileUrl = ref('')
const fileName = ref('')
const component = ref(null)
const componentProps = ref({})

const props = defineProps({
  title: { type: String, default: 'File Viewer' },
  fallbackUrl: { type: String, default: '/pdfjs/sample.pdf' }
})

// Computed file types
const isPdf = computed(() => fileUrl.value && fileName.value.toLowerCase().endsWith('.pdf'))
const isImage = computed(() => /\.(jpg|jpeg|png|gif|bmp|tiff)$/i.test(fileName.value))

// PDF.js viewer URL
const pdfViewerUrl = computed(() => `/pdfjs/web/viewer.html?file=${encodeURIComponent(fileUrl.value)}`)

// Open modal with a file URL
const openModal = async (url, name) => {
  fileUrl.value = url
  fileName.value = name
  component.value = null
  componentProps.value = {}
  await nextTick()
  $(modalRef.value).modal('show')
}

// Open modal with a custom Vue component
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

// Expose methods for parent components
defineExpose({ openModal, openCustom, closeModal })
</script>

<template>
  <div class="modal fade" tabindex="-1" role="dialog" ref="modalRef">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ fileName || props.title }}</h5>
          <button type="button" class="close" @click="closeModal">&times;</button>
        </div>

        <div class="modal-body p-0" style="height:80vh;">
          <!-- Custom Vue component -->
          <component v-if="component" :is="component" v-bind="componentProps" />

          <!-- PDF viewer -->
          <iframe v-else-if="isPdf"
                  :src="pdfViewerUrl"
                  style="width:100%; height:100%; border:none;"
                  allowfullscreen>
          </iframe>

          <!-- Image viewer -->
          <div v-else-if="isImage" class="h-100 d-flex justify-content-center align-items-center bg-light p-2">
            <img :src="fileUrl" class="img-fluid" style="max-height:100%;" :alt="fileName" />
          </div>

          <!-- Fallback -->
          <iframe v-else
                  :src="props.fallbackUrl"
                  style="width:100%; height:100%; border:none;"
                  allowfullscreen>
          </iframe>
        </div>

        <div class="modal-footer">
          <a v-if="fileUrl" :href="fileUrl" download class="btn btn-outline-secondary btn-sm">
            <i class="fal fa-download"></i> Download
          </a>
          <button type="button" class="btn btn-secondary btn-sm" @click="closeModal">Close</button>
        </div>
      </div>
    </div>
  </div>
</template>
