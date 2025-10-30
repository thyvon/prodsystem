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
          <button class="btn btn-success" @click="createPurchaseRequest">
            <i class="fal fa-plus"></i> Create Purchase Request
          </button>
          <button class="btn btn-primary" @click="exportPurchaseRequest">
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
  sortColumn: 'id',
  sortDirection: 'desc',
  // Optionally: page: 1, limit: 10, search: ''
})

const datatableHeaders = [
  { text: 'Reference No', value: 'reference_no', width: '12%' },
  { text: 'Request Date', value: 'request_date', width: '10%' },
  { text: 'Deadline', value: 'deadline_date', width: '10%' },
  { text: 'Purpose', value: 'purpose', width: '25%' },
  { text: 'Urgent', value: 'is_urgent', width: '5%' },
  { text: 'Amount', value: 'amount_usd', width: '8%' },
  { text: 'Approval Status', value: 'approval_status', width: '10%' },
  { text: 'Requested By', value: 'creator', width: '15%' },
]

const datatableFetchUrl = '/api/purchase-requests'
const datatableActions = ['edit', 'delete', 'preview']
const datatableOptions = {
  responsive: true,
  pageLength: pageLength.value,
  lengthMenu: [[10, 20, 50, 100, 1000], [10, 20, 50, 100, 1000]],
}

// Action handlers
const createPurchaseRequest = () => {
  window.location.href = '/purchase-requests/create'
}

const handleEdit = (pr) => {
  window.location.href = `/purchase-requests/${pr.id}/edit`
}

const handlePreview = (pr) => {
  window.location.href = `/purchase-requests/${pr.id}/show`
}

const handleDelete = async (pr) => {
  const confirmed = await confirmAction(
    `Delete Purchase Request "${pr.reference_no}"?`,
    '<strong>Warning:</strong> This action cannot be undone!'
  )
  if (!confirmed) return

  try {
    const response = await axios.delete(`/api/purchase-requests/${pr.id}`)
    showAlert('Deleted', response.data.message || `"${pr.reference_no}" deleted successfully.`, 'success')
    datatableRef.value?.reload()
  } catch (e) {
    console.error(e)
    showAlert('Failed to delete', e.response?.data?.message || 'Something went wrong.', 'danger')
  }
}

const exportPurchaseRequest = () => {
  const params = {
    search: datatableParams.search || '',
    sortColumn: datatableParams.sortColumn,
    sortDirection: datatableParams.sortDirection,
  }
  const queryString = new URLSearchParams(params).toString()
  window.location.href = `/api/purchase-requests/export?${queryString}`
}

// Datatable event handlers
const handleSortChange = ({ column, direction }) => {
  datatableParams.sortColumn = column
  datatableParams.sortDirection = direction
}

const handlePageChange = (page) => {
  // Implement if your datatable supports pagination
  // datatableParams.page = page
}

const handleLengthChange = (length) => {
  // Implement if your datatable supports page length
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
