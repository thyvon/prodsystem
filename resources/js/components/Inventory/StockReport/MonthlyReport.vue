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
      <template #additional-header>
        <div class="d-flex mb-2">
          <button class="btn btn-success mr-2" @click="createMonthlyReport">
            <i class="fal fa-plus mr-1"></i> Create Monthly Report
          </button>
        </div>
      </template>
    </datatable>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import axios from 'axios'
import { showAlert, confirmAction } from '@/Utils/bootbox'

// -------------------- DATATABLE --------------------
const datatableRef = ref(null)
const datatableParams = reactive({
  sortColumn: 'created_at',
  sortDirection: 'desc',
  search: '',
  page: 1,
  limit: 10,
})

const datatableHeaders = [
  { text: 'Reference No', value: 'reference_no' },
  { text: 'Report Date', value: 'report_date' },
  { text: 'Warehouse Names', value: 'warehouse_names' },
  { text: 'Status', value: 'approval_status' },
  { text: 'Created At', value: 'created_at' },
  { text: 'Created By', value: 'creator.name' },
]

const datatableFetchUrl = '/api/inventory/stock-reports/monthly-report'
const datatableActions = ['detail', 'edit', 'delete'] // Removed 'preview'
const datatableOptions = { autoWidth: false, responsive: true, pageLength: 10 }

// -------------------- DATATABLE HANDLERS --------------------
const createMonthlyReport = () => window.location.href = '/inventory/stock-reports/monthly-report/create'
const handleView = report => window.location.href = `/inventory/stock-reports/monthly-report/${report.id}/details`
const handleEdit = report => window.location.href = `/inventory/stock-reports/monthly-report/${report.id}/edit`

const handleDelete = async report => {
  const confirmed = await confirmAction(
    `Delete Monthly Report "${report.reference_no}"?`,
    '<strong>Warning:</strong> This action cannot be undone!'
  )
  if (!confirmed) return

  try {
    const res = await axios.delete(`/api/inventory/stock-reports/monthly-report/${report.id}`)
    showAlert('Deleted', res.data.message || `"${report.reference_no}" deleted successfully.`, 'success')
    datatableRef.value?.reload()
  } catch (err) {
    console.error(err)
    showAlert('Error', err.response?.data?.message || 'Failed to delete.', 'danger')
  }
}

// DATATABLE EVENTS
const datatableHandlers = { detail: handleView, edit: handleEdit, delete: handleDelete } // Removed 'preview'
const handleSortChange = ({ column, direction }) => { datatableParams.sortColumn = column; datatableParams.sortDirection = direction }
const handlePageChange = page => datatableParams.page = page
const handleLengthChange = length => datatableParams.limit = length
const handleSearchChange = search => datatableParams.search = search
</script>
