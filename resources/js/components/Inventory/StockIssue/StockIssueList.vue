<template>
  <div class="stock-issue-items-table">
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
          <button class="btn btn-primary" @click="openImportModal">
            <i class="fal fa-file-import"></i> Import
          </button>
          <button class="btn btn-info" @click="goToStockIssueItems">
            <i class="fal fa-list"></i> Stock Issue Items
          </button>
        </div>
      </template>
    </datatable>

    <!-- Import Modal -->
    <div ref="importModal" class="modal fade" tabindex="-1" role="dialog">
      <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Import Stock Issues</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
          <div class="mb-2">
            <a href="/sampleExcel/stock_issues_sample.xlsx" class="btn btn-sm btn-info" download>
              <i class="fal fa-download"></i> Export Sample
            </a>
          </div>


            <div class="form-group">
              <label class="font-weight-bold">Select File</label>
              <div class="custom-file">
                <input
                  type="file"
                  class="custom-file-input"
                  ref="importFileInput"
                  accept=".xlsx,.csv"
                  @change="handleFileChange"
                />
                <label class="custom-file-label" for="importFileInput">
                  {{ importFileName || 'Choose file' }}
                </label>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-success" @click="importFileAction" :disabled="importing">
              <span v-if="importing" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
              <i v-else class="fal fa-file-import"></i> Import
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

const goToStockIssueItems = () => {
  window.location.href = '/inventory/stock-issue/items' // adjust the route if needed
}


// -------------------- DATATABLE --------------------
const datatableRef = ref(null)
const pageLength = ref(10)
const datatableParams = reactive({ sortColumn: 'created_at', sortDirection: 'desc', search: '' })

const datatableHeaders = [
  { text: 'Issue Date', value: 'transaction_date', minWidth: '120px' },
  { text: 'Reference No', value: 'reference_no', minWidth: '100px' },
  { text: 'Request No', value: 'request_number', minWidth: '100px' },
  { text: 'Warehouse', value: 'warehouse_name', minWidth: '180px' },
  { text: 'Warehouse Campus', value: 'warehouse_campus_name', minWidth: '120px' },
  { text: 'Quantity', value: 'quantity', minWidth: '80px' },
  { text: 'Amount', value: 'total_price', minWidth: '100px' },
  { text: 'Issued By', value: 'created_by', minWidth: '120px' },
  { text: 'Requester', value: 'requester_name', minWidth: '140px' },
  { text: 'Campus', value: 'requester_campus_name', minWidth: '120px' },
];



const datatableFetchUrl = '/api/inventory/stock-issues'
const datatableActions = ['edit', 'delete', 'preview']
const datatableOptions = {
  autoWidth: false,
  responsive: false,
  pageLength: pageLength.value,
  lengthMenu: [[10, 20, 50, 100, 1000], [10, 20, 50, 100, 1000]],
}

// -------------------- DATATABLE ACTIONS --------------------
const createStockIssue = () => window.location.href = '/inventory/stock-issues/create'
const handleEdit = (stockIssue) => window.location.href = `/inventory/stock-issues/${stockIssue.id}/edit`
const handlePreview = (stockIssue) => window.location.href = `/inventory/stock-issues/${stockIssue.id}/show`

const handleDelete = async (stockIssue) => {
  const confirmed = await confirmAction(
    `Delete Stock Issue "${stockIssue.reference_no}"?`,
    '<strong>Warning:</strong> This action cannot be undone!'
  )
  if (!confirmed) return
  try {
    const response = await axios.delete(`/api/inventory/stock-issues/${stockIssue.id}`)
    showAlert('Deleted', response.data.message || `"${stockIssue.reference_no}" was deleted successfully.`, 'success')
    datatableRef.value?.reload()
  } catch (e) {
    console.error(e)
    showAlert('Failed to delete', e.response?.data?.message || 'Something went wrong.', 'danger')
  }
}

const datatableHandlers = { edit: handleEdit, delete: handleDelete, preview: handlePreview }
const handleSortChange = ({ column, direction }) => { datatableParams.sortColumn = column; datatableParams.sortDirection = direction }
const handlePageChange = (page) => {}
const handleLengthChange = (length) => {}
const handleSearchChange = (search) => { datatableParams.search = search }

// -------------------- IMPORT --------------------
const importModal = ref(null)
const importFileInput = ref(null)
const importFile = ref(null)
const importFileName = ref('')
const importing = ref(false)

const openImportModal = () => { $(importModal.value).modal('show') }

const handleFileChange = (event) => {
  importFile.value = event.target.files[0]
  importFileName.value = importFile.value?.name || ''
  // update label for Bootstrap custom-file-input
  $(importFileInput.value).next('.custom-file-label').html(importFileName.value)
}

const importFileAction = async () => {
  if (!importFile.value) {
    showAlert('Error', 'Please select a file to import.', 'warning')
    return
  }

  const formData = new FormData()
  formData.append('file', importFile.value)

  const flatten = v => {
    if (!v) return null
    if (typeof v === 'string') return v
    if (Array.isArray(v)) return v.join('; ')
    return Object.values(v).flat().join('; ')
  }

  try {
    importing.value = true
    const res = await axios.post('/api/inventory/stock-issues/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    showAlert('Success', res.data.message || 'Import completed!', 'success')
    datatableRef.value?.reload()
    $(importModal.value).modal('hide')
    importFile.value = null
    importFileName.value = ''
    $(importFileInput.value).val('')
  } catch (err) {
    console.error(err)
    const d = err.response?.data
    const msg = d?.message ?? d?.error ?? flatten(d?.errors) ?? err.message ?? 'Something went wrong.'
    showAlert('Error', msg, 'danger')
  } finally {
    importing.value = false
  }
}
</script>
