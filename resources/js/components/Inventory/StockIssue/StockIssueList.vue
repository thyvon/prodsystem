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
          <button class="btn btn-success" @click="createStockIssue">
            <i class="fal fa-plus"></i> Create Stock Issue
          </button>
          <button class="btn btn-primary" @click="exportStockIssue">
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

// Adjust headers for Stock Issue
const datatableHeaders = [
  { text: 'Reference No', value: 'reference_no', width: '9%' },
  { text: 'Request No', value: 'request_number', width: '9%' },
  { text: 'Warehouse', value: 'warehouse_name', width: '18%' },
  { text: 'Warehouse Campus', value: 'warehouse_campus_name', width: '7%' },
  { text: 'Quantity', value: 'quantity', width: '7%' },
  { text: 'Amount', value: 'total_price', width: '7%' },
  { text: 'Issued By', value: 'created_by', width: '10%' },
  { text: 'Issue Date', value: 'transaction_date', width: '11%' },
  { text: 'Requester', value: 'requester_name', width: '12%' },
  { text: 'Campus', value: 'requester_campus_name', width: '10%' },
]

const datatableFetchUrl = '/api/inventory/stock-issues'
const datatableActions = ['edit', 'delete', 'preview']
const datatableOptions = {
  responsive: true,
  pageLength: pageLength.value,
  lengthMenu: [[10, 20, 50, 100, 1000], [10, 20, 50, 100, 1000]],
}

// Action handlers
const createStockIssue = () => {
  window.location.href = '/inventory/stock-issues/create'
}

const handleEdit = (stockIssue) => {
  window.location.href = `/inventory/stock-issues/${stockIssue.id}/edit`
}

const handlePreview = (stockIssue) => {
  window.location.href = `/inventory/stock-issues/${stockIssue.id}/show`
}

const handleDelete = async (stockIssue) => {
  const confirmed = await confirmAction(
    `Delete Stock Issue "${stockIssue.issue_number}"?`,
    '<strong>Warning:</strong> This action cannot be undone!'
  )
  if (!confirmed) return

  try {
    const response = await axios.delete(`/api/inventory/stock-issues/${stockIssue.id}`)
    showAlert('Deleted', response.data.message || `"${stockIssue.issue_number}" was deleted successfully.`, 'success')
    datatableRef.value?.reload()
  } catch (e) {
    console.error(e)
    showAlert('Failed to delete', e.response?.data?.message || 'Something went wrong.', 'danger')
  }
}

const exportStockIssue = () => {
  const params = {
    search: datatableParams.search || '',
    sortColumn: datatableParams.sortColumn,
    sortDirection: datatableParams.sortDirection,
  }
  const queryString = new URLSearchParams(params).toString()
  window.location.href = `/api/inventory/stock-issues/export?${queryString}`
}

// Datatable event handlers
const handleSortChange = ({ column, direction }) => {
  datatableParams.sortColumn = column
  datatableParams.sortDirection = direction
}

const handlePageChange = (page) => {
  // datatableParams.page = page
}

const handleLengthChange = (length) => {
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
