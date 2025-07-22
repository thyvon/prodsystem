<template>
  <div class="container-fluid mt-3">
    <div class="card border mb-0">
      <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
        <h4 class="mb-0 font-weight-bold">Stock Beginnings</h4>
        <div>
          <a :href="createRoute" class="btn btn-primary btn-sm">Create Stock Beginning</a>
        </div>
      </div>
      <div class="card-body">
        <datatable
          ref="datatableRef"
          :headers="datatableHeaders"
          :fetch-url="datatableFetchUrl"
          :fetch-params="datatableParams"
          :options="datatableOptions"
          :actions="['edit', 'delete']"
          :handlers="actionHandlers"
          @sort-change="handleSortChange"
          @page-change="handlePageChange"
          @length-change="handleLengthChange"
          @search-change="handleSearchChange"
          @error="handleError"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import axios from 'axios'
import { showAlert } from '@/Utils/bootbox'

// Refs and state
const datatableRef = ref(null)
const pageLength = ref(10)

// Datatable configuration
const datatableParams = reactive({
  sortColumn: 'created_at',
  sortDirection: 'desc',
  limit: pageLength.value,
  search: '',
})

const datatableHeaders = [
  { text: 'Reference No', value: 'reference_no', width: '15%', sortable: true },
  { text: 'Beginning Date', value: 'beginning_date', width: '15%', sortable: true },
  { text: 'Warehouse', value: 'warehouse_name', width: '20%', sortable: false },
  { text: 'Items', value: 'items', width: '15%', sortable: false },
  { text: 'Created At', value: 'created_at', width: '20%', sortable: true },
  { text: 'Updated At', value: 'updated_at', width: '20%', sortable: true },
]

const datatableFetchUrl = '/api/stock-beginnings'

const datatableOptions = {
  responsive: true,
  pageLength: pageLength.value,
  lengthMenu: [[10, 15, 20, 50, 100], [10, 15, 20, 50, 100]],
}

// Action handlers for dropdown
const actionHandlers = {
  edit: (row) => {
    window.location.href = editRoute(row.id)
  },
  delete: (row) => {
    deleteStockBeginning(row.id)
  },
}

// Routes
const createRoute = window.route('stockBeginnings.create')
const editRoute = (id) => window.route('stockBeginnings.edit', { mainStockBeginning: id })

// Datatable event handlers
const handleSortChange = ({ column, direction }) => {
  datatableParams.sortColumn = column
  datatableParams.sortDirection = direction
  datatableRef.value.reload()
}

const handlePageChange = (page) => {
  datatableParams.page = page
  datatableRef.value.reload()
}

const handleLengthChange = (length) => {
  datatableParams.limit = length
  datatableRef.value.reload()
}

const handleSearchChange = (search) => {
  datatableParams.search = search
  datatableRef.value.reload()
}

const handleError = (message) => {
  showAlert('Error', message, 'danger')
}

// Delete a stock beginning
const deleteStockBeginning = async (id) => {
  if (!confirm('Are you sure you want to delete this stock beginning?')) return

  try {
    await axios.delete(`/api/stock-beginnings/${id}`)
    showAlert('Success', 'Stock beginning deleted successfully.', 'success')
    datatableRef.value.reload()
  } catch (err) {
    showAlert('Error', err.response?.data?.message || 'Failed to delete stock beginning.', 'danger')
  }
}
</script>