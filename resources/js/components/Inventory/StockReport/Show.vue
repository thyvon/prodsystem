<template>
  <div class="card mb-0 shadow">

    <!-- Header -->
    <div class="card-header bg-light py-2 d-flex justify-between items-center">
      <button class="btn btn-sm btn-outline-success" @click="goBack">
        <i class="fal fa-backward"></i> Back
      </button>
      <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-secondary" @click="fetchStockReport">
          <i class="fal fa-sync"></i> Refresh
        </button>
        <button class="btn btn-sm btn-outline-primary" @click="openPdfModal">
          <i class="fal fa-print"></i> Print PDF
        </button>
      </div>
    </div>

    <!-- Body -->
    <div class="card-body p-3" style="font-family: 'TW Cen MT', 'Khmer OS Battambang';">

      <h4 class="text-center font-weight-bold mb-3">Monthly Stock Report</h4>
      <h6 class="text-center text-muted mb-4">
        {{ formatDate(reportParams.start_date) }} - {{ formatDate(reportParams.end_date) }}
        ({{ warehouseNames }})
      </h6>

      <!-- Stock Table -->
      <div class="table-responsive">
        <table class="table table-bordered table-sm">
          <thead class="table-secondary">
            <tr>
              <th>#</th>
              <th>Item Code</th>
              <th>Description</th>
              <th>Unit</th>
              <th>Beginning Qty</th>
              <th>Beginning Amount</th>
              <th>Stock In Qty</th>
              <th>Stock In Amount</th>
              <th>Available Qty</th>
              <th>Available Amount</th>
              <th>Stock Out Qty</th>
              <th>Stock Out Amount</th>
              <th>Ending Qty</th>
              <th>Ending Amount</th>
              <th>Avg Price</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, i) in stockItems" :key="i">
              <td class="text-center">{{ i + 1 }}</td>
              <td>{{ item.item_code }}</td>
              <td>{{ item.description || item.product_name || '-' }}</td>
              <td>{{ item.unit_name }}</td>
              <td class="text-center">{{ format(item.beginning_quantity) }}</td>
              <td class="text-end">{{ format(item.beginning_total) }}</td>
              <td class="text-center">{{ format(item.stock_in_quantity) }}</td>
              <td class="text-end">{{ format(item.stock_in_total) }}</td>
              <td class="text-center bg-success-light">{{ format(item.available_quantity) }}</td>
              <td class="text-end bg-success-light">{{ format(item.available_total) }}</td>
              <td class="text-center">{{ format(item.stock_out_quantity) }}</td>
              <td class="text-end">{{ format(item.stock_out_total) }}</td>
              <td class="text-center">{{ format(item.ending_quantity) }}</td>
              <td class="text-end">{{ format(item.ending_total) }}</td>
              <td class="text-end">{{ format(item.average_price) }}</td>
            </tr>
            <tr class="table-secondary font-weight-bold">
              <td colspan="4" class="text-end">Total</td>
              <td class="text-center">{{ format(total('beginning_quantity')) }}</td>
              <td class="text-end">{{ format(total('beginning_total')) }}</td>
              <td class="text-center">{{ format(total('stock_in_quantity')) }}</td>
              <td class="text-end">{{ format(total('stock_in_total')) }}</td>
              <td class="text-center bg-success-light">{{ format(total('available_quantity')) }}</td>
              <td class="text-end bg-success-light">{{ format(total('available_total')) }}</td>
              <td class="text-center">{{ format(total('stock_out_quantity')) }}</td>
              <td class="text-end">{{ format(total('stock_out_total')) }}</td>
              <td class="text-center">{{ format(total('ending_quantity')) }}</td>
              <td class="text-end">{{ format(total('ending_total')) }}</td>
              <td></td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Requested By & Approval Cards -->
      <div class="mt-5">
        <h5 class="mb-3 font-weight-bold">Report Approval Summary</h5>
        <div class="row justify-content-center">

          <!-- Requested By -->
          <div class="col-md-3 mb-4">
            <div class="card border shadow-sm h-100">
              <div class="card-body text-center">
                <p class="font-weight-bold mb-1">Requested By</p>
                <div class="mb-2">
                  <img :src="reportParams.created_by?.profile_url" class="rounded-circle" width="50" height="50">
                </div>
                <p class="font-weight-bold mb-1">{{ reportParams.created_by?.name || 'N/A' }}</p>
                <div v-if="reportParams.created_by?.signature_url">
                  <img :src="reportParams.created_by.signature_url" height="50">
                </div>
                <p class="mb-1">Position: {{ reportParams.creator_position?.title || 'N/A' }}</p>
                <p class="mb-0">Date: {{ formatDate(reportParams.created_at) }}</p>
              </div>
            </div>
          </div>

          <!-- Approvers -->
          <div v-for="(resp, idx) in responders" :key="idx" class="col-md-3 mb-4">
            <div class="card border shadow-sm h-100">
              <div class="card-body text-center">
                <p class="font-weight-bold mb-1">{{ resp.request_type_label || resp.request_type }} By</p>
                <div class="mb-2">
                  <img :src="resp.user_profile_url" class="rounded-circle" width="50" height="50">
                </div>
                <p class="font-weight-bold mb-1">{{ resp.user_name }}</p>
                <div v-if="resp.approval_status === 'Approved' && resp.signature_url">
                  <img :src="resp.signature_url" height="50">
                </div>
                <p class="mb-1">
                  Status:
                  <span class="badge"
                        :class="{
                          'badge-success': resp.approval_status === 'Approved',
                          'badge-danger': resp.approval_status === 'Rejected',
                          'badge-warning': resp.approval_status === 'Pending',
                          'badge-info': resp.approval_status === 'Returned'
                        }">
                    <strong>{{ resp.approval_status === 'Approved' ? 'Signed' : resp.approval_status }}</strong>
                  </span>
                </p>
                <p class="mb-1">Position: {{ resp.position_name }}</p>
                <p class="mb-0">Date: {{ resp.responded_at ? formatDate(resp.responded_at) : '-' }}</p>
                <p class="mb-0">Comment: {{ resp.remarks || '-' }}</p>
              </div>
            </div>
          </div>

          <div v-if="responders.length === 0" class="col-12 text-center">
            No approvals available.
          </div>
        </div>

        <!-- Approval Buttons -->
        <div class="mt-3 d-flex justify-center gap-2 flex-wrap" v-if="approvalButton">
          <button class="btn btn-success" @click="openConfirmModal('approve')">
            <i class="fal fa-check"></i> Approve
          </button>
          <button class="btn btn-danger" @click="openConfirmModal('reject')">
            <i class="fal fa-times"></i> Reject
          </button>
          <button class="btn btn-warning" @click="openConfirmModal('return')">
            <i class="fal fa-undo"></i> Return
          </button>
          <button class="btn btn-primary" @click="openReassignModal">
            <i class="fal fa-exchange"></i> Reassign
          </button>
        </div>
        <p class="text-center text-muted mt-2" v-else>No approval actions available.</p>
      </div>
    </div>

    <!-- PDF Modal -->
    <StockReportModal ref="pdfViewer" title="Monthly Stock Report PDF" />

    <!-- Confirm Approval Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ currentActionTitle }}</h5>
            <button type="button" class="close" @click="resetConfirmModal">&times;</button>
          </div>
          <div class="modal-body">
            <textarea v-model="commentInput" class="form-control" rows="4" placeholder="Enter comment (optional)" :disabled="loading"></textarea>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="resetConfirmModal" :disabled="loading">Cancel</button>
            <button class="btn" :class="currentActionClass" @click="submitApproval(currentAction)" :disabled="loading">
              {{ currentActionButtonLabel }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Reassign Modal -->
    <div class="modal fade" id="reassignModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Reassign Approval</h5>
            <button type="button" class="close" @click="cleanupReassignModal">&times;</button>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label>Select New Responder</label>
              <select id="userSelect" class="form-control w-100"></select>
            </div>
            <div class="form-group">
              <label>Comment (optional)</label>
              <textarea id="reassignComment" class="form-control" rows="3"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="cleanupReassignModal">Cancel</button>
            <button class="btn btn-primary" @click="confirmReassign">Reassign</button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted, nextTick, computed } from 'vue'
import axios from 'axios'
import StockReportModal from '@/components/Reusable/StockReportModal.vue'
import { showAlert } from '@/Utils/bootbox'
import { formatDateShort } from '@/Utils/dateFormat'
import { initSelect2, destroySelect2 } from '@/Utils/select2'

const props = defineProps({ monthlyStockReportId: Number })

const stockItems = ref([])
const reportParams = ref({})
const warehouseNames = ref('All Warehouses')
const responders = ref([])
const approvalButton = ref(false)
const pdfViewer = ref(null)
const loading = ref(false)
const usersList = ref([])
const commentInput = ref('')
const currentAction = ref('approve')
const usersByType = ref({
  check: [],
  verify: [],
  acknowledge: []
})


// --- Helper Functions ---
const format = val => (!val || Number(val) === 0 ? '-' : Number(val).toLocaleString(undefined, { minimumFractionDigits: 2 }))
const formatDate = date => formatDateShort(date)
const total = key => stockItems.value.reduce((sum, i) => sum + (i[key] || 0), 0)
const capitalize = s => s?.charAt(0).toUpperCase() + s.slice(1)
const goBack = () => window.history.back()
const currentActionTitle = computed(() => currentAction.value === 'approve' ? 'Approve Report' :
                                              currentAction.value === 'reject' ? 'Reject Report' : 'Return Report')
const currentActionClass = computed(() => currentAction.value === 'approve' ? 'btn-success' :
                                              currentAction.value === 'reject' ? 'btn-danger' : 'btn-warning')
const currentActionButtonLabel = computed(() => currentAction.value === 'approve' ? 'Approve' :
                                                        currentAction.value === 'reject' ? 'Reject' : 'Return')

// --- Fetch Report ---
const fetchStockReport = async () => {
  try {
    const res = await axios.get(`/api/inventory/stock-reports/monthly-report/${props.monthlyStockReportId}/details`)
    Object.assign(reportParams.value, res.data)
    warehouseNames.value = res.data.warehouse_names || 'All Warehouses'
    stockItems.value = res.data.report || []
    responders.value = res.data.responders || []
    approvalButton.value = res.data.approvalButton || false
    usersList.value = res.data.usersList || []
  } catch(err){ showAlert('Error', err.response?.data?.message || 'Failed to load report', 'danger') }
}

// --- PDF Modal ---
const openPdfModal = () => pdfViewer.value.open(`/inventory/stock-reports/monthly-report/${props.monthlyStockReportId}/show`)

// --- Approval Handling ---
const openConfirmModal = (action) => { currentAction.value = action; commentInput.value = ''; $('#confirmModal').modal('show') }
const resetConfirmModal = () => { commentInput.value = ''; $('#confirmModal').modal('hide') }
const submitApproval = async (action) => {
  loading.value = true
  try {
    const res = await axios.post(`/api/inventory/stock-reports/monthly-report/${props.monthlyStockReportId}/${action}`, { comment: commentInput.value })
    showAlert('success', res.data.message || 'Action successful.')
    $('#confirmModal').modal('hide')
    fetchStockReport()
  } catch(err){ showAlert('Error', err.response?.data?.message || 'Action failed', 'danger') }
  finally{ loading.value = false }
}

// --- Reassign Handling ---
const openReassignModal = async () => {
  loading.value = true
  try {
    const res = await axios.get(`/api/inventory/stock-reports/get-approval-users`, {
      params: { request_type: props.approvalRequestType || 'approve' }
    })

    // Save the grouped users
    usersByType.value = res.data || { check: [], verify: [], acknowledge: [] }

    // Choose current group based on approval type
    const currentGroup = usersByType.value[props.approvalRequestType] || []

    // Populate Select2
    await nextTick()
    const selectEl = document.getElementById('userSelect')
    selectEl.innerHTML = '<option value="">-- Select a user --</option>' +
      currentGroup.map(u => `<option value="${u.id}">${u.name} ${u.card_number ? '(' + u.card_number + ')' : ''}</option>`).join('')
    
    initSelect2(selectEl, { width: '100%', dropdownParent: $('#reassignModal') })
    $('#reassignModal').modal('show')

  } catch(err) {
    showAlert('Error', 'Failed to load users.', 'danger')
  } finally { loading.value = false }
}


const confirmReassign = async () => {
  const newUserId = document.getElementById('userSelect')?.value
  const comment = document.getElementById('reassignComment')?.value.trim()
  if (!newUserId){ showAlert('Error', 'Please select a user.', 'danger'); return }
  loading.value = true
  try {
    await axios.post(`/api/inventory/stock-reports/monthly-report/${props.monthlyStockReportId}/reassign`, { new_user_id: newUserId, comment })
    showAlert('success', 'Approval reassigned successfully.')
    $('#reassignModal').modal('hide')
    destroySelect2(document.getElementById('userSelect'))
    fetchStockReport()
  } catch(err){ showAlert('Error', err.response?.data?.message || 'Reassign failed.', 'danger') }
  finally{ loading.value = false }
}

const cleanupReassignModal = () => { destroySelect2(document.getElementById('userSelect')) }

onMounted(fetchStockReport)
</script>

<style scoped>
.table-responsive { max-height:600px; overflow-y:auto; }
.bg-success-light { background-color:#e6f7e6 !important; }
.text-center { text-align:center !important; }
.text-end { text-align:right !important; }
.select2-container--default .select2-dropdown { z-index: 1060 !important; }
</style>
