<template>
  <div class="container-fluid">
    <form @submit.prevent="submitForm" enctype="multipart/form-data">
      <div class="card border mb-0 shadow">
        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
          <h4 class="mb-0 font-weight-bold">
            {{ isEditMode ? 'Edit Document Approval' : 'Create Document Approval' }}
          </h4>
          <button type="button" class="btn btn-outline-primary btn-sm" @click="goToIndex">
            <i class="fal fa-backward"></i>
          </button>
        </div>

        <div class="card-body">
          <!-- Document Details -->
          <div class="border rounded p-3 mb-4">
            <h5 class="font-weight-bold mb-3 text-primary">📄 Document Details</h5>

            <div class="form-group">
              <label class="font-weight-bold">
                Document Type <span class="text-danger">*</span>
              </label>
              <select class="form-control select2-doc-type" required>
                <option value="">Select Type</option>
              </select>
            </div>

            <div class="form-group">
              <label class="font-weight-bold">Description <span class="text-danger">*</span></label>
              <textarea v-model="form.description" class="form-control" rows="2" required></textarea>
            </div>

            <div class="form-group">
              <label class="font-weight-bold">Upload File</label>
              <div class="custom-file">
                <input type="file" class="custom-file-input" id="customFile" @change="handleFileUpload">
                <label class="custom-file-label" for="customFile">{{ fileLabel }}</label>
              </div>
              <div v-if="existingFileUrl" class="mt-1">
                <a :href="existingFileUrl" target="_blank">Current File</a>
              </div>
            </div>
          </div>

          <!-- Approvals Table -->
          <div class="border rounded p-3 mb-4">
            <h5 class="font-weight-bold mb-3 text-primary">👥 Approval Assignments</h5>
            <div class="table-responsive">
              <table class="table table-bordered table-sm table-hover">
                <thead class="thead-light">
                  <tr>
                    <th>Approval Type</th>
                    <th>Assigned User</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(approval, index) in form.approvals" :key="index">
                    <td>
                      <select v-model="approval.request_type" class="form-control" required>
                        <option value="">Select Type</option>
                        <option value="initial">Initial</option>
                        <option value="approve">Approve</option>
                        <option value="check">Check</option>
                        <option value="review">Review</option>
                        <option value="acknowledge">Acknowledge</option>
                      </select>
                    </td>
                    <td>
                      <select
                        class="form-control user-select"
                        :data-index="index"
                        v-model="approval.responder_id"
                        required
                      >
                        <option value="">Select User</option>
                      </select>
                    </td>
                    <td>
                      <button type="button" class="btn btn-danger btn-sm" @click="removeApproval(index)">
                        <i class="fal fa-trash-alt"></i> Remove
                      </button>
                    </td>
                  </tr>
                  <tr v-if="form.approvals.length === 0">
                    <td colspan="3" class="text-center text-muted">No approvals added yet</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm mt-2" @click="addApproval">
              <i class="fal fa-plus"></i> Add Approval
            </button>
          </div>

          <!-- Submit -->
          <div class="text-right">
            <button type="submit" class="btn btn-primary btn-sm" :disabled="isSubmitting">
              <span v-if="isSubmitting" class="spinner-border spinner-border-sm mr-1"></span>
              {{ isEditMode ? 'Update' : 'Create' }}
            </button>
            <button type="button" class="btn btn-secondary btn-sm" @click="goToIndex">Cancel</button>
          </div>
        </div>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, nextTick, onMounted } from 'vue'
import axios from 'axios'
import { initSelect2, destroySelect2 } from '@/Utils/select2'
import { showAlert } from '@/Utils/bootbox'

const props = defineProps({
  documentId: { type: Number, default: null }
})

const emit = defineEmits(['submitted'])

const isSubmitting = ref(false)
const isEditMode = ref(!!props.documentId)
const documentTypes = ref(['Stock Report', 'Monthly Stock Report', 'Asset Disposal', 'Monthly Procurement Report', 'Monthly Stock Summary Report'])

const form = ref({
  document_type: '',
  description: '',
  file: null,
  approvals: []
})

const existingFileUrl = ref('')
const approvalUsers = ref([])
const fileLabel = ref('Choose file')

const goToIndex = () => (window.location.href = '/digital-docs-approvals')

// Handle file upload
const handleFileUpload = (e) => {
  const file = e.target.files[0]
  form.value.file = file || null
  fileLabel.value = file ? file.name : 'Choose file'
}

// Fetch users for approval
const fetchApprovalUsers = async () => {
  try {
    const { data } = await axios.get('/api/digital-docs-approvals/get-users-for-approval')
    approvalUsers.value = Array.isArray(data) ? data : data.data || []
  } catch (err) {
    console.error(err)
    await showAlert('Error', 'Failed to fetch approval users.', 'danger')
  }
}

// Fetch document for edit
const fetchDocumentForEdit = async () => {
  if (!isEditMode.value) return
  try {
    const { data } = await axios.get(`/api/digital-docs-approvals/${props.documentId}/edit`)
    if (data.data) {
      const doc = data.data
      form.value.document_type = doc.document_type || ''
      form.value.description = doc.description || ''
      form.value.file = null
      form.value.approvals = (doc.approvals || []).map(a => ({
        id: a.id || null,
        responder_id: a.responder?.id || null,
        request_type: a.request_type || '',
        isDefault: ['initial', 'approve'].includes(a.request_type)
      }))
      existingFileUrl.value = doc.sharepoint_file_url || ''
      await nextTick()
      form.value.approvals.forEach((_, i) => initUserSelect(i))
    }
  } catch (err) {
    console.error(err)
    await showAlert('Error', err.response?.data?.message || 'Failed to load document data.', 'danger')
  }
}

// Init Select2
const initUserSelect = async (index) => {
  await nextTick()
  const el = document.querySelector(`.user-select[data-index="${index}"]`)
  if (!el) return
  destroySelect2(el)
  $(el).empty().append('<option value="">Select User</option>')
  approvalUsers.value.forEach(u => $(el).append(`<option value="${u.id}">${u.name}</option>`))
  initSelect2(el, { placeholder: 'Select User', width: '100%', allowClear: true }, (value) => {
    form.value.approvals[index].responder_id = value ? Number(value) : ''
  })
  $(el).val(form.value.approvals[index].responder_id || '').trigger('change.select2')
}

// Add / Remove approvals
const addApproval = async () => {
  form.value.approvals.push({ request_type: '', responder_id: '' })
  await initUserSelect(form.value.approvals.length - 1)
}

const removeApproval = async (index) => {
  const el = document.querySelector(`.user-select[data-index="${index}"]`)
  if (el) destroySelect2(el)
  form.value.approvals.splice(index, 1)
  await nextTick()
  form.value.approvals.forEach((_, i) => initUserSelect(i))
}

// Submit form
const submitForm = async () => {
  isSubmitting.value = true
  try {
    const formData = new FormData()

    // Main fields
    formData.append('document_type', form.value.document_type || '')
    formData.append('description', form.value.description || '')

    // File (optional on update)
    if (form.value.file) formData.append('file', form.value.file)

    // Approvals array
    form.value.approvals.forEach((a, i) => {
      formData.append(`approvals[${i}][user_id]`, a.responder_id || '')
      formData.append(`approvals[${i}][request_type]`, a.request_type || '')
      if (a.id) formData.append(`approvals[${i}][id]`, a.id)
    })

    let url = '/api/digital-docs-approvals'
    let method = 'post'

    if (isEditMode.value) {
      // For update, use POST with _method=PUT
      url = `/api/digital-docs-approvals/${props.documentId}`
      formData.append('_method', 'PUT')
    }

    // Send request
    await axios({
      method,
      url,
      data: formData,
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    await showAlert(
      'Success',
      isEditMode.value ? 'Document updated successfully.' : 'Document created successfully.',
      'success'
    )
    emit('submitted')
    goToIndex()
  } catch (err) {
    console.error(err.response?.data || err)
    await showAlert(
      'Error',
      err.response?.data?.message || err.message || 'Failed to save document.',
      'danger'
    )
  } finally {
    isSubmitting.value = false
  }
}


// Mounted
onMounted(async () => {
  await fetchApprovalUsers()
  await fetchDocumentForEdit()
  initSelect2(
  document.querySelector('.select2-doc-type'),
  {
    placeholder: 'Select Document Type',
    allowClear: true,
    width: '100%',
    data: documentTypes.value.map(dt => ({ id: dt, text: dt })),
  },
  (val) => (form.value.document_type = val || '')
);
if (form.value.document_type) {
  $('.select2-doc-type').val(form.value.document_type).trigger('change.select2')
}
})
</script>
