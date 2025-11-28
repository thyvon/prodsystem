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
      <template #additional-header>
        <div class="btn-group" role="group">
          <button class="btn btn-success" @click="createStockCount">
            <i class="fal fa-plus"></i> Create Stock Count
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
const pageLength = ref(10)
const datatableParams = reactive({ sortColumn: 'transaction_date', sortDirection: 'desc', search: '' })

const datatableHeaders = [
  { text: 'Count Date', value: 'transaction_date', width: '12%' },
  { text: 'Reference No', value: 'reference_no', width: '10%' },
  { text: 'Warehouse', value: 'warehouse', width: '15%' },
  { text: 'Campus', value: 'warehouse_campus', width: '10%' },
  { text: 'Total Items', value: 'total_items', width: '10%' },
  { text: 'Total Counted', value: 'total_counted', width: '10%' },
  { text: 'Created By', value: 'created_by', width: '10%' },
  { text: 'Remarks', value: 'remarks', width: '13%' },
  { text: 'Approval Status', value: 'approval_status', width: '10%' },
]

const datatableFetchUrl = '/api/inventory/stock-counts'
const datatableActions = ['edit', 'delete', 'preview']
const datatableOptions = {
  responsive: true,
  pageLength: pageLength.value,
  lengthMenu: [[10, 20, 50, 100, 1000], [10, 20, 50, 100, 1000]],
}

// -------------------- ACTIONS --------------------
const createStockCount = () => window.location.href = '/inventory/stock-counts/create'
const handleEdit = (sc) => window.location.href = `/inventory/stock-counts/${sc.id}/edit`
const handlePreview = (sc) => window.location.href = `/inventory/stock-counts/${sc.id}/show`

const handleDelete = async (sc) => {
  const confirmed = await confirmAction(
    `Delete Stock Count "${sc.reference_no}"?`,
    '<strong>Warning:</strong> This action cannot be undone!'
  )
  if (!confirmed) return
  try {
    const response = await axios.delete(`/api/inventory/stock-counts/${sc.id}`)
    showAlert('Deleted', response.data.message || `"${sc.reference_no}" deleted successfully.`, 'success')
    datatableRef.value?.reload()
  } catch (err) {
    console.error(err)
    showAlert('Failed to delete', err.response?.data?.message || 'Something went wrong.', 'danger')
  }
}

const datatableHandlers = { edit: handleEdit, delete: handleDelete, preview: handlePreview }
const handleSortChange = ({ column, direction }) => { datatableParams.sortColumn = column; datatableParams.sortDirection = direction }
const handlePageChange = (page) => {}
const handleLengthChange = (length) => {}
const handleSearchChange = (search) => { datatableParams.search = search }
</script>
