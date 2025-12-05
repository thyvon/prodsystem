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
      <template #cell-is_active="{ value }">
        <span :class="value ? 'badge badge-success' : 'badge badge-secondary'">
          {{ value ? 'Active' : 'Inactive' }}
        </span>
      </template>
    </datatable>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import axios from 'axios'
import { confirmAction, showAlert } from '@/Utils/bootbox'

// Refs & state
const datatableRef = ref(null)
const pageLength = ref(10)

// Datatable parameters
const datatableParams = reactive({
  sortColumn: 'id',
  sortDirection: 'desc',
  page: 1,
  limit: pageLength.value,
  search: '',
})

// Datatable headers
const datatableHeaders = [
  { text: 'Variant Code', value: 'variant_item_code', width: '10%' },
  { text: 'Product Name', value: 'product_name', width: '20%' },
  { text: 'Warehouse', value: 'warehouse_name', width: '15%' },
  { text: 'Alert Quantity', value: 'alert_quantity', width: '10%' },
  { text: 'Active', value: 'is_active', width: '10%' },
  { text: 'Created At', value: 'created_at', width: '15%' },
  { text: 'Updated At', value: 'updated_at', width: '15%' },
]

// API endpoint
const datatableFetchUrl = '/api/inventory/warehouses/products'

// Actions
const datatableActions = ['edit', 'delete', 'preview']

const datatableOptions = {
  responsive: true,
  pageLength: pageLength.value,
  lengthMenu: [[10, 20, 50, 100, 1000], [10, 20, 50, 100, 1000]],
}

// Action handlers
const handleEdit = (row) => {
  window.location.href = `/inventory/warehouses/products/${row.id}/edit`
}

const handlePreview = (row) => {
  window.location.href = `/inventory/warehouses/products/${row.id}/show`
}

const handleDelete = async (row) => {
  const confirmed = await confirmAction(
    `Delete Warehouse Product "${row.variant_item_code}"?`,
    '<strong>Warning:</strong> This action cannot be undone!'
  )
  if (!confirmed) return

  try {
    const response = await axios.delete(`/api/warehouse-products/${row.id}`)
    showAlert('Deleted', response.data.message || `"${row.variant_item_code}" deleted successfully.`, 'success')
    datatableRef.value?.reload()
  } catch (e) {
    console.error(e)
    showAlert('Failed to delete', e.response?.data?.message || 'Something went wrong.', 'danger')
  }
}

// Datatable event handlers
const handleSortChange = ({ column, direction }) => {
  datatableParams.sortColumn = column
  datatableParams.sortDirection = direction
}

const handlePageChange = (page) => {
  datatableParams.page = page
}

const handleLengthChange = (length) => {
  datatableParams.limit = length
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
