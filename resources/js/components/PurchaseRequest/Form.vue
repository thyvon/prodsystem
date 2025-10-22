<template>
  <div class="container-fluid">
    <form @submit.prevent="submitForm" enctype="multipart/form-data">
      <div class="card border mb-0 shadow">
        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
          <h4 class="mb-0 font-weight-bold">
            {{ isEditMode ? 'Edit Purchase Request' : 'Create Purchase Request' }}
          </h4>
          <button type="button" class="btn btn-outline-primary btn-sm" @click="goToIndex">
            <i class="fal fa-backward"></i>
          </button>
        </div>

        <div class="card-body">
          <!-- Document Details -->
        <div class="row">
        <div class="col-6">
            <div class="border rounded p-3 mb-4">
            <h5 class="font-weight-bold mb-3 text-primary">🏷️ Requester Info</h5>

            <!-- Requester Name -->
            <div class="row align-items-center mb-2">
                <div class="col-4 font-weight-bold">Requester:</div>
                <div class="col-8 border-bottom py-1">Vun Thy</div>
            </div>

            <!-- Position -->
            <div class="row align-items-center mb-2">
                <div class="col-4 font-weight-bold">Position:</div>
                <div class="col-8 border-bottom py-1">Manager</div>
            </div>

            <!-- Card ID -->
            <div class="row align-items-center mb-2">
                <div class="col-4 font-weight-bold">Card ID:</div>
                <div class="col-8 border-bottom py-1">123456</div>
            </div>

            <!-- Department -->
            <div class="row align-items-center mb-2">
                <div class="col-4 font-weight-bold">Department:</div>
                <div class="col-8 border-bottom py-1">Finance</div>
            </div>

            <!-- Purpose -->
            <div class="form-group mt-3">
                <label class="font-weight-bold">Purpose <span class="text-danger">*</span></label>
                <textarea v-model="form.purpose" class="form-control" rows="2" required></textarea>
            </div>

            <!-- Upload File -->
            <div class="form-group mt-2">
                <label class="font-weight-bold">Upload File <span class="text-danger">*</span></label>
                <div class="custom-file">
                <input type="file" class="custom-file-input" id="customFile" @change="handleFileUpload">
                <label class="custom-file-label" for="customFile">{{ fileLabel }}</label>
                </div>
                <div v-if="existingFileUrl" class="mt-1">
                <a :href="existingFileUrl" target="_blank">Current File</a>
                </div>
            </div>

            </div>
        </div>
            <div class="col-6">
            <div class="border rounded p-3 mb-4">
            <h5 class="font-weight-bold mb-3 text-primary">🏷️ Contact Info</h5>

            <div class="row align-items-center mb-2">
                <div class="col-4 font-weight-bold">Cellphone:</div>
                <div class="col-8 border-bottom py-1">123456</div>
            </div>
                <div class="row align-items-center mb-2">
                <div class="col-4 font-weight-bold">Ext:</div>
                <div class="col-8 border-bottom py-1">123456</div>
            </div>
            <!-- Purpose -->
            <div class="form-group mt-3">
                <label class="font-weight-bold">Purpose <span class="text-danger">*</span></label>
                <textarea v-model="form.purpose" class="form-control" rows="2" required></textarea>
            </div>

            <!-- Upload File -->
            <div class="form-group mt-2">
                <label class="font-weight-bold">Upload File <span class="text-danger">*</span></label>
                <div class="custom-file">
                <input type="file" class="custom-file-input" id="customFile" @change="handleFileUpload">
                <label class="custom-file-label" for="customFile">{{ fileLabel }}</label>
                </div>
                <div v-if="existingFileUrl" class="mt-1">
                <a :href="existingFileUrl" target="_blank">Current File</a>
                </div>
            </div>

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
                    <th style="min-width: 200px;">Approval Type</th>
                    <th style="min-width: 200px;">Assigned User</th>
                    <th style="min-width: 100px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(approval, index) in form.approvals" :key="index">
                    <td>
                      <select
                        v-model="approval.request_type"
                        class="form-control approval-type-select"
                        :data-index="index"
                        required
                      >
                        <option value="">Select Type</option>
                        <option value="initial">Initial</option>
                        <option value="verify">Verify</option>
                        <option value="approve">Approve</option>
                        <option value="check">Check</option>
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
                      <button
                        type="button"
                        class="btn btn-danger btn-sm"
                        @click="removeApproval(index)"
                        :disabled="approval.isDefault"
                      >
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
const documentTypes = ref([
  'Stock Report',
  'Monthly Stock Report',
  'Asset Disposal',
  'Monthly Procurement Report',
  'Monthly Stock Summary Report'
])

const defaultApprovalMap = {
  'Stock Report': ['initial', 'check', 'approve'],
  'Monthly Stock Report': ['initial', 'verify', 'check', 'approve'],
  'Asset Disposal': ['approve'],
  'Monthly Procurement Report': ['check', 'approve'],
  'Monthly Stock Summary Report': ['check', 'approve']
}

const form = ref({
  document_type: '',
  description: '',
  file: null,
  approvals: []
})

const existingFileUrl = ref('')
const fileLabel = ref('Choose file')

const goToIndex = () => (window.location.href = '/digital-docs-approvals')

// --- Merge defaults with existing approvals (create/edit) ---
const setDefaultApprovalsByDocType = async (docType) => {
  if (!docType) return

  const defaults = defaultApprovalMap[docType] || []

  // Keep existing approvals
  const existingApprovals = [...form.value.approvals]

  // Mark existing approvals as default if they match default types
  existingApprovals.forEach(a => {
    a.isDefault = defaults.includes(a.request_type)
  })

  // Add missing default approvals that do not exist yet
  defaults.forEach(type => {
    const exists = existingApprovals.some(a => a.request_type === type)
    if (!exists) {
      existingApprovals.push({
        request_type: type,
        responder_id: '',
        isDefault: true
      })
    }
  })

  form.value.approvals = existingApprovals

  // Reinitialize Select2 for all approvals
  await nextTick()
  form.value.approvals.forEach((_, i) => {
    initApprovalTypeSelect(i)
    initUserSelect(i)
  })
}

// --- Handle file upload ---
const handleFileUpload = (e) => {
  const file = e.target.files[0]
  form.value.file = file || null
  fileLabel.value = file ? file.name : 'Choose file'
}

// --- Initialize user select2 ---
const initUserSelect = async (index) => {
  await nextTick()
  const approval = form.value.approvals[index]
  const el = document.querySelector(`.user-select[data-index="${index}"]`)
  if (!el) return
  destroySelect2(el)

  $(el).empty().append('<option value="">Select User</option>')

  if (!approval.request_type) {
    initSelect2(el, { placeholder: 'Select User', width: '100%', allowClear: true }, (value) => {
      form.value.approvals[index].responder_id = value ? Number(value) : ''
    })
    return
  }

  try {
    const { data } = await axios.get('/api/digital-docs-approvals/get-users-for-approval', {
      params: { request_type: approval.request_type }
    })
    const users = Array.isArray(data.data) ? data.data : []
    $(el).empty().append('<option value="">Select User</option>')
    users.forEach(u => $(el).append(`<option value="${u.id}">${u.name}</option>`))

    initSelect2(el, { placeholder: 'Select User', width: '100%', allowClear: true }, (value) => {
      form.value.approvals[index].responder_id = value ? Number(value) : ''
    })

    // Preserve selected user in edit mode
    $(el).val(approval.responder_id || '').trigger('change.select2')
  } catch (err) {
    console.error(err)
    $(el).empty().append('<option value="">Failed to load users</option>')
  }
}

// --- Initialize approval type select2 ---
const initApprovalTypeSelect = async (index) => {
  await nextTick()
  const el = document.querySelector(`.approval-type-select[data-index="${index}"]`)
  if (!el) return
  destroySelect2(el)

  initSelect2(el, {
    placeholder: 'Select Approval Type',
    width: '100%',
    allowClear: true
  }, (value) => {
    form.value.approvals[index].request_type = value || ''
    if (value) initUserSelect(index)
  })

  $(el).val(form.value.approvals[index].request_type || '').trigger('change.select2')
}

// --- Add / Remove approvals ---
const addApproval = async () => {
  form.value.approvals.push({ request_type: '', responder_id: '', isDefault: false })
  const index = form.value.approvals.length - 1
  await initApprovalTypeSelect(index)
  await initUserSelect(index)
}

const removeApproval = async (index) => {
  const typeEl = document.querySelector(`.approval-type-select[data-index="${index}"]`)
  if (typeEl) destroySelect2(typeEl)

  const userEl = document.querySelector(`.user-select[data-index="${index}"]`)
  if (userEl) destroySelect2(userEl)

  form.value.approvals.splice(index, 1)
  await nextTick()
  form.value.approvals.forEach((_, i) => {
    initApprovalTypeSelect(i)
    initUserSelect(i)
  })
}

// --- Fetch document for edit ---
const fetchDocumentForEdit = async () => {
  if (!isEditMode.value) return
  try {
    const { data } = await axios.get(`/api/digital-docs-approvals/${props.documentId}/edit`)
    if (data.data) {
      const doc = data.data
      form.value.document_type = doc.document_type || ''
      form.value.description = doc.description || ''
      form.value.file = null
      existingFileUrl.value = doc.sharepoint_file_url || ''

      // Map existing approvals
      form.value.approvals = (doc.approvals || []).map(a => ({
        id: a.id || null,
        responder_id: a.responder?.id || null,
        request_type: a.request_type || '',
        isDefault: false // will set later
      }))

      await setDefaultApprovalsByDocType(form.value.document_type)
    }
  } catch (err) {
    console.error(err)
    await showAlert('Error', err.response?.data?.message || 'Failed to load document data.', 'danger')
  }
}

// --- Submit form ---
const submitForm = async () => {
  isSubmitting.value = true
  try {
    const formData = new FormData()
    formData.append('document_type', form.value.document_type || '')
    formData.append('description', form.value.description || '')
    if (form.value.file) formData.append('file', form.value.file)
    form.value.approvals.forEach((a, i) => {
      formData.append(`approvals[${i}][user_id]`, a.responder_id || '')
      formData.append(`approvals[${i}][request_type]`, a.request_type || '')
      if (a.id) formData.append(`approvals[${i}][id]`, a.id)
    })

    let url = '/api/digital-docs-approvals'
    let method = 'post'
    if (isEditMode.value) {
      url = `/api/digital-docs-approvals/${props.documentId}`
      formData.append('_method', 'PUT')
    }

    await axios({ method, url, data: formData, headers: { 'Content-Type': 'multipart/form-data' } })
    await showAlert('Success', isEditMode.value ? 'Document updated successfully.' : 'Document created successfully.', 'success')
    emit('submitted')
    goToIndex()
  } catch (err) {
    console.error(err)
    await showAlert('Error', err.response?.data?.message || 'Failed to save document.', 'danger')
  } finally {
    isSubmitting.value = false
  }
}

// --- Mounted ---
onMounted(async () => {
  await fetchDocumentForEdit()

  const docTypeEl = document.querySelector('.select2-doc-type')

  initSelect2(
    docTypeEl,
    {
      placeholder: 'Select Document Type',
      allowClear: true,
      width: '100%',
      data: documentTypes.value.map(dt => ({ id: dt, text: dt }))
    },
    async (val) => {
      form.value.document_type = val || ''
      if (val) {
        await setDefaultApprovalsByDocType(val)
      } else {
        form.value.approvals = []
      }
    }
  )

  if (isEditMode.value && form.value.document_type) {
    $(docTypeEl).val(form.value.document_type).trigger('change.select2')
  }
})
</script>
