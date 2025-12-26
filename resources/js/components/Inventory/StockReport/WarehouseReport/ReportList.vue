<template>
  <div>
    <!-- DATATABLE -->
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
      <!-- FILTERS -->
      <template #additional-header>
        <div class="d-flex flex-column mb-2">
          <!-- Filter Row -->
          <div class="d-flex mb-2 align-items-center">
            <button class="btn btn-success mr-2" @click="createReport">
                <i class="fal fa-plus mr-1"></i> Create Report
            </button>
            <button class="btn btn-info" type="button" data-toggle="collapse" data-target="#filterCollapse">
              <i class="fal fa-filter mr-2"></i> Filters
            </button>
          </div>

          <!-- Collapsed Filters -->
          <div class="collapse" id="filterCollapse">
            <div class="d-flex align-items-center mb-2">
              <select ref="warehouseSelect" class="form-control" multiple></select>
            </div>

            <div class="d-flex justify-content-end">
              <button class="btn btn-primary" @click="applyFilters">
                <i class="fal fa-filter mr-2"></i> Apply
              </button>
            </div>
          </div>
        </div>
      </template>
    </datatable>

    <!-- Reusable PDF / File Viewer Modal -->
    <FileViewerModal ref="fileModal" title="Stock Report PDF" />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'
import { initSelect2, destroySelect2 } from '@/Utils/select2.js'
import { showAlert, confirmAction } from '@/Utils/bootbox'
import FileViewerModal from '@/components/Reusable/FileViewerModal.vue'

// --- REFS ---
const datatableRef = ref(null)
const warehouseSelect = ref(null)
const selectedWarehouses = ref([])
const fileModal = ref(null) // reusable file modal

// --- DATATABLE PARAMS ---
const datatableParams = reactive({
  sortColumn: 'created_at',
  sortDirection: 'desc',
  search: '',
  warehouse_ids: [],
  limit: 10,
  page: 1,
})

// --- DATATABLE HEADERS ---
const datatableHeaders = [
  { text: 'Reference No', value: 'reference_no', minWidth: '150px' },
  { text: 'Report Date', value: 'report_date', minWidth: '140px' },
  { text: 'Warehouse', value: 'warehouse.name', minWidth: '160px', sortable: false },
  { text: 'Approval Status', value: 'approval_status', minWidth: '150px' },
  { text: 'Created By', value: 'creater.name', minWidth: '150px', sortable: false },
  { text: 'Created At', value: 'created_at', minWidth: '150px' },
]

// --- DATATABLE FETCH URL & OPTIONS ---
const datatableFetchUrl = '/api/inventory/stock-reports/get-report-list'
const datatableOptions = { autoWidth: false, responsive: true, pageLength: 10 }

// --- DATATABLE ACTIONS & HANDLERS ---
const datatableActions = ['edit', 'view','delete']

const createReport = () => {
  window.location.href = '/inventory/stock-reports/reports/create-report'
}

const viewReport = (row) => window.location.href = `/inventory/stock-reports/reports/${row.id}/show-report`
const editReport = (row) => window.location.href = `/inventory/stock-reports/reports/${row.id}/edit-report`
const deleteReport = async (row) => {
  const confirmed = await confirmAction(
    `Delete Stock Report "${row.reference_no}"?`,
    '<strong>Warning:</strong> This action cannot be undone!'
  )
  if (!confirmed) return

  try {
    await axios.delete(`/api/inventory/stock-reports/${row.id}/delete-report`)
    showAlert('Deleted', `"${row.reference_no}" deleted successfully.`, 'success')
    datatableRef.value.reload()
  } catch (err) {
    console.error(err)
    showAlert('Error', err.response?.data?.message || 'Failed to delete.', 'danger')
  }
}

const datatableHandlers = { view: viewReport, edit: editReport, delete: deleteReport }

// --- FILTERS ---
const fetchWarehouses = async () => {
  try {
    const res = await axios.get('/api/main-value-lists/get-warehouses')
    const warehouses = res.data.map(w => ({ id: w.id, text: w.text }))

    destroySelect2(warehouseSelect.value)
    initSelect2(
      warehouseSelect.value,
      { placeholder: 'Filter by Warehouse', data: warehouses, allowClear: true },
      values => selectedWarehouses.value = values.map(Number)
    )
  } catch (e) { console.error(e) }
}

const applyFilters = () => {
  datatableParams.warehouse_ids = selectedWarehouses.value
  datatableRef.value.reload()
}

// --- DATATABLE EVENTS ---
const handleSortChange = ({ column, direction }) => { datatableParams.sortColumn = column; datatableParams.sortDirection = direction }
const handlePageChange = (page) => datatableParams.page = page
const handleLengthChange = (length) => datatableParams.limit = length
const handleSearchChange = (search) => datatableParams.search = search

// --- ON MOUNT ---
onMounted(() => {
  fetchWarehouses()
})
</script>
