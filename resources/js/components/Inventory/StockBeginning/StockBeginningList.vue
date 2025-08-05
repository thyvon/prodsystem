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
          <button class="btn btn-success" @click="createStockBeginning">
            <i class="fal fa-plus"></i> Create Stock Beginning
          </button>
          <button class="btn btn-primary" @click="exportStockBeginnings">
            <i class="fal fa-download"></i> Export
          </button>
        </div>
      </template>
    </datatable>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import axios from 'axios'
import { confirmAction, showAlert } from '@/Utils/bootbox'

// Refs and state
const datatableRef = ref(null)
const pageLength = ref(10)

// Datatable configuration
const datatableParams = reactive({
  sortColumn: 'created_at',
  sortDirection: 'desc',
  // Optionally add: page: 1, limit: 10, search: ''
})

const datatableHeaders = [
  { text: 'Reference No', value: 'reference_no', width: '10%' },
  { text: 'Date', value: 'beginning_date', width: '10%' },
  { text: 'Warehouse', value: 'warehouse_name', width: '20%' },
  { text: 'Campus', value: 'campus_name', width: '7%' },
  { text: 'Building', value: 'building_name', width: '7%' },
  { text: 'Quantity', value: 'quantity', width: '7%' },
  { text: 'Value', value: 'total_value', width: '7%' },
  { text: 'Created By', value: 'created_by', width: '10%' },
  { text: 'Created', value: 'created_at', width: '8%' },
  { text: 'Updated', value: 'updated_at', width: '15%' },
  { text: 'Approval Status', value: 'approval_status', width: '10%' }
]

const datatableFetchUrl = '/api/inventory/stock-beginnings'
const datatableActions = ['edit', 'delete', 'preview']
const datatableOptions = {
  responsive: true,
  pageLength: pageLength.value,
  lengthMenu: [[10, 20, 50, 100, 1000], [10, 20, 50, 100, 1000]],
}

// Action handlers
const createStockBeginning = () => {
  window.location.href = '/inventory/stock-beginnings/create'; // Adjust URL as per your routes
}

const handleEdit = (stockBeginning) => {
  window.location.href = `/inventory/stock-beginnings/${stockBeginning.id}/edit`; // Adjust URL as per your routes
}

const handlePreview = (stockBeginning) => {
  window.location.href = `/inventory/stock-beginnings/${stockBeginning.id}/show`; // Adjust URL as per your routes
}

const handleDelete = async (stockBeginning) => {
  const confirmed = await confirmAction(
    `Delete Stock Beginning "${stockBeginning.reference_no}"?`,
    '<strong>Warning:</strong> This action cannot be undone!'
  )
  if (!confirmed) return

  try {
    const response = await axios.delete(`/api/inventory/stock-beginnings/${stockBeginning.id}`)
    showAlert('Deleted', response.data.message || `"${stockBeginning.reference_no}" was deleted successfully.`, 'success')
    datatableRef.value?.reload() // Ensure datatable refreshes
  } catch (e) {
    console.error(e)
    showAlert('Failed to delete', e.response?.data?.message || 'Something went wrong.', 'danger')
  }
}

const exportStockBeginnings = () => {
  const params = {
    search: datatableParams.search || '',
    sortColumn: datatableParams.sortColumn,
    sortDirection: datatableParams.sortDirection,
  };
  const queryString = new URLSearchParams(params).toString();
  window.location.href = `/api/inventory/stock-beginnings/export?${queryString}`; // Matches your export method
}

// Datatable event handlers
const handleSortChange = ({ column, direction }) => {
  datatableParams.sortColumn = column
  datatableParams.sortDirection = direction
}

const handlePageChange = (page) => {
  // Uncomment and implement if your datatable supports pagination
  // datatableParams.page = page
}

const handleLengthChange = (length) => {
  // Uncomment and implement if your datatable supports page length
  // datatableParams.limit = length
}

const handleSearchChange = (search) => {
  datatableParams.search = search
}

// Map actions to handlers
const datatableHandlers = {
  edit: handleEdit,
  delete: handleDelete,
  preview: handlePreview,
}
</script>