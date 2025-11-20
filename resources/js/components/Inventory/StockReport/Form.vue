<template>
  <div class="container-fluid">
    <form @submit.prevent="submitForm">
      <div class="card border shadow-sm">

        <!-- Header -->
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
          <h4 class="mb-0">{{ isEditMode ? 'Edit Monthly Stock Report' : 'Create Monthly Stock Report' }}</h4>
          <button type="button" class="btn btn-outline-light btn-sm" @click="goToList">
            Back
          </button>
        </div>

        <div class="card-body">

          <!-- Dates & Reference -->
          <div class="row mb-4">
            <div class="col-md-3">
              <label class="form-label fw-bold">Start Date <span class="text-danger">*</span></label>
              <input id="start_date" type="text" class="form-control" readonly required />
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold">End Date <span class="text-danger">*</span></label>
              <input id="end_date" type="text" class="form-control" readonly required />
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold">Reference No</label>
              <input type="text" class="form-control" :value="form.reference_no || 'Auto-generated'" readonly />
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold">Status</label>
              <div>
                <span class="badge fs-6" :class="statusBadgeClass">
                  {{ form.approval_status }}
                </span>
                <div v-if="form.approver_name" class="small text-muted mt-1">
                  Approved by {{ form.approver_name }}
                </div>
              </div>
            </div>
          </div>

          <!-- Warehouses & Remarks -->
          <div class="row mb-4">
            <div class="col-md-8">
              <label class="form-label fw-bold">Warehouses <span class="text-danger">*</span></label>
              <select id="warehouseSelect" multiple class="form-control"></select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-bold">Remarks</label>
              <textarea v-model="form.remarks" class="form-control" rows="5" placeholder="Optional remarks..."></textarea>
            </div>
          </div>

          <!-- Dynamic Approval Table (LIKE STOCK TRANSFER) -->
          <div class="border rounded p-4 bg-light">
            <h5 class="h5 fw-bold text-primary mb-3">Approval Assignments</h5>

            <div class="table-responsive">
              <table class="table table-bordered table-sm align-middle">
                <thead class="table-light">
                  <tr>
                    <th width="35%">Approval Type</th>
                    <th width="55%">Assigned User</th>
                    <th width="10%" class="text-center">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(approval, index) in form.approvals" :key="index">
                    <td>
                      <select
                        :ref="el => setTypeSelectRef(el, index)"
                        class="form-control form-control-sm"
                        :disabled="approval.isDefault"
                        v-model="approval.request_type"
                      >
                        <option value="">Select Type</option>
                        <option value="check">Check</option>
                        <option value="verify">Verify</option>
                        <option value="acknowledge">Acknowledge</option>
                      </select>
                    </td>
                    <td>
                      <select
                        :ref="el => setUserSelectRef(el, index)"
                        class="form-control form-control-sm"
                        :disabled="!approval.request_type"
                      >
                        <option value="">Select User</option>
                        <option v-for="user in approval.availableUsers" :key="user.id" :value="user.id">
                          {{ user.name }} <small class="text-muted">({{ user.card_number || user.email }})</small>
                        </option>
                      </select>
                    </td>
                    <td class="text-center">
                      <button
                        type="button"
                        class="btn btn-danger btn-sm"
                        @click="removeApproval(index)"
                        :disabled="approval.isDefault"
                        title="Remove"
                      >
                        Remove
                      </button>
                    </td>
                  </tr>
                  <tr v-if="form.approvals.length === 0">
                    <td colspan="3" class="text-center text-muted py-3">No approval assignments</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- <button type="button" class="btn btn-outline-primary btn-sm" @click="addApproval">
              Add Approval Assignment
            </button> -->
          </div>

          <!-- Submit -->
          <div class="text-end mt-4">
            <button type="button" class="btn btn-secondary me-2" @click="goToList">Cancel</button>
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="isSubmitting || !canSubmit"
            >
              <span v-if="isSubmitting" class="spinner-border spinner-border-sm me-2"></span>
              {{ isEditMode ? 'Update Report' : 'Submit for Approval' }}
            </button>
          </div>

        </div>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick } from 'vue'
import axios from 'axios'
import { showAlert } from '@/Utils/bootbox'

const props = defineProps({
  stockReportId: { type: [String, Number], default: null }
})

const isEditMode = computed(() => !!props.stockReportId)
const isSubmitting = ref(false)

// Form data
const form = reactive({
  id: null,
  start_date: '',
  end_date: '',
  warehouse_ids: [],
  remarks: '',
  reference_no: '',
  approval_status: 'Draft',
  approver_name: '',
  approvals: [] // Dynamic: [{ request_type, user_id, isDefault, availableUsers }]
})

// Select2 refs
const typeSelectRefs = ref([])
const userSelectRefs = ref([])

const setTypeSelectRef = (el, index) => { typeSelectRefs.value[index] = el }
const setUserSelectRef = (el, index) => { userSelectRefs.value[index] = el }

// Master data
const warehouses = ref([])
const usersByType = reactive({ check: [], verify: [], acknowledge: [] })

// Computed
const statusBadgeClass = computed(() => ({
  'badge-success': form.approval_status === 'Approved',
  'badge-warning': ['Pending', 'In Progress'].includes(form.approval_status),
  'badge-secondary': form.approval_status === 'Draft',
  'badge-danger': form.approval_status === 'Rejected'
}))

const canSubmit = computed(() => {
  const required = ['check', 'verify', 'acknowledge']
  const assigned = form.approvals
    .filter(a => required.includes(a.request_type) && a.user_id)
    .map(a => a.request_type)

  return (
    form.start_date &&
    form.end_date &&
    form.warehouse_ids.length > 0 &&
    required.every(type => assigned.includes(type))
  )
})

const goToList = () => window.location.href = '/inventory/stock-reports/monthly-report'

// Fetch warehouses + approval users
const fetchMasterData = async () => {
  try {
    const [whRes, userRes] = await Promise.all([
      axios.get('/api/inventory/stock-reports/get-warehouses'),
      axios.get('/api/inventory/stock-reports/get-approval-users')
    ])

    warehouses.value = whRes.data.data || whRes.data
    Object.assign(usersByType, userRes.data)

    // Exclude current logged-in user
    const currentUserId = window.Laravel?.user?.id || null
    if (currentUserId) {
      Object.keys(usersByType).forEach(key => {
        usersByType[key] = usersByType[key].filter(u => u.id !== currentUserId)
      })
    }
  } catch (err) {
    showAlert('Error', 'Failed to load warehouses or users.', 'danger')
  }
}

// Load report for edit
const loadReport = async (id) => {
  try {
    const { data } = await axios.get(`/api/inventory/stock-reports/${id}/edit`)
    const r = data.data

    Object.assign(form, {
      id: r.id,
      start_date: r.start_date,
      end_date: r.end_date,
      warehouse_ids: r.warehouse_ids || [],
      remarks: r.remarks || '',
      reference_no: r.reference_no,
      approval_status: r.approval_status,
      approver_name: r.approver_name || ''
    })

    form.approvals = (r.approvals || []).map(a => ({
      id: a.id || null,
      request_type: a.request_type,
      user_id: a.responder_id ? Number(a.responder_id) : null,
      isDefault: true,
      availableUsers: usersByType[a.request_type] || []
    }))

    // Ensure all 3 types exist
    ;['check', 'verify', 'acknowledge'].forEach(type => {
      if (!form.approvals.some(a => a.request_type === type)) {
        form.approvals.push({
          id: null,
          request_type: type,
          user_id: null,
          isDefault: true,
          availableUsers: usersByType[type] || []
        })
      }
    })
  } catch (err) {
    showAlert('Error', 'Failed to load report.', 'danger')
  }
}

// Dynamic approvals
const addApproval = () => {
  form.approvals.push({
    id: null,
    request_type: '',
    user_id: null,
    isDefault: false,
    availableUsers: []
  })
}

const removeApproval = (index) => {
  if (form.approvals[index].isDefault) {
    showAlert('Cannot Remove', 'Default approval steps cannot be removed.', 'warning')
    return
  }
  form.approvals.splice(index, 1)
}

// Rebuild user dropdown when type changes
const refreshUserDropdown = async (index) => {
  const type = form.approvals[index].request_type
  const $select = userSelectRefs.value[index]

  if (!$select) return

  // Destroy old
  if ($select.select2) $select.select2('destroy')

  if (!type) {
    form.approvals[index].availableUsers = []
    form.approvals[index].user_id = null
    $( $select ).html('<option value="">Select User</option>')
    return
  }

  form.approvals[index].availableUsers = usersByType[type] || []
  form.approvals[index].user_id = null // reset on type change

  await nextTick()

  $( $select ).select2({
    placeholder: 'Select User',
    allowClear: true,
    width: '100%',
    data: form.approvals[index].availableUsers.map(u => ({
      id: u.id,
      text: `${u.name} (${u.card_number || u.email})`
    }))
  })
  .val(null) // ← Important: ensures nothing is pre-selected
  .trigger('change')
  .on('change', (e) => {
    form.approvals[index].user_id = e.target.value ? Number(e.target.value) : null
  })

  // Only pre-select if editing and user was previously saved
  if (form.approvals[index].user_id) {
    $( $select ).val(form.approvals[index].user_id).trigger('change')
  }
}

// Initialize widgets
const initWidgets = async () => {
  // Datepickers
  $('#start_date, #end_date').datepicker({
    format: 'yyyy-mm-dd',
    autoclose: true,
    todayHighlight: true
  }).on('changeDate', e => {
    const field = e.target.id
    form[field === 'start_date' ? 'start_date' : 'end_date'] = e.format()
  })

  // Warehouse multi-select
  $('#warehouseSelect').select2({
    placeholder: 'Select warehouses',
    allowClear: true,
    width: '100%',
    data: warehouses.value.map(w => ({ id: w.id, text: w.name || w.text }))
  }).on('change', () => {
    form.warehouse_ids = $('#warehouseSelect').val() ? $('#warehouseSelect').val().map(Number) : []
  })

  // Set saved values
  if (form.start_date) $('#start_date').datepicker('setDate', form.start_date)
  if (form.end_date) $('#end_date').datepicker('setDate', form.end_date)
  if (form.warehouse_ids.length) $('#warehouseSelect').val(form.warehouse_ids).trigger('change')

  // Approval Type Selects (v-model handles value)
  typeSelectRefs.value.forEach((el, i) => {
    if (!el) return
    $(el).select2({
      placeholder: 'Select Type',
      width: '100%',
      allowClear: !form.approvals[i].isDefault
    })
    .val(form.approvals[i].request_type || null)
    .trigger('change')
    .on('change', () => {
      form.approvals[i].request_type = el.value
      refreshUserDropdown(i)
    })
  })

  // Initialize user dropdowns
  form.approvals.forEach((_, i) => refreshUserDropdown(i))
}

// Submit
const submitForm = async () => {
  if (!canSubmit.value) {
    showAlert('Validation Failed', 'Please fill all required fields: dates, warehouses, and one user per approval type (Check, Verify, Acknowledge).', 'warning')
    return
  }

  isSubmitting.value = true

  const payload = {
    ...form,
    warehouse_ids: form.warehouse_ids,
    approvals: form.approvals.map(a => ({
      id: a.id,
      request_type: a.request_type,
      user_id: a.user_id
    }))
  }

  try {
    const url = isEditMode.value
      ? `/api/inventory/stock-reports/${form.id}`
      : '/api/inventory/stock-reports'

    await axios[isEditMode.value ? 'put' : 'post'](url, payload)

    await showAlert('Success', 'Monthly stock report saved successfully!', 'success')
    goToList()
  } catch (err) {
    showAlert('Error', err.response?.data?.message || 'Failed to save report.', 'danger')
  } finally {
    isSubmitting.value = false
  }
}

// Lifecycle
onMounted(async () => {
  await fetchMasterData()

  if (isEditMode.value) {
    await loadReport(props.stockReportId)
  } else {
    // Create mode: add default 3 rows
    ;['check', 'verify', 'acknowledge'].forEach(type => {
      form.approvals.push({
        id: null,
        request_type: type,
        user_id: null,
        isDefault: true,
        availableUsers: usersByType[type] || []
      })
    })
  }

  await nextTick()
  await initWidgets()
})
</script>

<style scoped>
.badge { padding: 0.5em 0.9em; font-size: 0.9em; }
small { font-size: 0.8em; }
</style>