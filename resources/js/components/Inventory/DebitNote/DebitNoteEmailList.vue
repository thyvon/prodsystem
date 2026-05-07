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
      @sort-change="handleSortChange"
      @page-change="handlePageChange"
      @length-change="handleLengthChange"
      @search-change="handleSearchChange"
    >
      <!-- Create + Import + Export buttons -->
      <template #additional-header>
        <div class="d-flex flex-column mb-2">
          <div class="d-flex mb-2 align-items-center gap-2">
            <div class="btn-group" role="group">
              <button class="btn btn-success" @click="openCreateModal">
                <i class="fal fa-plus"></i> Create
              </button>

              <button class="btn btn-primary" @click="openImportModal">
                <i class="fal fa-file-excel"></i> Import
              </button>

              <button class="btn btn-info" @click="exportDebitNoteEmails">
                <i class="fal fa-download"></i> Export
              </button>
            </div>

            <button
              class="btn btn-outline-info"
              type="button"
              data-toggle="collapse"
              data-target="#debitNoteEmailFilterCollapse"
              aria-expanded="false"
              aria-controls="debitNoteEmailFilterCollapse"
            >
              <i class="fal fa-filter mr-1"></i> Filters
            </button>
          </div>

          <div class="collapse" id="debitNoteEmailFilterCollapse">
            <div class="card card-body shadow-sm">
              <div class="row g-2 mb-3">
                <div class="col-md-6">
                  <select ref="departmentSelect" class="form-select" multiple></select>
                </div>
                <div class="col-md-6">
                  <input
                    v-model.trim="receiverNameFilter"
                    type="text"
                    class="form-control"
                    placeholder="Receiver Name"
                    @keyup.enter="applyFilters"
                  />
                </div>
              </div>

              <div class="d-flex justify-content-end gap-2">
                <button class="btn btn-secondary" @click="resetFilters">
                  <i class="fal fa-undo mr-1"></i> Reset
                </button>
                <button class="btn btn-primary" @click="applyFilters">
                  <i class="fal fa-filter mr-1"></i> Apply
                </button>
              </div>
            </div>
          </div>
        </div>
      </template>

      <!-- Send To -->
      <template #cell-send_to_email="{ value }">
        <div v-if="value?.length">
          <div v-for="(email, i) in value" :key="i">{{ email }}</div>
        </div>
        <span v-else class="text-muted">—</span>
      </template>

      <!-- CC -->
      <template #cell-cc_to_email="{ value }">
        <div v-if="value?.length">
          <div v-for="(email, i) in value" :key="i">{{ email }}</div>
        </div>
        <span v-else class="text-muted">—</span>
      </template>
    </datatable>

    <!-- Modals -->
    <DebitNoteEmailModal
      ref="debitNoteModal"
      :is-editing="isEditing"
      @submitted="reloadDatatable"
    />

    <DebitNoteEmailImportModal
      ref="importModal"
      @imported="reloadDatatable"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import axios from 'axios'
import DebitNoteEmailModal from '@/components/Inventory/DebitNote/DebitNoteEmailModal.vue'
import DebitNoteEmailImportModal from '@/components/Inventory/DebitNote/DebitNoteEmailImportModal.vue'
import { confirmAction, showAlert } from '@/Utils/bootbox'
import { initSelect2, destroySelect2 } from '@/Utils/select2.js'

// Refs
const datatableRef = ref(null)
const debitNoteModal = ref(null)
const importModal = ref(null)
const departmentSelect = ref(null)
const isEditing = ref(false)
const selectedDepartments = ref([])
const receiverNameFilter = ref('')

// Params
const datatableParams = reactive({
  sortColumn: 'created_at',
  sortDirection: 'desc',
  department_ids: [],
  receiver_name: ''
})

// Headers
const datatableHeaders = [
  { text: 'Campus', value: 'campus_name', width: '20%', sortable: true },
  { text: 'Department', value: 'department_name', width: '20%', sortable: true },
  { text: 'Warehouse', value: 'warehouse_name', width: '20%', sortable: true },
  { text: 'Receiver Name', value: 'receiver_name', width: '20%', sortable: true },
  { text: 'Send To', value: 'send_to_email', width: '20%', sortable: false },
  { text: 'CC', value: 'cc_to_email', width: '20%', sortable: false },
  { text: 'Created', value: 'created_at', width: '20%', sortable: true }
]

// API
const datatableFetchUrl = '/api/inventory/debit-note/emails'

// Actions (always enabled)
const datatableActions = computed(() => ['edit', 'delete'])

const datatableOptions = {
  responsive: true,
  pageLength: 10,
  lengthMenu: [[10, 20, 50, 100], [10, 20, 50, 100]]
}

// Modals
const openCreateModal = () => {
  isEditing.value = false
  debitNoteModal.value.show({ isEditing: false })
}

const openEditModal = async (row) => {
  const res = await axios.get(`/api/inventory/debit-note/emails/${row.id}/edit`)
  isEditing.value = true
  debitNoteModal.value.show({
    isEditing: true,
    ...res.data.data
  })
}

const openImportModal = () => {
  importModal.value.show()
}

const fetchDepartments = async () => {
  try {
    const res = await axios.get('/api/main-value-lists/get-departments')
    const departments = res.data.map(d => ({ id: d.id, text: d.text }))

    destroySelect2(departmentSelect.value)
    initSelect2(
      departmentSelect.value,
      { placeholder: 'Department', allowClear: true, width: '100%', data: departments },
      (value) => {
        selectedDepartments.value = value.map(Number)
      }
    )
  } catch (err) {
    console.error('Failed to load departments:', err)
    showAlert('Error', 'Failed to load departments.', 'danger')
  }
}

const applyFilters = () => {
  datatableParams.department_ids = [...selectedDepartments.value]
  datatableParams.receiver_name = receiverNameFilter.value
  datatableRef.value?.reload()
}

const resetFilters = () => {
  receiverNameFilter.value = ''
  selectedDepartments.value = []
  datatableParams.department_ids = []
  datatableParams.receiver_name = ''
  destroySelect2(departmentSelect.value)
  fetchDepartments()
  datatableRef.value?.reload()
}

const exportDebitNoteEmails = () => {
  const params = new URLSearchParams({
    search: datatableParams.search || '',
    sortColumn: datatableParams.sortColumn,
    sortDirection: datatableParams.sortDirection,
    receiver_name: datatableParams.receiver_name || ''
  })

  datatableParams.department_ids.forEach((id) => params.append('department_ids[]', id))

  window.location.href = `/api/inventory/debit-note/emails/export?${params.toString()}`
}

// Delete
const handleDelete = async (row) => {
  const confirmed = await confirmAction(
    'Delete this configuration?',
    'This action cannot be undone.'
  )
  if (!confirmed) return

  await axios.delete(`/api/inventory/debit-note/emails/${row.id}/delete`)
  showAlert('Deleted', 'Deleted successfully.', 'success')
  reloadDatatable()
}

// Datatable handlers
const datatableHandlers = {
  edit: openEditModal,
  delete: handleDelete
}

// Events
const handleSortChange = ({ column, direction }) => {
  datatableParams.sortColumn = column
  datatableParams.sortDirection = direction
}
const handlePageChange = () => {}
const handleLengthChange = () => {}
const handleSearchChange = (search) => (datatableParams.search = search)

// Reload
const reloadDatatable = () => {
  datatableRef.value?.reload()
}

onMounted(() => {
  fetchDepartments()
})
</script>
