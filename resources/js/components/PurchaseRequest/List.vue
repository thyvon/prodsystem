<template>
  <div>
    <!-- ========================= -->
    <!-- Datatable -->
    <!-- ========================= -->
    <datatable
      ref="datatableRef"
      :headers="datatableHeaders"
      :fetch-url="datatableFetchUrl"
      :fetch-params="datatableParams"
      :actions="datatableActions"
      :handlers="datatableHandlers"
      :options="datatableOptions"
      @sort-change="handleSortChange"
      @search-change="handleSearchChange"
    >

      <!-- ========================= -->
      <!-- Additional Header: Buttons + Filters -->
      <!-- ========================= -->
      <template #additional-header>
        <div class="d-flex align-items-center gap-2 flex-wrap">

          <!-- Buttons -->
          <div class="btn-group">
            <button class="btn btn-success" @click="createPurchaseRequest">
              <i class="fal fa-plus"></i> Create Purchase Request
            </button>

            <button class="btn btn-primary" @click="exportPurchaseRequest">
              <i class="fal fa-download"></i> Export
            </button>

            <button class="btn btn-primary" @click="openImportModal">
              <i class="fal fa-upload"></i> Import
            </button>
          </div>

          <!-- Status Dropdown -->
        <div class="custom-select-wrapper w-auto ml-2">
        <select class="custom-select" v-model="datatableParams.status">
            <option value="">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
            <option value="cancelled">Cancelled</option>
        </select>
        </div>

          <!-- Trash Checkbox -->
        <div class="custom-control custom-checkbox ml-2 custom-trash-checkbox">
        <input
            class="custom-control-input"
            type="checkbox"
            id="trashCheckbox"
            v-model="datatableParams.trashed"
        />
        <label class="custom-control-label" for="trashCheckbox">
            Show Trash <i class="fal fa-trash"></i>
        </label>
        </div>
        </div>
      </template>

    </datatable>

    <!-- ========================= -->
    <!-- Import Purchase Request Modal -->
    <!-- ========================= -->
    <div
      class="modal fade"
      ref="importModal"
      tabindex="-1"
      role="dialog"
      aria-labelledby="importPRModalLabel"
      aria-hidden="true"
    >
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content border-0 shadow-lg">

          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title font-weight-bold" id="importPRModalLabel">
              Import Purchase Requests
            </h5>
            <button
              type="button"
              class="close text-white"
              data-dismiss="modal"
              :disabled="importing"
            >
              <span>&times;</span>
            </button>
          </div>

          <div class="modal-body">

            <!-- Download Sample -->
            <div class="mb-3">
              <a
                href="/sampleExcel/purchase_request_import_sample.xlsx"
                class="btn btn-sm btn-info"
                download
              >
                <i class="fal fa-download"></i> Download Sample Excel
              </a>
            </div>

            <!-- File Upload -->
            <div class="form-group">
              <label>Select Excel File (.xlsx, .xls, .csv)</label>

              <div class="custom-file">
                <input
                  type="file"
                  class="custom-file-input"
                  ref="importFileInput"
                  @change="handleFileChange"
                  accept=".xlsx,.xls,.csv"
                >
                <label class="custom-file-label">
                  {{ importFileName || 'Choose file...' }}
                </label>
              </div>
            </div>

            <p class="text-muted mt-2">
              Ensure your Excel file matches the provided template.
            </p>

          </div>

          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-secondary"
              data-dismiss="modal"
              :disabled="importing"
            >
              Cancel
            </button>

            <button
              type="button"
              class="btn btn-primary"
              @click="importFileAction"
              :disabled="!selectedFile || importing"
            >
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
import { ref, reactive, onMounted, watch } from 'vue'
import axios from 'axios'
import { confirmAction, showAlert } from '@/Utils/bootbox'

/* =========================
   State
========================= */
const datatableRef = ref(null)
const importing = ref(false)
const selectedFile = ref(null)
const importFileName = ref('')
const importModal = ref(null)

/* =========================
   Datatable Config
========================= */
const datatableParams = reactive({
  sortColumn: 'id',
  sortDirection: 'desc',
  search: '',
  status: '',
  trashed: false,
})

const datatableHeaders = [
  { text: 'Reference No', value: 'reference_no', minWidth: '100px' },
  { text: 'Request Date', value: 'request_date' },
  { text: 'Deadline', value: 'deadline_date' },
  { text: 'Purpose', value: 'purpose' },
  { text: 'Urgent', value: 'is_urgent' },
  { text: 'Amount', value: 'amount_usd', minWidth: '100px'},
  { text: 'Approval Status', value: 'approval_status' },
  { text: 'Requested By', value: 'creator' },
]

const datatableFetchUrl = '/api/purchase-requests'
const datatableActions = ['preview', 'edit', 'delete', 'restore', 'forceDelete']
const datatableOptions = { responsive: true }

/* =========================
   Watch Filters to Reload
========================= */
watch(
  () => [datatableParams.status, datatableParams.trashed],
  () => datatableRef.value?.reload()
)

/* =========================
   Modal Logic
========================= */
const openImportModal = () => window.$(importModal.value).modal('show')

const handleFileChange = (e) => {
  const file = e.target.files[0]
  selectedFile.value = file || null
  importFileName.value = file?.name || ''
}

const importFileAction = async () => {
  if (!selectedFile.value || importing.value) return;
  importing.value = true;

  const formData = new FormData();
  formData.append('file', selectedFile.value);

  try {
    const response = await axios.post(
      '/api/purchase-requests/import-purchase-requests',
      formData,
      { headers: { 'Content-Type': 'multipart/form-data' } }
    );

    const warnings = Array.isArray(response.data?.errors) ? response.data.errors : [];
    if (warnings.length) {
      const warningHtml = `<ul style="text-align:left; padding-left:20px;">${warnings.slice(0,20).map(w=>`<li>${w}</li>`).join('')}</ul>${warnings.length>20?`<p>...and ${warnings.length-20} more warning(s).</p>`:''}`;
      showAlert('Import Completed with Warnings', warningHtml, 'warning');
    } else {
      showAlert('Import Successful', response.data.message || 'Purchase Requests imported successfully.', 'success');
    }

    datatableRef.value?.reload();
    window.$(importModal.value).modal('hide');

  } catch (err) {
    const response = err.response?.data;
    let errorMessage = 'Failed to import file.';
    if (response?.errors) {
      if (typeof response.errors === 'object' && !Array.isArray(response.errors)) {
        const flatErrors = Object.values(response.errors).flat();
        errorMessage = `<ul style="text-align:left; padding-left:20px;">${flatErrors.map(e=>`<li>${e}</li>`).join('')}</ul>`;
      } else if (Array.isArray(response.errors)) {
        errorMessage = `<ul style="text-align:left; padding-left:20px;">${response.errors.map(e=>`<li>${e}</li>`).join('')}</ul>`;
      }
    } else if (response?.message) {
      errorMessage = response.message;
    }
    showAlert('Import Failed', errorMessage, 'danger');
  } finally {
    importing.value = false;
  }
}

/* =========================
   Reset Modal on Close
========================= */
onMounted(() => {
  window.$(importModal.value).on('hidden.bs.modal', () => {
    selectedFile.value = null
    importFileName.value = ''
  })
})

/* =========================
   Other Actions
========================= */
const createPurchaseRequest = () => window.location.href = '/purchase-requests/create'
const exportPurchaseRequest = () => window.location.href = `/api/purchase-requests/export?${new URLSearchParams(datatableParams).toString()}`

const handleEdit = (pr) => window.location.href = `/purchase-requests/${pr.id}/edit`
const handlePreview = (pr) => window.location.href = `/purchase-requests/${pr.id}/show`

const handleDelete = async (pr) => {
  const confirmed = await confirmAction(`Delete Purchase Request "${pr.reference_no}"?`, 'This action cannot be undone!')
  if (!confirmed) return
  try {
    await axios.delete(`/api/purchase-requests/${pr.id}`)
    datatableRef.value?.reload()
    showAlert('Success','Purchase Request deleted successfully.','success');
  } catch (err) {
    showAlert('Error','Failed to delete Purchase Request.','danger');
  }
}

const handleRestore = async (pr) => {
  const confirmed = await confirmAction(`Restore Purchase Request "${pr.reference_no}"?`, 'This will bring back the record from trash.')
  if (!confirmed) return
  try {
    await axios.post(`/api/purchase-requests/${pr.id}/restore`);
    datatableRef.value?.reload();
    showAlert('Success','Purchase Request restored successfully.','success');
  } catch {
    showAlert('Error','Failed to restore Purchase Request.','danger');
  }
}

const handleForceDelete = async (pr) => {
  const confirmed = await confirmAction(`Permanently delete Purchase Request "${pr.reference_no}"?`, 'This action cannot be undone!')
  if (!confirmed) return
  try {
    await axios.delete(`/api/purchase-requests/${pr.id}/force-delete`);
    datatableRef.value?.reload();
    showAlert('Success','Purchase Request permanently deleted.','success');
  } catch {
    showAlert('Error','Failed to permanently delete Purchase Request.','danger');
  }
}

/* =========================
   Handlers: now always call, buttons handle disabled logic
========================= */
const datatableHandlers = {
  preview: handlePreview,
  edit: handleEdit,
  delete: handleDelete,
  restore: handleRestore,
  forceDelete: handleForceDelete,
}

const handleSortChange = ({ column, direction }) => {
  datatableParams.sortColumn = column
  datatableParams.sortDirection = direction
}

const handleSearchChange = (search) => datatableParams.search = search
</script>

<style>
/* Custom style for your trash checkbox */
.custom-trash-checkbox .custom-control-input:checked ~ .custom-control-label {
  color: #1d4ed8; /* Blue text when checked */
  font-weight: 600;
}

.custom-trash-checkbox .custom-control-input:checked ~ .custom-control-label i {
  color: #dc2626; /* Red trash icon when checked */
}

.custom-trash-checkbox .custom-control-label {
  display: flex;
  align-items: center;
  gap: 0.25rem; /* small space between text and icon */
  transition: color 0.2s ease;
}

.custom-trash-checkbox .custom-control-input {
  border-radius: 0.25rem;
  width: 1.2rem;
  height: 1.2rem;
  cursor: pointer;
  transition: border-color 0.2s ease, background-color 0.2s ease;
}

.custom-trash-checkbox .custom-control-input:focus {
  box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25); /* focus glow */
}
</style>
