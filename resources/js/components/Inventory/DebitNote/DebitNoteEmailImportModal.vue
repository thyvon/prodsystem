<template>
  <BaseModal
    v-model="showModal"
    id="debitNoteEmailImportModal"
    title="Import Debit Note Emails"
    size="md"
    :loading="isLoading"
  >
    <template #body>
      <form @submit.prevent="submitImport">
        <div class="form-group">
          <label>Select Excel File <span class="text-danger">*</span></label>
          <div class="custom-file">
            <input
              type="file"
              class="custom-file-input"
              id="excelFile"
              accept=".xlsx,.xls,.csv"
              @change="handleFileChange"
              required
            />
            <label class="custom-file-label" for="excelFile">Choose file...</label>
          </div>
          <small class="form-text text-muted">
            File must contain columns: <strong>department, warehouse, send_to_email, cc_to_email</strong>
          </small>
          <button type="button" class="btn btn-sm btn-info mt-2" @click="exportSampleFile">
            <i class="fal fa-download mr-1"></i>
            Download Sample Template
          </button>
        </div>
      </form>
    </template>

    <template #footer>
      <button type="button" class="btn btn-secondary" @click="hideModal">Cancel</button>
      <button
        type="submit"
        class="btn btn-primary"
        @click="submitImport"
        :disabled="isSubmitting || !file"
      >
        <span v-if="isSubmitting" class="spinner-border spinner-border-sm mr-1"></span>
        Import
      </button>
    </template>
  </BaseModal>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'
import BaseModal from '@/components/Reusable/BaseModal.vue'
import { showAlert } from '@/Utils/bootbox'

const emit = defineEmits(['imported'])

const showModal = ref(false)
const isSubmitting = ref(false)
const isLoading = ref(false)
const file = ref(null)

const handleFileChange = (e) => {
  file.value = e.target.files[0] || null
}

const hideModal = () => {
  file.value = null
  showModal.value = false
}

const exportSampleFile = () => {
  const link = document.createElement('a')
  link.href = '/sampleExcel/debit_note_emails_sample.xlsx'
  link.download = 'debit_note_emails_template.xlsx'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

const submitImport = async () => {
  if (!file.value) return
  if (isSubmitting.value) return

  isSubmitting.value = true

  try {
    const formData = new FormData()
    formData.append('file', file.value)

    await axios.post('/inventory/debit-note/emails/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    emit('imported')
    hideModal()
    showAlert('Success', 'Debit Note Emails imported successfully.', 'success')
  } catch (err) {
    console.error(err)
    showAlert('Error', err.response?.data?.message || err.message || 'Import failed.', 'danger')
  } finally {
    isSubmitting.value = false
  }
}

defineExpose({ show: () => { showModal.value = true } })
</script>
