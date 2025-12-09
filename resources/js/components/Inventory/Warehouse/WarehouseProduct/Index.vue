<template>
  <div>
    <!-- Datatable -->
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
        <button class="btn btn-primary" @click="openImportModal">
          <i class="fal fa-file-import"></i> Import Warehouse Products
        </button>
      </template>

      <template #cell-is_active="{ value }">
        <span :class="value ? 'badge badge-success' : 'badge badge-secondary'">
          {{ value ? 'Active' : 'Inactive' }}
        </span>
      </template>
    </datatable>

    <!-- Update-only modal -->
    <WarehouseProductModal ref="warehouseProductModal" @submitted="reloadTable" />

    <!-- Import Modal -->
    <div class="modal fade" ref="importModal" tabindex="-1" role="dialog" aria-labelledby="importModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content border-0 shadow-lg">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title font-weight-bold" id="importModalLabel">Import Warehouse Products</h5>
              <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <a href="/sampleExcel/wh_products_import_sample.xlsx" class="btn btn-sm btn-info" download>
                <i class="fal fa-download"></i> Download Sample Excel
              </a>
            </div>

            <div class="form-group">
              <label for="importFile">Select Excel File (.xlsx)</label>
              <div class="custom-file">
                <input 
                  type="file" 
                  class="custom-file-input" 
                  ref="importFileInput" 
                  @change="handleFileChange" 
                  accept=".xlsx,.xls"
                >
                <label class="custom-file-label" for="importFile">{{ importFileName || 'Choose file...' }}</label>
              </div>
            </div>
            <p class="text-muted mt-2">
              Ensure your Excel file matches the template with columns: 
              <strong>product_code, warehouse_name, alert_quantity, is_active</strong>.
            </p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal" aria-label="Close" :disabled="importing">Cancel</button>
            <button type="button" class="btn btn-primary" @click="importFileAction" :disabled="importing">
              <span v-if="importing" class="spinner-border spinner-border-sm mr-1"></span>
              Import
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import axios from 'axios'
import { showAlert, confirmAction } from '@/Utils/bootbox'
import WarehouseProductModal from '@/components/Inventory/Warehouse/WarehouseProduct/Form.vue'

// --- Refs ---
const datatableRef = ref(null)
const warehouseProductModal = ref(null)
const importModal = ref(null)
const importFileInput = ref(null)
const importFile = ref(null)
const importFileName = ref('')
const importing = ref(false)

// --- Datatable state ---
const pageLength = ref(10)
const datatableParams = reactive({
  sortColumn: 'id',
  sortDirection: 'desc',
  page: 1,
  limit: pageLength.value,
  search: '',
})

const datatableHeaders = [
  { text: 'Variant Code', value: 'variant_item_code', minWidth: '100px' },
  { text: 'Product Name', value: 'product_name', minWidth: '300px' },
  { text: 'Warehouse', value: 'warehouse_name', minWidth: '150px' },
  { text: 'Alert Quantity', value: 'alert_quantity', minWidth: '100px' },
  { text: 'Active', value: 'is_active', minWidth: '100px' },
  { text: 'Created At', value: 'created_at', minWidth: '150px' },
  { text: 'Updated At', value: 'updated_at', minWidth: '150px' },
]

const datatableFetchUrl = '/api/inventory/warehouses/products'
const datatableActions = ['edit', 'delete', 'preview']

const datatableOptions = {
  autoWidth: false,
  responsive: false,
  pageLength: pageLength.value,
  lengthMenu: [[10, 20, 50, 100, 1000], [10, 20, 50, 100, 1000]],
}

// --- Action handlers ---
const handleEdit = (row) => {
  warehouseProductModal.value.show(row.id)
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

// --- Datatable events ---
const handleSortChange = ({ column, direction }) => {
  datatableParams.sortColumn = column
  datatableParams.sortDirection = direction
}

const handlePageChange = (page) => { datatableParams.page = page }
const handleLengthChange = (length) => { datatableParams.limit = length }
const handleSearchChange = (search) => { datatableParams.search = search }

const reloadTable = () => {
  datatableRef.value?.reload()
}

// --- Import modal ---
const openImportModal = () => { $(importModal.value).modal('show') }

const handleFileChange = (event) => {
  importFile.value = event.target.files[0]
  importFileName.value = importFile.value?.name || ''
  $(importFileInput.value).next('.custom-file-label').html(importFileName.value)
}

// Update-only import action
const importFileAction = async () => {
  if (!importFile.value) {
    showAlert('Error', 'Please select a file to import.', 'warning')
    return
  }

  const formData = new FormData()
  formData.append('file', importFile.value)

  try {
    importing.value = true

    // Send file to backend
    const res = await axios.post('/inventory/warehouses/products/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    // Show backend message (success)
    showAlert('Success', res.data.message, 'success')

    // Reload datatable
    datatableRef.value?.reload()

    // Close modal & reset
    $(importModal.value).modal('hide')
    importFile.value = null
    importFileName.value = ''
    $(importFileInput.value).val('')

  } catch (err) {
    console.error(err)

    // Get error message from backend
    const msg = err.response?.data?.message || 'Something went wrong.'

    // Show backend error
    showAlert('Error', msg, 'danger')
  } finally {
    importing.value = false
  }
}


// --- Map datatable actions ---
const datatableHandlers = {
  edit: handleEdit,
  delete: handleDelete,
  preview: handlePreview,
}
</script>
