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
        <button class="btn btn-sm btn-outline-primary" @click="printReport">
          <i class="fal fa-print"></i> Print Report
        </button>
      </div>
    </div>

    <!-- Body -->
    <div class="card-body p-3">

      <!-- Header: Logo + Title -->
      <div class="row align-items-center mb-3">
        
        <!-- Left Column: Logo -->
        <div class="col-3 d-flex align-items-center">
          <img src="@public/img/logo/logo-dark.png" alt="Logo" style="height:50px;">
        </div>
        
        <!-- Center Column: Title -->
        <div class="col-6 text-center">
          <h4 class="font-weight-bold mb-0">Monthly Stock Report</h4>
          <h6 class="text-muted">
            {{ formatDate(reportParams.start_date) }} - {{ formatDate(reportParams.end_date) }}
            ({{ warehouseNames }})
          </h6>
        </div>
        
        <!-- Right Column: Blank -->
        <div class="col-3">
          <!-- Blank or future content -->
        </div>
        
      </div>
      <div class="dropdown-divider mb-1"></div>

      <!-- Stock Table -->
      <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
        <table class="table table-bordered table-hover table-striped align-middle table-sm">
          <thead class="table-light sticky-header">
            <tr>
              <th>#</th>
              <th style="min-width: 100px;">Item Code</th>
              <th style ="min-width: 300px;">Description</th>
              <th>Unit</th>
              <th>Beginning Qty</th>
              <th>Beginning Price</th>
              <th>Beginning Amount</th>
              <th>Stock In Qty</th>
              <th>Stock In Amount</th>
              <th>Available Qty</th>
              <th>Available Price</th>
              <th>Available Amount</th>
              <th>Stock Out Qty</th>
              <th>Stock Out Amount</th>
              <th>Ending Qty</th>
              <th>Counted Qty</th>
              <th>Variance</th>
              <th>Carried Forward</th>
              <th>Avg Price</th>
              <th>Ending Amount</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, i) in stockItems" :key="i">
              <td class="text-center">{{ i + 1 }}</td>
              <td>{{ item.item_code }}</td>
              <td>{{ item.description || item.product_name || '-' }}</td>
              <td>{{ item.unit_name }}</td>
              <td class="text-center">{{ formatQty(item.beginning_quantity) }}</td>
              <td class="text-end">{{ formatAmount(item.beginning_price) }}</td>
              <td class="text-end">{{ formatAmount(item.beginning_total) }}</td>
              <td class="text-center">{{ formatQty(item.stock_in_quantity) }}</td>
              <td class="text-end">{{ formatAmount(item.stock_in_total) }}</td>
              <td class="text-center bg-success-light">{{ formatQty(item.available_quantity) }}</td>
              <td class="text-end bg-success-light">{{ formatAmount(item.available_price) }}</td>
              <td class="text-end bg-success-light">{{ formatAmount(item.available_total) }}</td>
              <td class="text-center">{{ formatQty(item.stock_out_quantity) }}</td>
              <td class="text-end">{{ formatAmount(item.stock_out_total) }}</td>
              <td class="text-center">{{ formatQty(item.ending_quantity) }}</td>
              <td class="text-center">{{ formatQty(item.counted_quantity) }}</td>
              <td class="text-center">{{ formatQty(item.variance_quantity) }}</td>
              <td class="text-center">{{ formatQty(item.counted_quantity) }}</td>
              <td class="text-end">{{ formatAmount(item.average_price) }}</td>
              <td class="text-end">{{ formatAmount(item.ending_total) }}</td>
            </tr>

            <!-- Totals Row -->
            <tr class="table-secondary font-weight-bold">
              <td colspan="4" class="text-end">Total</td>
              <td class="text-center">{{ formatQty(total('beginning_quantity')) }}</td>
              <td class="text-end">-</td>
              <td class="text-end">{{ formatAmount(total('beginning_total')) }}</td>
              <td class="text-center">{{ formatQty(total('stock_in_quantity')) }}</td>
              <td class="text-end">{{ formatAmount(total('stock_in_total')) }}</td>
              <td class="text-center bg-success-light">{{ formatQty(total('available_quantity')) }}</td>
              <td class="text-end bg-success-light">-</td>
              <td class="text-end bg-success-light">{{ formatAmount(total('available_total')) }}</td>
              <td class="text-center">{{ formatQty(total('stock_out_quantity')) }}</td>
              <td class="text-end">{{ formatAmount(total('stock_out_total')) }}</td>
              <td class="text-center">{{ formatQty(total('ending_quantity')) }}</td>
              <td class="text-center">{{ formatQty(total('counted_quantity')) }}</td>
              <td class="text-center">{{ formatQty(total('variance_quantity')) }}</td>
              <td class="text-center">{{ formatQty(total('counted_quantity')) }}</td>
              <td class="text-end">-</td>
              <td class="text-end">{{ formatAmount(total('ending_total')) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="dropdown-divider mb-1"></div>

      <!-- Requested By & Approvals -->
      <div class="mt-5">
        <h5 class="mb-3 font-weight-bold">Report Approval</h5>

        <div class="row justify-content-center">

          <!-- Prepared By -->
          <div class="col-md-2 mb-2 p-1">
            <div class="card border shadow-sm h-100">
              <div class="card-body">
                <p class="font-weight-bold mb-1">Prepared & Counted By</p>

                <div class="mb-3">
                  <img 
                    :src="reportParams.created_by?.profile_url 
                      ? `/storage/${reportParams.created_by.profile_url}` 
                      : '/images/default-avatar.png'"
                    class="rounded-circle"
                    width="50"
                    height="50">
                </div>

                <p class="font-weight-bold mb-1">{{ reportParams.created_by?.name || 'N/A' }}</p>
                <p class="mb-1">
                  Status:
                  <span class="badge badge-success"><strong>Prepared</strong></span>
                </p>
                <p class="mb-1">Position: {{ reportParams.created_by?.position_name || 'N/A' }}</p>
                <p class="mb-0">Date: {{ formatDate(reportParams.report_date) }}</p>
              </div>
            </div>
          </div>

          <!-- Approvers -->
          <div v-for="(resp, idx) in responders" :key="idx" class="col-md-2 mb-2 p-1">
            <div class="card border shadow-sm h-100">
              <div class="card-body">
                <p class="font-weight-bold mb-1">{{ resp.request_type_label || resp.request_type }}</p>

                <div class="mb-3">
                  <img 
                    :src="resp.user_profile_url 
                      ? `/storage/${resp.user_profile_url}` 
                      : '/images/default-avatar.png'"
                    class="rounded-circle"
                    width="50"
                    height="50">
                </div>

                <p class="font-weight-bold mb-1">{{ resp.user_name }}</p>

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
                <p class="mb-0">Date: {{ resp.responded_date ? formatDate(resp.responded_date) : '-' }}</p>
                <p class="mb-0">Comment: {{ resp.comment || '-' }}</p>
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
            <i class="fal fa-check"></i> {{ reportParams.button_label || 'Approve' }}
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

    <StockReportModal ref="pdfViewer" title="Monthly Stock Report PDF" />

    <!-- Confirm Modal -->
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
            <button type="button" class="close" @click="closeReassignModal">&times;</button>
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
            <button class="btn btn-secondary" @click="closeReassignModal">Cancel</button>
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

// Props
const props = defineProps({
  monthlyStockReportId: Number,
  approvalRequestType: String
})

// Refs
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
const usersByType = ref({ check: [], verify: [], acknowledge: [] })
const currentActionRequestType = ref('')

// Helpers
const formatAmount = val => (!val || Number(val) === 0 ? '-' : Number(val).toLocaleString(undefined, { minimumFractionDigits: 4 , maximumFractionDigits: 4 }))
const formatQty = val => (!val || Number(val) === 0 ? '-' : Number(val).toLocaleString(undefined, { minimumFractionDigits: 2 , maximumFractionDigits: 2 }))
const formatDate = date => formatDateShort(date)
const total = key => stockItems.value.reduce((sum, i) => sum + (i[key] || 0), 0)
const goBack = () => window.location.href = '/approvals'

const currentActionTitle = computed(() =>
  currentAction.value === 'approve'
    ? 'Confirm Report'
    : currentAction.value === 'reject'
      ? 'Reject Report'
      : 'Return Report'
)

const currentActionClass = computed(() =>
  currentAction.value === 'approve'
    ? 'btn-success'
    : currentAction.value === 'reject'
      ? 'btn-danger'
      : 'btn-warning'
)

const currentActionButtonLabel = computed(() =>
  currentAction.value === 'approve'
    ? 'Confirm'
    : currentAction.value === 'reject'
      ? 'Reject'
      : 'Return'
)

// Fetch report
const fetchStockReport = async () => {
  try {
    const res = await axios.get(`/api/inventory/stock-reports/monthly-report/${props.monthlyStockReportId}/show`)
    Object.assign(reportParams.value, res.data)
    warehouseNames.value = res.data.warehouse_names || 'All Warehouses'
    stockItems.value = res.data.report || []
    responders.value = res.data.responders || []
    approvalButton.value = res.data.approvalButton || false
    usersList.value = res.data.usersList || []
  } catch (err) {
    showAlert('Error', err.response?.data?.message || 'Failed to load report', 'danger')
  }
}

// PDF
const printReport = () =>
  pdfViewer.value.open(`/inventory/stock-reports/monthly-report/${props.monthlyStockReportId}/showpdf`)

// Approval Modal
const openConfirmModal = (action) => {
  currentAction.value = action
  commentInput.value = ''
  $('#confirmModal').modal('show')
}

const resetConfirmModal = () => {
  commentInput.value = ''
  $('#confirmModal').modal('hide')
}

const submitApproval = async (action) => {
  loading.value = true
  try {
    const res = await axios.post(`/api/inventory/stock-reports/${props.monthlyStockReportId}/submit-approval`, {
      request_type: props.approvalRequestType,
      action: action,
      comment: commentInput.value.trim()
    })

    showAlert('success', res.data.message || 'Action successful')
    $('#confirmModal').modal('hide')
    fetchStockReport()
  } catch (err) {
    showAlert('Error', err.response?.data?.message || 'Action failed', 'danger')
  } finally {
    loading.value = false
  }
}

// Reassign logic
const openReassignModal = async () => {
  loading.value = true
  try {
    const res = await axios.get(`/api/inventory/stock-reports/get-approval-users`, {
      params: { request_type: props.approvalRequestType }
    })

    usersByType.value = res.data || { check: [], verify: [], acknowledge: [] }
    const currentGroup = usersByType.value[props.approvalRequestType] || []

    if (currentGroup.length === 0) {
      showAlert('Info', 'No users available for reassign.', 'info')
      return
    }

    currentActionRequestType.value = props.approvalRequestType

    await nextTick()

    const selectEl = document.getElementById('userSelect')
    if (!selectEl) return

    selectEl.innerHTML = '<option value="">-- Select a user --</option>' +
      currentGroup.map(u =>
        `<option value="${u.id}">${u.name} ${u.card_number ? '(' + u.card_number + ')' : ''}</option>`
      ).join('')

    if ($(selectEl).hasClass('select2-hidden-accessible')) {
      $(selectEl).select2('destroy')
    }

    initSelect2(selectEl, { width: '100%', dropdownParent: $('#reassignModal') })
    $('#reassignModal').modal('show')
  } catch (err) {
    showAlert('Error', 'Failed to load users.', 'danger')
  } finally {
    loading.value = false
  }
}

const confirmReassign = async () => {
  const newUserId = document.getElementById('userSelect')?.value
  const comment = document.getElementById('reassignComment')?.value.trim()

  if (!newUserId) {
    showAlert('Error', 'Please select a user.', 'danger')
    return
  }

  loading.value = true
  try {
    await axios.post(`/api/inventory/stock-reports/${props.monthlyStockReportId}/reassign-approval`, {
      request_type: currentActionRequestType.value,
      new_user_id: newUserId,
      comment
    })

    showAlert('success', 'Approval reassigned successfully.')
    $('#reassignModal').modal('hide')
    destroySelect2(document.getElementById('userSelect'))
    fetchStockReport()
  } catch (err) {
    showAlert('Error', err.response?.data?.message || 'Reassign failed.', 'danger')
  } finally {
    loading.value = false
  }
}

const closeReassignModal = () => {
  destroySelect2(document.getElementById('userSelect'))
  $('#reassignModal').modal('hide')
}

onMounted(fetchStockReport)
</script>

<style scoped>
.sticky-header th {
  position: sticky;
  top: 0;
  background-color: #f8f9fa;
  z-index: 10;
}
</style>