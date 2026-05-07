<template>
  <div>
    <datatable
      ref="datatableRef"
      :headers="datatableHeaders"
      :fetch-url="datatableFetchUrl"
      :fetch-params="datatableParams"
      :actions="datatableActions"
      :handlers="datatableHandlers"
      :options="datatableOptions"
      :scrollable="true"
      @sort-change="handleSortChange"
      @page-change="handlePageChange"
      @length-change="handleLengthChange"
      @search-change="handleSearchChange"
    >
      <template #additional-header>
        <div class="d-flex flex-column mb-2">

          <!-- Top Row: Filter Button -->
          <div class="d-flex mb-2 align-items-center gap-2">
            <button
              class="btn btn-info"
              type="button"
              data-toggle="collapse"
              data-target="#filterCollapse"
              aria-expanded="false"
              aria-controls="filterCollapse"
            >
              <i class="fal fa-filter mr-2"></i> Filter + Debit Note
            </button>
          </div>

          <!-- COLLAPSIBLE FILTER SECTION -->
          <div class="collapse" id="filterCollapse">
            <div class="card card-body shadow-sm">
              <!-- Row 1: Date + Warehouse -->
              <div class="row g-2 mb-3">
                <div class="col-md-3">
                  <input type="text" ref="startDateRef" class="form-control" placeholder="Start Date" />
                </div>
                <div class="col-md-3">
                  <input type="text" ref="endDateRef" class="form-control" placeholder="End Date" />
                </div>
                <div class="col-md-3">
                  <select ref="warehouseSelect" class="form-select" multiple></select>
                </div>
                <div class="col-md-3">
                  <select ref="campusSelect" class="form-select" multiple></select>
                </div>
              </div>

              <div class="row g-2 mb-3">
                <div class="col-md-6">
                  <select ref="departmentSelect" class="form-select" multiple></select>
                </div>
                <div class="col-md-6">
                  <select ref="statusSelect" class="form-select" multiple></select>
                </div>
              </div>

              <!-- Buttons: Apply + Send Email -->
              <div class="d-flex justify-content-end gap-2">
                <button class="btn btn-secondary" @click="resetFilters">
                  <i class="fal fa-undo mr-2"></i> Reset
                </button>
                <button class="btn btn-primary" @click="applyFilters">
                  <i class="fal fa-filter mr-2"></i> Apply
                </button>
                <button class="btn btn-success" @click="bulkExportDebitNotes">
                  <i class="fal fa-file-archive mr-2"></i> Bulk Export
                </button>
                <button class="btn btn-warning" @click="sendDebitNoteEmail" :disabled="sendingEmails">
                  <i class="fal fa-envelope mr-2"></i> Send Email
                </button>
              </div>

              <!-- Progress -->
              <div v-if="sendingEmails" class="mt-2 text-info font-weight-medium">
                {{ progressText }}
              </div>
            </div>
          </div>

        </div>
      </template>
    </datatable>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, nextTick } from 'vue'
import axios from 'axios'
import { initSelect2, destroySelect2 } from '@/Utils/select2.js'
import { showAlert, confirmAction } from '@/Utils/bootbox'

const datatableRef = ref(null)
const warehouseSelect = ref(null)
const campusSelect = ref(null)
const departmentSelect = ref(null)
const statusSelect = ref(null)
const startDateRef = ref(null)
const endDateRef = ref(null)

const selectedWarehouses = ref([])
const selectedCampuses = ref([])
const selectedDepartments = ref([])
const selectedStatuses = ref([])

const sendingEmails = ref(false)
const progressText = ref('')

const datatableParams = reactive({
  sortColumn: 'id',
  sortDirection: 'desc',
  search: '',
  warehouse_ids: [],
  campus_ids: [],
  department_ids: [],
  statuses: [],
  start_date: null,
  end_date: null,
})

const datatableHeaders = [
  { text: 'Reference No', value: 'reference_number', minWidth: '150px', sortable: true },
  { text: 'Warehouse', value: 'warehouse_name', minWidth: '150px', sortable: true },
  { text: 'Campus', value: 'campus_name', minWidth: '150px', sortable: true },
  { text: 'Department', value: 'department_name', minWidth: '150px', sortable: true },
  { text: 'To Email', value: 'debit_note_email', minWidth: '200px', sortable: false },
  { text: 'CC Email', value: 'cc_email', minWidth: '200px', maxWidth: '260px', sortable: false },
  { text: 'Start Date', value: 'start_date', minWidth: '120px', sortable: true },
  { text: 'End Date', value: 'end_date', minWidth: '120px', sortable: true },
  { text: 'Total Items', value: 'total_items', minWidth: '100px', sortable: false },
  { text: 'Total Amount', value: 'total_price', minWidth: '120px', sortable: false },
  { text: 'Status', value: 'status', minWidth: '100px', sortable: true },
  { text: 'Created By', value: 'created_by', minWidth: '150px', sortable: true },
]

const datatableFetchUrl = '/api/inventory/debit-notes'
const datatableActions = ['preview', 'export', 'resend', 'edit', 'delete']
const datatableOptions = { autoWidth: false, responsive: false, pageLength: 10 }

const handleEdit = (note) => window.location.href = `/inventory/debit-notes/${note.id}/edit`
const handlePreview = (note) => window.location.href = `/inventory/debit-notes/${note.id}/show`
const handleExport = (note) => window.open(`/api/inventory/debit-notes/${note.id}/export`, '_blank')
const handleDelete = async (note) => {
  const confirmed = await confirmAction(`Delete Debit Note "${note.reference_number}"?`, 'This cannot be undone!')
  if (!confirmed) return
  try {
    await axios.delete(`/api/inventory/debit-notes/${note.id}`)
    showAlert('Deleted', `"${note.reference_number}" deleted successfully.`, 'success')
    datatableRef.value.reload()
  } catch (e) {
    console.error(e)
    showAlert('Failed', e.response?.data?.message || 'Something went wrong.', 'danger')
  }
}

const pollEmailProgress = (successMessage) => {
  const interval = setInterval(async () => {
    try {
      const res = await axios.get('/api/inventory/debit-notes/email-progress')
      progressText.value = res.data.status

      if (res.data.finished) {
        clearInterval(interval)
        sendingEmails.value = false
        showAlert('Success', successMessage, 'success')
        datatableRef.value.reload()
      }
    } catch (e) {
      console.error('Progress fetch error:', e)
      clearInterval(interval)
      sendingEmails.value = false
    }
  }, 1000)
}

const handleResend = async (note) => {
  if (sendingEmails.value) {
    showAlert('In Progress', 'Another debit note email send is already in progress.', 'warning')
    return
  }

  const confirmed = await confirmAction(
    `Resend Debit Note "${note.reference_number}"?`,
    'This will send this specific debit note again to its configured recipients.'
  )
  if (!confirmed) return

  sendingEmails.value = true
  progressText.value = `Starting resend for ${note.reference_number}...`

  try {
    await axios.post(`/api/inventory/debit-notes/${note.id}/send-email`)
    pollEmailProgress(`Debit Note "${note.reference_number}" email has been sent.`)
  } catch (e) {
    console.error('Resend debit note email failed:', e)
    sendingEmails.value = false
    showAlert('Failed', e.response?.data?.message || 'Something went wrong.', 'danger')
  }
}

const datatableHandlers = { edit: handleEdit, delete: handleDelete, preview: handlePreview, export: handleExport, resend: handleResend }

// Fetch warehouses
const fetchWarehouses = async () => {
  try {
    const res = await axios.get('/api/main-value-lists/get-warehouses')
    const warehouses = res.data.map(w => ({ id: w.id, text: w.text }))
    destroySelect2(warehouseSelect.value)
    initSelect2(warehouseSelect.value, { placeholder: 'Warehouse', allowClear: true, width: '100%', data: warehouses }, (value) => {
      selectedWarehouses.value = Array.isArray(value) ? value.map(Number) : []
    })
  } catch (e) { console.error(e) }
}

// Fetch departments
const fetchDepartments = async () => {
  try {
    const res = await axios.get('/api/main-value-lists/get-departments')
    const depts = res.data.map(d => ({ id: d.id, text: d.text }))
    destroySelect2(departmentSelect.value)
    initSelect2(departmentSelect.value, { placeholder: 'Department', allowClear: true, width: '100%', data: depts }, (value) => {
      selectedDepartments.value = Array.isArray(value) ? value.map(Number) : []
    })
  } catch (e) { console.error(e) }
}

const fetchCampuses = async () => {
  try {
    const res = await axios.get('/api/main-value-lists/get-campuses')
    const campuses = res.data.map(c => ({ id: c.id, text: c.text }))
    destroySelect2(campusSelect.value)
    initSelect2(campusSelect.value, { placeholder: 'Campus', allowClear: true, width: '100%', data: campuses }, (value) => {
      selectedCampuses.value = Array.isArray(value) ? value.map(Number) : []
    })
  } catch (e) { console.error(e) }
}

const initStatusFilter = () => {
  const statuses = [
    { id: 'pending', text: 'Pending' },
    { id: 'sending', text: 'Sending' },
    { id: 'sent', text: 'Sent' },
  ]

  destroySelect2(statusSelect.value)
  initSelect2(statusSelect.value, { placeholder: 'Status', allowClear: true, width: '100%', data: statuses }, (value) => {
    selectedStatuses.value = Array.isArray(value) ? value : []
  })
}

// Datepickers
const initDatepickers = () => {
  nextTick(() => {
    if (window.$ && startDateRef.value) {
      window.$(startDateRef.value).datepicker({ format: 'yyyy-mm-dd', autoclose: true, clearBtn: true })
        .on('changeDate clearDate', () => {
          datatableParams.start_date = window.$(startDateRef.value).val() || null
        })
    }
    if (window.$ && endDateRef.value) {
      window.$(endDateRef.value).datepicker({ format: 'yyyy-mm-dd', autoclose: true, clearBtn: true })
        .on('changeDate clearDate', () => {
          datatableParams.end_date = window.$(endDateRef.value).val() || null
        })
    }
  })
}

const syncDateFilters = () => {
  datatableParams.start_date = startDateRef.value && window.$
    ? window.$(startDateRef.value).val() || null
    : null

  datatableParams.end_date = endDateRef.value && window.$
    ? window.$(endDateRef.value).val() || null
    : null
}

// Apply filters
const applyFilters = () => {
  syncDateFilters()
  datatableParams.warehouse_ids = [...selectedWarehouses.value]
  datatableParams.campus_ids = [...selectedCampuses.value]
  datatableParams.department_ids = [...selectedDepartments.value]
  datatableParams.statuses = [...selectedStatuses.value]
  datatableRef.value.reload()
}

const resetFilters = () => {
  selectedWarehouses.value = []
  selectedCampuses.value = []
  selectedDepartments.value = []
  selectedStatuses.value = []

  datatableParams.warehouse_ids = []
  datatableParams.campus_ids = []
  datatableParams.department_ids = []
  datatableParams.statuses = []
  datatableParams.start_date = null
  datatableParams.end_date = null

  if (window.$) {
    [warehouseSelect.value, campusSelect.value, departmentSelect.value, statusSelect.value]
      .filter(Boolean)
      .forEach((el) => window.$(el).val(null).trigger('change'))

    if (startDateRef.value) {
      window.$(startDateRef.value).datepicker('clearDates')
      window.$(startDateRef.value).val('')
    }

    if (endDateRef.value) {
      window.$(endDateRef.value).datepicker('clearDates')
      window.$(endDateRef.value).val('')
    }
  }

  datatableRef.value.reload()
}

const bulkExportDebitNotes = async () => {
  syncDateFilters()

  const warehouseIds = [...selectedWarehouses.value]
  const campusIds = [...selectedCampuses.value]
  const departmentIds = [...selectedDepartments.value]
  const statuses = [...selectedStatuses.value]

  try {
    const response = await axios.get('/api/inventory/debit-notes/export/bulk', {
      params: {
        search: datatableParams.search || '',
        sortColumn: datatableParams.sortColumn,
        sortDirection: datatableParams.sortDirection,
        warehouse_ids: warehouseIds,
        campus_ids: campusIds,
        department_ids: departmentIds,
        statuses,
        start_date: datatableParams.start_date,
        end_date: datatableParams.end_date,
      },
      responseType: 'blob',
    })

    const blob = new Blob([response.data], { type: 'application/zip' })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    const disposition = response.headers['content-disposition'] || ''
    const matched = disposition.match(/filename="?([^"]+)"?/)

    link.href = url
    link.download = matched?.[1] || 'debit_notes.zip'
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (e) {
    console.error('Bulk export failed:', e)

    let message = 'Failed to export debit notes.'
    const blobText = await e.response?.data?.text?.()

    if (blobText) {
      try {
        const parsed = JSON.parse(blobText)
        message = parsed.message || message
      } catch {
        message = blobText || message
      }
    } else {
      message = e.response?.data?.message || e.message || message
    }

    showAlert('Failed', message, 'danger')
  }
}

// Send filtered emails with progress
const sendDebitNoteEmail = async () => {
  syncDateFilters()

  const confirmed = await confirmAction(
    'Send emails for all filtered Debit Notes?',
    'This will send emails to all recipients of the current filtered list.'
  )
  if (!confirmed) return

  sendingEmails.value = true
  progressText.value = 'Starting...'

  try {
    // Start sending emails
    await axios.post('/api/inventory/debit-notes/send-emails', {
      warehouse_ids: selectedWarehouses.value || [],
      campus_ids: selectedCampuses.value || [],
      department_ids: selectedDepartments.value || [],
      statuses: selectedStatuses.value || [],
      start_date: datatableParams.start_date || null,
      end_date: datatableParams.end_date || null,
    })

    pollEmailProgress('Emails sent successfully!')
  } catch (e) {
    console.error('Send emails failed:', e)
    sendingEmails.value = false
    showAlert('Failed', e.response?.data?.message || 'Something went wrong.', 'danger')
  }
}

// Datatable events
const handleSortChange = ({ column, direction }) => { datatableParams.sortColumn = column; datatableParams.sortDirection = direction }
const handlePageChange = (page) => {}
const handleLengthChange = (length) => {}
const handleSearchChange = (search) => { datatableParams.search = search }

onMounted(() => {
  fetchWarehouses()
  fetchCampuses()
  fetchDepartments()
  initStatusFilter()
  initDatepickers()
})
</script>
