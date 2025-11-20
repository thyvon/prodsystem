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

    <!-- FULLSCREEN PDF MODAL -->
    <div class="modal fade modal-fullscreen modal-backdrop-transparent" id="pdfModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Monthly Stock Report PDF</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close" @click="closePdfModal">
            <span aria-hidden="true">&times;</span>
            </button>
        </div>

        <div class="modal-body p-0 position-relative" style="height: 80vh;">
            <!-- SmartAdmin Loader Overlay -->
            <div
            v-if="isGeneratingPdf"
            class="position-absolute top-0 start-0 w-100 h-100 d-flex justify-content-center align-items-center"
            style="z-index: 10;"
            >
            <div class="text-center">
                <i class="spinner-border spinner-border-lg text-primary" role="status" aria-hidden="true"></i>
                <p class="mt-2 mb-0">Generating Report...</p>
            </div>
            </div>

            <!-- PDF IFRAME -->
            <iframe
            v-if="pdfUrl"
            :src="pdfUrl"
            style="width: 100%; height: 100%; border:0;"
            @load="isGeneratingPdf = false"
            ></iframe>
        </div>

        <!-- MODAL FOOTER BUTTON WITH OVERLAY -->
        <div class="modal-footer position-relative">
            <button
            class="btn btn-primary"
            :disabled="isDownloading"
            @click="downloadPdf"
            >
            <i v-if="isDownloading" class="spinner-border spinner-border-sm mr-1"></i>
            {{ isDownloading ? 'Downloading...' : 'Download PDF' }}
            </button>
        </div>

        <!-- Optional overlay for the button only -->
        <div
            v-if="isDownloading"
            class="position-absolute top-0 start-0 w-100 h-100 d-flex justify-content-center align-items-center"
            style="z-index: 20; background: rgba(255,255,255,0.5);"
        ></div>
        </div>
    </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
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
const datatableActions = ['detail', 'edit', 'delete', 'preview']
const datatableOptions = { autoWidth: false, responsive: true, pageLength: 10 }

// -------------------- PDF & MODAL --------------------
const pdfUrl = ref(null)
const isGeneratingPdf = ref(false)

// -------------------- DATATABLE HANDLERS --------------------
const createMonthlyReport = () => window.location.href = '/inventory/stock-reports/monthly-report/create'
const handleView = report => window.location.href = `/inventory/stock-reports/monthly-report/${report.id}/show`
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

// -------------------- PDF PREVIEW --------------------
const handlePreview = async report => {
  try {
    // Show modal & loader immediately
    isGeneratingPdf.value = true
    pdfUrl.value = null
    $('#pdfModal').modal('show')

    const res = await axios.post(
      `/inventory/stock-reports/monthly-report/${report.id}/show`,
      {},
      { responseType: 'blob', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') } }
    )

    const blob = new Blob([res.data], { type: 'application/pdf' })
    pdfUrl.value = URL.createObjectURL(blob)
  } catch (err) {
    console.error(err)
    alert('Failed to generate PDF preview')
    isGeneratingPdf.value = false
    $('#pdfModal').modal('hide')
  }
}

// Close modal & clean up
const closePdfModal = () => {
  $('#pdfModal').modal('hide')
  if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value)
  pdfUrl.value = null
  isGeneratingPdf.value = false
}

// DATATABLE EVENTS
const datatableHandlers = { detail: handleView, edit: handleEdit, delete: handleDelete, preview: handlePreview }
const handleSortChange = ({ column, direction }) => { datatableParams.sortColumn = column; datatableParams.sortDirection = direction }
const handlePageChange = page => datatableParams.page = page
const handleLengthChange = length => datatableParams.limit = length
const handleSearchChange = search => datatableParams.search = search

// -------------------- ON MOUNT --------------------
onMounted(() => {
  $('#pdfModal').on('hidden.bs.modal', () => {
    if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value)
    pdfUrl.value = null
    isGeneratingPdf.value = false
  })
})
</script>
