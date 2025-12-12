<template>
  <div class="card mb-0 shadow">
    <!-- Header -->
    <div class="card-header bg-light py-2 d-flex justify-between items-center">
      <button class="btn btn-sm btn-outline-success" @click="goBack">
        <i class="fal fa-backward"></i> Back
      </button>
      <button class="btn btn-sm btn-outline-secondary" @click="printReport(props.warehouseProductReportId)">
        <i class="fal fa-print"></i> Print
      </button>
    </div>

    <!-- Body -->
    <div class="card-body bg-white p-3">
      <!-- Header Info -->
      <div class="row mb-2">
        <div class="col-3">
          <p class="text-muted mb-1">PREPARED BY/ រៀបចំដោយ: <span class="font-weight-bold">{{ report.prepared_by ?? 'N/A' }}</span></p>
          <p class="text-muted mb-1">CARD ID/ អត្តលេខ: <span class="font-weight-bold">{{ report.card_number ?? 'N/A' }}</span></p>
          <p class="text-muted mb-1">DATE/កាលបរិច្ឆេទ: <span class="font-weight-bold">{{ formatDate(report.report_date) ?? 'N/A' }}</span></p>
        </div>

        <div class="col-6 text-center">
          <h4 class="font-weight-bold text-dark">របាយការណ៍ស្តុក</h4>
          <h4 class="font-weight-bold text-dark">STOCK REPORT</h4>
        </div>

        <div class="col-3">
          <p class="text-muted mb-1">REF./លេខយោង: <span class="font-weight-bold">{{ report.reference_no ?? 'N/A' }}</span></p>
          <p class="text-muted mb-1">WAREHOUSE/ឃ្លាំង: <span class="font-weight-bold">{{ report.warehouse_name ?? 'N/A' }}</span></p>
          <p class="text-muted mb-1">CAMPUS/ សាខា: <span class="font-weight-bold">{{ report.warehouse_campus ?? 'N/A' }}</span></p>
        </div>
      </div>

      <!-- Stock Table -->
      <div class="table-responsive">
        <table class="table table-sm table-bordered table-hover">
          <thead class="table-secondary text-center">
            <tr>
              <th>#</th>
              <th style="min-width: 120px;">Item Code</th>
              <th style="min-width: 200px;">Description</th>
              <th style="min-width: 80px;">UoM</th>
              <th style="min-width: 100px;">Unit Price</th>
              <th style="min-width: 100px;">6-Month<br>Avg Usage</th>
              <th style="min-width: 100px;">Last Month<br>Usage</th>
              <th style="min-width: 100px;">Stock<br>Beginning</th>
              <th style="min-width: 100px;">Order<br>Plan Qty</th>
              <th style="min-width: 100px;">Demand<br>Forecast</th>
              <th style="min-width: 100px;">Stock<br>Ending</th>
              <th style="min-width: 100px;">Ending Stock<br>Cover Day</th>
              <th style="min-width: 100px;">Target Safety<br>Stock Day</th>
              <th style="min-width: 100px;">Stock<br>Value</th>
              <th style="min-width: 100px;">Inventory<br>Reorder Qty</th>
              <th style="min-width: 100px;">Reorder<br>Level Qty</th>
              <th style="min-width: 100px;">Max Inventory<br>Level Qty</th>
              <th style="min-width: 100px;">Max Inventory<br>Usage Day</th>
              <th style="min-width: 200px;">Remarks</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, i) in report.items || []" :key="i">
              <td class="text-center">{{ i + 1 }}</td>
              <td class="text-center">{{ item.product_code ?? 'N/A' }}</td>
              <td class="text-start">{{ item.product_name }} {{ item.description ?? '' }}</td>
              <td>{{ item.unit_name ?? 'N/A' }}</td>
              <td class="text-end">{{ formatAmount(item.unit_price) }}</td>
              <td class="text-end">{{ formatQty(item.avg_6_month_usage) }}</td>
              <td class="text-end">{{ formatQty(item.last_month_usage) }}</td>
              <td class="text-end">{{ formatQty(item.stock_beginning) }}</td>
              <td class="text-end">{{ formatQty(item.order_plan_qty) }}</td>
              <td class="text-end">{{ formatQty(item.demand_forecast_quantity) }}</td>
              <td class="text-end">{{ formatQty(item.stock_ending) }}</td>
              <td class="text-end">{{ formatQty(item.ending_stock_cover_day) }}</td>
              <td class="text-end">{{ formatQty(item.target_safety_stock_day) }}</td>
              <td class="text-end">{{ formatAmount(item.stock_value) }}</td>
              <td class="text-end">{{ formatQty(item.inventory_reorder_quantity) }}</td>
              <td class="text-end">{{ formatQty(item.reorder_level_day) }}</td>
              <td class="text-end">{{ formatQty(item.max_inventory_level_quantity) }}</td>
              <td class="text-end">{{ formatQty(item.max_inventory_usage_day) }}</td>
              <td class="text-start">{{ item.remarks ?? '-' }}</td>
            </tr>

            <!-- Totals Row -->
            <tr class="table-secondary font-weight-bold text-center">
              <td colspan="4" class="text-end">Total</td>
              <td>-</td>
              <td>{{ formatTotal(report.items, 'avg_6_month_usage') }}</td>
              <td>{{ formatTotal(report.items, 'last_month_usage') }}</td>
              <td>{{ formatTotal(report.items, 'stock_beginning') }}</td>
              <td>{{ formatTotal(report.items, 'order_plan_qty') }}</td>
              <td>{{ formatTotal(report.items, 'demand_forecast_quantity') }}</td>
              <td>{{ formatTotal(report.items, 'stock_ending') }}</td>
              <td>{{ formatTotal(report.items, 'ending_stock_cover_day') }}</td>
              <td>{{ formatTotal(report.items, 'target_safety_stock_day') }}</td>
              <td>{{ formatTotalAmount(report.items, 'stock_value') }}</td>
              <td>{{ formatTotal(report.items, 'inventory_reorder_quantity') }}</td>
              <td>{{ formatTotal(report.items, 'reorder_level_day') }}</td>
              <td>{{ formatTotal(report.items, 'max_inventory_level_quantity') }}</td>
              <td>{{ formatTotal(report.items, 'max_inventory_usage_day') }}</td>
              <td></td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Approvals -->
      <div class="mt-4">
        <div class="row justify-content-center">
          <div v-for="(approval, i) in approvals || []" :key="i" class="col-md-3 mb-4">
            <div class="card border shadow-sm h-100">
              <div class="card-body text-center">
                <label class="font-weight-bold d-block mb-2">{{ approval.label }} By</label>
                <img :src="`/storage/${approval.profile_picture}`" class="rounded-circle mb-2" width="50" height="50">
                <div v-if="approval.approval_status === 'Approved' && approval.signature" class="mb-2">
                  <img :src="`/storage/${approval.signature}`" height="50">
                </div>
                <div class="border-bottom mb-2"></div>
                <div class="font-weight-bold">{{ approval.responder_name ?? 'N/A' }}</div>
                <p class="mb-1">
                  Status: 
                  <span class="badge"
                        :class="{
                          'badge-success': approval.approval_status === 'Approved',
                          'badge-danger': approval.approval_status === 'Rejected',
                          'badge-warning': approval.approval_status === 'Pending',
                          'badge-info': approval.approval_status === 'Returned'
                        }">{{ approval.approval_status === 'Approved' ? 'Signed' : approval.approval_status }}</span>
                </p>
                <p class="mb-1">Position: {{ approval.position_name ?? 'N/A' }}</p>
                <p class="mb-0">Date: {{ formatDateTime(approval.responded_date) || 'N/A' }}</p>
                <p class="mb-0">Comment: {{ approval.comment ?? '-' }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Approval Actions -->
      <div class="card-footer mt-4">
        <h5 class="font-weight-bold text-dark mb-3">Approval Action</h5>
        <div v-if="report.approval_buttons?.showButton">
          <div class="d-flex gap-2 flex-wrap">
            <button @click="openConfirmModal('approve')" class="btn btn-success">{{ currentActionDisplay }}</button>
            <button @click="openConfirmModal('reject')" class="btn btn-danger">Reject</button>
            <button @click="openConfirmModal('return')" class="btn btn-warning">Return</button>
            <button @click="openReassignModal" class="btn btn-primary">Reassign</button>
          </div>
        </div>
        <div v-else>
          <p class="text-muted">No approval action available.</p>
        </div>
      </div>

      <!-- Confirm Modal -->
      <div class="modal fade" id="confirmModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Confirm {{ currentActionDisplay }}</h5>
              <button type="button" class="close" @click="resetConfirmModal">&times;</button>
            </div>
            <div class="modal-body">
              <textarea v-model="commentInput" class="form-control" rows="4" placeholder="Enter comment (optional)"></textarea>
            </div>
            <div class="modal-footer">
              <button class="btn btn-secondary" @click="resetConfirmModal">Cancel</button>
              <button class="btn" :class="currentActionBtnClass" @click="submitApproval(currentAction)">{{ currentActionDisplay }}</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Reassign Modal -->
      <div class="modal fade" id="reassignModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Reassign Approval</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close" @click="cleanupReassignModal">&times;</button>
            </div>
            <div class="modal-body">
              <div class="form-group">
                <label for="userSelect">Select New Responder</label>
                <select id="userSelect" class="form-control w-100">
                  <option value="">-- Select a user --</option>
                  <option v-for="user in usersList" :key="user.id" :value="user.id">{{ user.name }}</option>
                </select>
              </div>
              <div class="form-group">
                <label for="reassignComment">Comment (optional)</label>
                <textarea id="reassignComment" v-model="reassignComment" class="form-control" rows="3"></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" @click="cleanupReassignModal">Cancel</button>
              <button class="btn btn-primary" @click="confirmReassign">Reassign</button>
            </div>
          </div>
        </div>
      </div>

    </div>
    <FileViewerModal ref="fileModal" title="Stock Report PDF" />
  </div>
</template>

<script setup>
import { ref, onMounted, computed, nextTick } from 'vue'
import axios from 'axios'
import { formatDateWithTime, formatDateShort } from '@/Utils/dateFormat'
import { showAlert } from '@/Utils/bootbox'
import { initSelect2, destroySelect2 } from '@/Utils/select2'
import FileViewerModal from '@/components/Reusable/FileViewerModal.vue'

const props = defineProps({ warehouseProductReportId: [String, Number] })

const report = ref({})
const approvals = ref([])
const usersList = ref([])
const currentAction = ref('approve')
const commentInput = ref('')
const reassignComment = ref('')
const fileModal = ref(null) // reusable file modal

// Helpers
const formatAmount = val => Number(val || 0).toLocaleString(undefined, { minimumFractionDigits: 4 })
const formatQty = val => Number(val || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })
const capitalize = s => (s && typeof s === 'string') ? s.charAt(0).toUpperCase() + s.slice(1) : ''
const formatDateTime = date => formatDateWithTime(date)
const formatDate = date => formatDateShort(date)
const goBack = () => window.location.href = '/inventory/stock-reports/reports-list'
const formatTotal = (items, field) => formatQty((items || []).reduce((sum, i) => sum + (i[field] || 0), 0))
const formatTotalAmount = (items, field) => formatAmount((items || []).reduce((sum, i) => sum + (i[field] || 0), 0))

const printReport = async (warehouseProductReportId) => {
  try {
    const res = await axios.get(
      `/inventory/stock-reports/reports/${warehouseProductReportId}/print-report`,
      {
        responseType: 'blob',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content }
      }
    )

    const blobUrl = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
    fileModal.value.openModal(blobUrl, `Stock Report - ${report.value.reference_no}.pdf`)

  } catch (err) {
    console.error(err)
    showAlert('Error', 'Failed to generate PDF.', 'danger')
  }
}

// Computed
const currentActionBtnClass = computed(() =>
  currentAction.value === 'approve'
    ? 'btn-success'
    : currentAction.value === 'reject'
      ? 'btn-danger'
      : 'btn-warning'
)
const currentActionDisplay = computed(() => {
  if (currentAction.value === 'approve') return capitalize(report.value.approval_buttons?.requestType) || 'Approve'
  if (currentAction.value === 'reject') return 'Reject'
  return 'Return'
})

// Fetch report
const fetchReport = async () => {
  try {
    const res = await axios.get(`/api/inventory/stock-reports/${props.warehouseProductReportId}/get-show-data`)
    report.value = res.data.data
    approvals.value = res.data.data.approvals || []
    if (report.value?.approval_buttons?.requestType) {
      report.value.approval_buttons.requestType = report.value.approval_buttons.requestType.toLowerCase()
    }
  } catch (err) {
    showAlert('Error', err.response?.data?.message || 'Failed to fetch report data', 'danger')
  }
}

// Approval actions
const openConfirmModal = (action) => { currentAction.value = action; commentInput.value = ''; $('#confirmModal').modal('show') }
const resetConfirmModal = () => { commentInput.value = ''; $('#confirmModal').modal('hide') }

const submitApproval = async (action) => {
  if (!report.value.approval_buttons?.requestType) { showAlert('Error', 'Request type not found.', 'danger'); return; }
  try {
    const res = await axios.post(
      `/api/inventory/stock-counts/${props.warehouseProductReportId}/submit-approval`,
      { request_type: report.value.approval_buttons.requestType, action, comment: commentInput.value?.trim() || '' }
    );
    showAlert('success', res.data.message || 'Action submitted successfully.')
    $('#confirmModal').modal('hide')
    setTimeout(() => window.location.href = res.data.redirect_url || window.location.href, 1500)
  } catch (err) {
    showAlert('Error', err.response?.data?.message || 'Action failed.', 'danger')
  }
}

const confirmReassign = async () => {
  const userSelectEl = document.getElementById('userSelect')
  const comment = reassignComment.value?.trim() || ''
  const newUserId = userSelectEl?.value
  if (!newUserId) { showAlert('Error', 'Please select a user.', 'danger'); return; }
  if (!report.value.approval_buttons?.requestType) { showAlert('Error', 'Request type not found.', 'danger'); return; }
  try {
    await axios.post(`/api/inventory/stock-counts/${props.warehouseProductReportId}/reassign-approval`, {
      request_type: report.value.approval_buttons.requestType,
      new_user_id: newUserId,
      comment
    })
    showAlert('success', 'Responder reassigned successfully.')
    $('#reassignModal').modal('hide')
    destroySelect2(userSelectEl)
    setTimeout(() => window.location.reload(), 1500)
  } catch (err) {
    showAlert('Error', err.response?.data?.message || 'Reassignment failed.', 'danger')
  }
}

const openReassignModal = async () => {
  try {
    const res = await axios.get(`/api/inventory/stock-counts/get-approval-users`)
    const action = currentAction.value || 'approve'
    usersList.value = Array.isArray(res.data?.[action]) ? res.data[action] : []
    await nextTick()
    initSelect2(document.getElementById('userSelect'), { width: '100%', dropdownParent: $('#reassignModal') })
    $('#reassignModal').modal('show')
  } catch {
    showAlert('Error', 'Failed to load users.', 'danger')
  }
}

const cleanupReassignModal = () => {
  const el = document.getElementById('userSelect')
  if (el) destroySelect2(el)
}

// Init
onMounted(() => { if (props.warehouseProductReportId) fetchReport() })
</script>

<style scoped>
.modal { overflow: visible !important; }
.select2-container--default .select2-dropdown { z-index: 1060 !important; }
</style>
