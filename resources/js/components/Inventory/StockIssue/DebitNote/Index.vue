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
                  <select ref="departmentSelect" class="form-select" multiple></select>
                </div>
              </div>

              <!-- Buttons: Apply + Send Email -->
              <div class="d-flex justify-content-end gap-2">
                <button class="btn btn-primary" @click="applyFilters">
                  <i class="fal fa-filter mr-2"></i> Apply
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
const departmentSelect = ref(null)
const startDateRef = ref(null)
const endDateRef = ref(null)

const selectedWarehouses = ref([])
const selectedDepartments = ref([])

const sendingEmails = ref(false)
const progressText = ref('')

const datatableParams = reactive({
  sortColumn: 'id',
  sortDirection: 'desc',
  search: '',
  warehouse_ids: [],
  department_ids: [],
  start_date: null,
  end_date: null,
})

const datatableHeaders = [
  { text: 'Reference No', value: 'reference_number', minWidth: '150px' },
  { text: 'Warehouse', value: 'warehouse_name', minWidth: '150px' },
  { text: 'Department', value: 'department_name', minWidth: '150px' },
  { text: 'To Email', value: 'debit_note_email', minWidth: '200px' },
  { text: 'CC Email', value: 'cc_email', minWidth: '200px' },
  { text: 'Start Date', value: 'start_date', minWidth: '120px' },
  { text: 'End Date', value: 'end_date', minWidth: '120px' },
  { text: 'Total Items', value: 'total_items', minWidth: '100px' },
  { text: 'Total Amount', value: 'total_price', minWidth: '120px' },
  { text: 'Status', value: 'status', minWidth: '100px' },
  { text: 'Created By', value: 'created_by', minWidth: '150px' },
]

const datatableFetchUrl = '/api/inventory/debit-notes'
const datatableActions = ['edit', 'delete', 'preview']
const datatableOptions = { autoWidth: false, responsive: false, pageLength: 10 }

const handleEdit = (note) => window.location.href = `/inventory/debit-notes/${note.id}/edit`
const handlePreview = (note) => window.location.href = `/inventory/debit-notes/${note.id}/show`
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

const datatableHandlers = { edit: handleEdit, delete: handleDelete, preview: handlePreview }

// Fetch warehouses
const fetchWarehouses = async () => {
  try {
    const res = await axios.get('/api/main-value-lists/get-warehouses')
    const warehouses = res.data.map(w => ({ id: w.id, text: w.text }))
    destroySelect2(warehouseSelect.value)
    initSelect2(warehouseSelect.value, { placeholder: 'Warehouse', allowClear: true, width: '220px', data: warehouses }, (value) => {
      selectedWarehouses.value = value.map(Number)
    })
  } catch (e) { console.error(e) }
}

// Fetch departments
const fetchDepartments = async () => {
  try {
    const res = await axios.get('/api/main-value-lists/get-departments')
    const depts = res.data.map(d => ({ id: d.id, text: d.text }))
    destroySelect2(departmentSelect.value)
    initSelect2(departmentSelect.value, { placeholder: 'Department', allowClear: true, width: '220px', data: depts }, (value) => {
      selectedDepartments.value = value.map(Number)
    })
  } catch (e) { console.error(e) }
}

// Datepickers
const initDatepickers = () => {
  nextTick(() => {
    if (window.$ && startDateRef.value) {
      window.$(startDateRef.value).datepicker({ format: 'yyyy-mm-dd', autoclose: true, clearBtn: true })
        .on('changeDate', e => datatableParams.start_date = e.format(0, 'yyyy-mm-dd'))
    }
    if (window.$ && endDateRef.value) {
      window.$(endDateRef.value).datepicker({ format: 'yyyy-mm-dd', autoclose: true, clearBtn: true })
        .on('changeDate', e => datatableParams.end_date = e.format(0, 'yyyy-mm-dd'))
    }
  })
}

// Apply filters
const applyFilters = () => {
  datatableParams.warehouse_ids = selectedWarehouses.value
  datatableParams.department_ids = selectedDepartments.value
  datatableRef.value.reload()
}

// Send filtered emails with progress
const sendDebitNoteEmail = async () => {
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
      department_ids: selectedDepartments.value || [],
      start_date: datatableParams.start_date || null,
      end_date: datatableParams.end_date || null,
    })

    // Poll progress every 1 second
    const interval = setInterval(async () => {
      try {
        const res = await axios.get('/api/inventory/debit-notes/email-progress')
        progressText.value = res.data.status

        if (res.data.finished) {
          clearInterval(interval)
          sendingEmails.value = false
          showAlert('Success', 'Emails sent successfully!', 'success')
          datatableRef.value.reload()
        }
      } catch (e) {
        console.error('Progress fetch error:', e)
        clearInterval(interval)
        sendingEmails.value = false
      }
    }, 1000)

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
  fetchDepartments()
  initDatepickers()
})
</script>
