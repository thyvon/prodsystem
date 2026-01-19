<template>
  <div class="card mb-0 shadow">
    <!-- Header -->
    <PurchaseRequestHeader
      @goBack="goBack"
      @printPdf="() => openPdfViewer(purchaseRequest.id)"
    />

    <!-- Body -->
    <PurchaseRequestBody
      :purchase-request="purchaseRequest"
      :format="format"
      :format-date="formatDate"
      :capitalize="capitalize"
      :days-between="daysBetween"
      :open-file-viewer="openFileViewer"
    />

    <!-- Actions Row -->
    <div class="row mt-4">
      <!-- Approval Actions -->
      <div class="col-md-6">
        <div class="card-footer w-100">
          <h5 class="font-weight-bold text-dark mb-3">Approval Action</h5>
          <div v-if="showApprovalButton">
            <div class="d-flex gap-2 flex-wrap">
              <button
                v-for="btn in approvalButtons"
                :key="btn.action"
                @click="btn.action === 'reassign' ? openReassignModal(purchaseRequest) : openConfirmModal(btn.action)"
                :class="`btn btn-sm ${btn.class}`"
                :disabled="loading"
              >
                <i :class="`fal ${btn.icon}`"></i> {{ btn.label }}
              </button>
            </div>
          </div>
          <p v-else class="text-muted">No approval action available at this time.</p>
        </div>
      </div>

      <!-- Procurement Actions -->
      <div class="col-md-6 d-flex justify-content-center" v-if="showProcurementActions">
        <div class="card-footer text-center w-100">
          <h5 class="font-weight-bold text-dark mb-3">Procurement Action</h5>
          <div class="d-flex justify-content-center gap-2 flex-wrap">
            <button
              v-for="btn in procurementButtons"
              :key="btn.action"
              v-if="btn.show"
              @click="btn.handler"
              :class="`btn btn-sm ${btn.class}`"
              :disabled="loading"
            >
              <i :class="`fal ${btn.icon}`"></i> {{ btn.label }}
            </button>
          </div>
        </div>
      </div>

      <!-- Procurement Notes -->
      <div class="col-md-6 d-flex justify-content-center" v-else>
        <div class="card-footer w-100">
          <h5 class="font-weight-bold text-dark mb-3 text-center">Procurement Action Note</h5>
          <div class="row justify-content-center">
            <div
              v-for="approval in prodApprovals"
              :key="`prod-${approval.id}`"
              class="col-md-4 col-sm-6 col-12 mb-3"
            >
              <div
                class="rounded p-3 h-100 text-start shadow-lg"
                :class="getApprovalStatusClass(approval.approval_status)"
                :style="{ border: `1px solid ${getApprovalBorderColor(approval.approval_status)}` }"
              >
                <i :class="getApprovalIcon(approval.approval_status) + ' fa-2x mb-2'"></i>
                <div class="mb-1">{{ approval.request_type_label || approval.request_type }}</div>
                <div class="font-weight-bold mb-1">{{ approval.name ?? 'N/A' }}</div>
                <div class="text-muted small">Date: {{ formatDate(approval.responded_date) ?? 'N/A' }}</div>
                <div v-if="approval.comment" class="text-muted small">Comment: {{ approval.comment }}</div>
              </div>
            </div>
            <div v-if="!prodApprovals.length" class="col-12 text-center text-muted">
              No procurement action available at this time.
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Confirm Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1" role="dialog">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ getModalTitle('confirm') }} Confirmation</h5>
            <button type="button" class="close" @click="resetConfirmModal">&times;</button>
          </div>
          <div class="modal-body">
            <textarea
              v-model="commentInput"
              class="form-control mb-3"
              rows="4"
              placeholder="Enter your comment (optional)"
              :disabled="loading"
            ></textarea>
            <div v-if="currentAction === 'return'" class="mb-3">
              <label for="returnUserSelect" class="form-label">Select User to Return To</label>
              <select id="returnUserSelect" class="form-control" :disabled="loading">
                <option value="" disabled>Select a user</option>
                <option v-for="user in usersList" :key="user.id" :value="user.id">
                  {{ user.name }} - {{ user.card_number }}
                </option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="resetConfirmModal" :disabled="loading">Cancel</button>
            <button
              class="btn"
              :class="getActionButtonClass(currentAction)"
              @click="submitApproval(currentAction)"
              :disabled="loading || (currentAction === 'return' && !selectedUser)"
            >
              {{ getModalTitle('confirm') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Reassign Modal -->
    <div class="modal fade" id="reassignModal" tabindex="-1" role="dialog">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Reassign {{ capitalize(approvalRequestType) }}</h5>
            <button type="button" class="close" @click="cleanupReassignModal">&times;</button>
          </div>
          <div class="modal-body">
            <div class="form-group mb-3">
              <label for="userSelect">Select New Responder</label>
              <select id="userSelect" class="form-control w-100" :disabled="loading">
                <option value="">-- Select a user --</option>
                <option v-for="user in usersList" :key="user.id" :value="user.id">
                  {{ user.name }} - {{ user.card_number }}
                </option>
              </select>
            </div>
            <div class="form-group">
              <label for="reassignComment">Comment (optional)</label>
              <textarea
                id="reassignComment"
                class="form-control"
                rows="3"
                v-model="commentInput"
              ></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="cleanupReassignModal">Cancel</button>
            <button class="btn btn-primary" @click="confirmReassign" :disabled="!selectedUser">Reassign</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Assign Purchaser Modal -->
    <div class="modal fade" id="assignPurchaserModal" tabindex="-1" role="dialog">
      <div class="modal-dialog modal-xl">
        <div class="modal-content shadow-lg">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title"><i class="fal fa-users"></i> Assign Purchasers to PR Items</h5>
            <button type="button" class="close text-white" @click="cleanupAssignPurchaserModal">&times;</button>
          </div>
          <div class="modal-body">
            <!-- Bulk Assign -->
            <div class="card border-0 shadow-sm mb-4">
              <div class="card-body bg-light">
                <div class="row align-items-end">
                  <div class="col-md-6">
                    <label class="fw-bold mb-1">Bulk Assign Purchaser</label>
                    <select id="bulkPurchaserSelect" class="form-control">
                      <option value="">-- Select Purchaser --</option>
                      <option v-for="user in purchaserList" :key="user.id" :value="user.id">
                        {{ user.name }} - {{ user.card_number }}
                      </option>
                    </select>
                  </div>
                  <div class="col-md-3 mt-3 mt-md-0">
                    <button class="btn btn-success w-100" @click="applyBulkPurchaser">
                      <i class="fal fa-check-circle me-1"></i> Apply to All
                    </button>
                  </div>
                  <div class="col-md-3 mt-3 mt-md-0 text-muted small">This will assign to all items.</div>
                </div>
              </div>
            </div>

            <!-- Items Table -->
            <div class="table-responsive border rounded">
              <table class="table table-hover mb-0">
                <thead class="thead-light">
                  <tr class="bg-secondary text-white">
                    <th width="5%" class="text-center">#</th>
                    <th width="45%">Item Details</th>
                    <th width="15%" class="text-center">Quantity</th>
                    <th width="15%" class="text-center">Unit</th>
                    <th width="35%">Purchaser</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, index) in assignItems" :key="item.id">
                    <td class="text-center">{{ index + 1 }}</td>
                    <td>
                      <div class="fw-semibold">{{ item.product_description ?? item.name }}</div>
                      <div class="small text-muted">Code: {{ item.product_code ?? '-' }}</div>
                    </td>
                    <td class="text-center">{{ item.quantity }}</td>
                    <td class="text-center">{{ item.unit_name }}</td>
                    <td>
                      <select :id="`purchaserSelect-${item.id}`" class="form-control" v-model="item.purchaser_id">
                        <option value="">-- Select Purchaser --</option>
                        <option v-for="user in purchaserList" :key="user.id" :value="user.id">
                          {{ user.name }} - {{ user.card_number }}
                        </option>
                      </select>
                    </td>
                  </tr>
                  <tr v-if="!assignItems.length">
                    <td colspan="5" class="text-center text-muted py-4">
                      <i class="fal fa-box-open me-1"></i> No items available
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button class="btn btn-outline-secondary" @click="cleanupAssignPurchaserModal">Cancel</button>
            <button class="btn btn-primary px-4" :disabled="loading" @click="submitAssignPurchasers">
              <i class="fal fa-save me-1"></i> Assign Selected
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Production Modal -->
    <div class="modal fade" id="prodModal" tabindex="-1" role="dialog">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Action Confirmation</h5>
            <button type="button" class="close" @click="resetProdModal">&times;</button>
          </div>
          <div class="modal-body">
            <textarea
              v-model="commentInput"
              class="form-control"
              rows="4"
              placeholder="Enter your comment (optional)"
              :disabled="loading"
            ></textarea>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="resetProdModal" :disabled="loading">Cancel</button>
            <button
              class="btn"
              :class="getActionButtonClass(currentAction)"
              @click="submitProdAction"
              :disabled="loading"
            >
              {{ actionConfig[currentAction]?.label ?? 'Submit' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- File Viewer -->
    <FileViewerModal ref="fileViewerModal" />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'
import { showAlert } from '@/Utils/bootbox'
import { formatDateShort } from '@/Utils/dateFormat'
import { destroySelect2 } from '@/Utils/select2'
import FileViewerModal from '../Reusable/FileViewerModal.vue'
import PurchaseRequestHeader from '@/components/PurchaseRequest/Partials/Show/Header.vue'
import PurchaseRequestBody from '@/components/PurchaseRequest/Partials/Show/Body.vue'

// Props
const props = defineProps({
  purchaseRequestId: { type: Number, required: true },
  initialData: { type: Object }
})

// State
const purchaseRequest = ref(props.initialData)
const loading = ref(false)
const usersList = ref([])
const purchaserList = ref([])
const assignItems = ref([])
const currentAction = ref('approve')
const commentInput = ref('')
const selectedUser = ref('')
const fileViewerModal = ref(null)

// UI Flags
const showApprovalButton = ref(purchaseRequest.value.approval_button_data?.showButton ?? false)
const showProcurementReceiveButton = ref(purchaseRequest.value.procurement_receive_button ?? false)
const showProcurementVerifyButton = ref(purchaseRequest.value.procurement_verify_button ?? false)
const showAssignPurchaserButton = ref(purchaseRequest.value.assign_purchaser_button ?? false)
const approvalRequestType = ref(purchaseRequest.value.approval_button_data?.requestType ?? 'approve')

// Config & Helpers
const actionConfig = {
  approve: { label: 'Approve', class: 'btn-success' },
  reject: { label: 'Reject', class: 'btn-danger' },
  return: { label: 'Return', class: 'btn-warning' },
  receive: { label: 'Receive', class: 'btn-success' },
  'prod-verify': { label: 'Verify', class: 'btn-success' }
}

const capitalize = s => s ? s.charAt(0).toUpperCase() + s.slice(1).toLowerCase() : ''
const format = val => Number(val || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })
const formatDate = date => formatDateShort(date)
const goBack = () => window.history.back()

const daysBetween = (startDate, endDate) => {
  if (!startDate || !endDate) return ''
  const start = new Date(new Date(startDate).setHours(0,0,0,0))
  const end = new Date(new Date(endDate).setHours(0,0,0,0))
  const days = Math.round((end - start) / (1000 * 60 * 60 * 24))
  return `${days} day${days !== 1 ? 's' : ''}`
}

// Computed
const showProcurementActions = computed(() =>
  showProcurementReceiveButton.value || showProcurementVerifyButton.value || showAssignPurchaserButton.value
)

const approvalButtons = computed(() => [
  { action: 'approve', label: capitalize(approvalRequestType.value), class: 'btn-success', icon: 'fa-check' },
  { action: 'reject', label: 'Reject', class: 'btn-danger', icon: 'fa-times' },
  { action: 'return', label: 'Return', class: 'btn-warning', icon: 'fa-undo' },
  { action: 'reassign', label: 'Reassign', class: 'btn-primary', icon: 'fa-exchange' }
])

const procurementButtons = computed(() => [
  { action: 'assign', label: 'Assign Purchaser', class: 'btn-primary', icon: 'fa-user-plus', show: showAssignPurchaserButton.value, handler: openAssignPurchaserModal },
  { action: 'receive', label: 'Receive', class: 'btn-success', icon: 'fa-check', show: showProcurementReceiveButton.value, handler: () => openProdModal('receive') },
  { action: 'return-proc', label: 'Return', class: 'btn-warning', icon: 'fa-undo', show: showProcurementReceiveButton.value, handler: () => openProdModal('return') },
  { action: 'verify', label: 'Verify', class: 'btn-success', icon: 'fa-check', show: showProcurementVerifyButton.value, handler: () => openProdModal('prod-verify') },
  { action: 'return-verify', label: 'Return', class: 'btn-warning', icon: 'fa-undo', show: showProcurementVerifyButton.value, handler: () => openProdModal('return') },
  { action: 'reject-verify', label: 'Reject', class: 'btn-danger', icon: 'fa-times', show: showProcurementVerifyButton.value, handler: () => openProdModal('reject') }
])

const prodApprovals = computed(() =>
  purchaseRequest.value.approvals?.filter(a => a.prod_action === 1) ?? []
)

// Helpers
const getModalTitle = action => {
  const titles = { approve: capitalize(approvalRequestType.value), reject: 'Reject', return: 'Return' }
  return titles[action] ?? 'Action'
}

const getActionButtonClass = action => {
  const classes = { approve: 'btn-success', reject: 'btn-danger', return: 'btn-warning' }
  return classes[action] ?? 'btn-primary'
}

const getApprovalStatusClass = status => {
  const classes = { Returned: 'text-warning', Rejected: 'text-danger' }
  return classes[status] ?? 'text-success'
}

const getApprovalBorderColor = status => {
  const colors = { Returned: '#ffc107', Rejected: '#dc3545' }
  return colors[status] ?? '#28a745'
}

const getApprovalIcon = status => {
  const icons = { Returned: 'fal fa-undo', Rejected: 'fal fa-times-circle' }
  return icons[status] ?? 'fal fa-check-circle'
}

// Modal Management
const openConfirmModal = async action => {
  currentAction.value = action
  commentInput.value = ''
  selectedUser.value = ''

  if (action === 'return') {
    loading.value = true
    try {
      const res = await axios.get('/api/purchase-requests/get-approval-users', {
        params: { request_type: approvalRequestType.value }
      })
      usersList.value = Object.values(res.data || {})[0] ?? []
    } catch (err) {
      showAlert('Error', 'Failed to load users.', 'danger')
    } finally {
      loading.value = false
    }
  }

  $('#confirmModal').modal('show')
  $('#confirmModal').off('shown.bs.modal').on('shown.bs.modal', async () => {
    await setupSelect2Modal('returnUserSelect', 'confirmModal', selectedUser.value)
  })
  $('#confirmModal').off('hidden.bs.modal').on('hidden.bs.modal', () => {
    destroySelect2(document.getElementById('returnUserSelect'))
  })
}

const resetConfirmModal = () => {
  const el = document.getElementById('returnUserSelect')
  if (el) destroySelect2(el)
  commentInput.value = ''
  selectedUser.value = ''
  $('#confirmModal').modal('hide')
}

const openReassignModal = async item => {
  loading.value = true
  commentInput.value = ''
  selectedUser.value = item.purchaser_id ?? ''

  try {
    const res = await axios.get('/api/purchase-requests/get-approval-users', {
      params: { request_type: approvalRequestType.value }
    })
    usersList.value = Object.values(res.data || {})[0] ?? []
  } catch (err) {
    showAlert('Error', 'Failed to load users.', 'danger')
  } finally {
    loading.value = false
  }

  $('#reassignModal').modal('show')
  $('#reassignModal').off('shown.bs.modal').on('shown.bs.modal', async () => {
    await setupSelect2Modal('userSelect', 'reassignModal', selectedUser.value)
  })
  $('#reassignModal').off('hidden.bs.modal').on('hidden.bs.modal', () => {
    destroySelect2(document.getElementById('userSelect'))
  })
}

const cleanupReassignModal = () => {
  destroySelect2(document.getElementById('userSelect'))
  selectedUser.value = ''
  $('#reassignModal').modal('hide')
}

const openAssignPurchaserModal = async () => {
  loading.value = true
  try {
    assignItems.value = purchaseRequest.value?.items ?? []
    const res = await axios.get('/api/purchase-requests/get-purchasers')
    purchaserList.value = res.data ?? []
    $('#assignPurchaserModal').modal('show')
  } catch (err) {
    showAlert('Error', 'Failed to load items or purchasers.', 'danger')
  } finally {
    loading.value = false
  }
}

const cleanupAssignPurchaserModal = () => {
  assignItems.value.forEach(item => {
    destroySelect2(document.getElementById(`purchaserSelect-${item.id}`))
  })
  destroySelect2(document.getElementById('bulkPurchaserSelect'))
  assignItems.value = []
  $('#assignPurchaserModal').modal('hide')
}

const openProdModal = action => {
  currentAction.value = action
  commentInput.value = ''
  $('#prodModal').modal('show')
}

const resetProdModal = () => {
  commentInput.value = ''
  $('#prodModal').modal('hide')
}

// Helper for Select2 setup
const setupSelect2Modal = async (selectId, modalId, initialValue) => {
  // Placeholder for Select2 initialization if needed
  // Since we removed Select2 complex logic, this can be simplified
  const selectEl = document.getElementById(selectId)
  if (selectEl) {
    selectEl.value = initialValue || ''
    selectEl.dispatchEvent(new Event('change', { bubbles: true }))
    $(selectEl).off('change').on('change', function () {
      selectedUser.value = this.value
    })
  }
}

// Submit Actions
const submitApproval = async action => {
  if (action === 'return' && !selectedUser.value) {
    showAlert('Error', 'Please select a user to return to.', 'danger')
    return
  }

  loading.value = true
  try {
    await axios.post(`/api/purchase-requests/${props.purchaseRequestId}/submit-approval`, {
      request_type: approvalRequestType.value,
      action,
      comment: commentInput.value.trim(),
      return_user_id: action === 'return' ? selectedUser.value : null
    })
    showAlert('success', 'Action submitted successfully.')
    resetConfirmModal()
    setTimeout(() => window.location.reload(), 1500)
  } catch (err) {
    showAlert('Error', err.response?.data?.message || 'Action failed.', 'danger')
  } finally {
    loading.value = false
  }
}

const submitProdAction = async () => {
  loading.value = true
  try {
    await axios.post(`/api/purchase-requests/${props.purchaseRequestId}/submit-prod-action`, {
      action: currentAction.value,
      comment: commentInput.value.trim()
    })
    showAlert('success', 'Action submitted successfully.')
    resetProdModal()
    setTimeout(() => window.location.reload(), 1500)
  } catch (err) {
    showAlert('Error', err.response?.data?.message || 'Action failed.', 'danger')
  } finally {
    loading.value = false
  }
}

const confirmReassign = async () => {
  if (!selectedUser.value) {
    showAlert('Error', 'Please select a user.', 'danger')
    return
  }

  loading.value = true
  try {
    await axios.post(`/api/purchase-requests/${props.purchaseRequestId}/reassign-approval`, {
      request_type: approvalRequestType.value,
      new_user_id: selectedUser.value,
      comment: commentInput.value.trim()
    })
    showAlert('success', 'Responder reassigned successfully.')
    cleanupReassignModal()
    setTimeout(() => window.location.reload(), 1500)
  } catch (err) {
    showAlert('Error', err.response?.data?.message || 'Reassignment failed.', 'danger')
  } finally {
    loading.value = false
  }
}

const applyBulkPurchaser = () => {
  const bulkId = document.getElementById('bulkPurchaserSelect')?.value
  if (!bulkId) {
    showAlert('Error', 'Please select a purchaser first.', 'danger')
    return
  }
  assignItems.value.forEach(item => {
    const el = document.getElementById(`purchaserSelect-${item.id}`)
    if (el) el.value = bulkId
  })
}

const submitAssignPurchasers = async () => {
  const assignments = assignItems.value
    .map(item => {
      const el = document.getElementById(`purchaserSelect-${item.id}`)
      const purchaserId = el?.value
      return purchaserId ? { item_id: item.id, purchaser_id: purchaserId } : null
    })
    .filter(Boolean)

  if (!assignments.length) {
    showAlert('Error', 'Please select at least one purchaser.', 'danger')
    return
  }

  loading.value = true
  try {
    await axios.post(`/api/purchase-requests/${props.purchaseRequestId}/assign-purchasers`, { assignments })
    showAlert('success', 'Purchasers assigned successfully.')
    cleanupAssignPurchaserModal()
    setTimeout(() => window.location.reload(), 1500)
  } catch (err) {
    showAlert('Error', err.response?.data?.message || 'Assignment failed.', 'danger')
  } finally {
    loading.value = false
  }
}

// File Viewer
const openFileViewer = (url, name) => {
  if (fileViewerModal.value) fileViewerModal.value.openModal(url, name)
}

const openPdfViewer = purchaseRequestId => {
  const url = `/purchase-requests/${purchaseRequestId}/pdf?print=1`
  const iframe = document.createElement('iframe')
  Object.assign(iframe.style, {
    position: 'fixed', right: '0', bottom: '0', width: '0', height: '0', border: '0'
  })
  iframe.onload = () => {
    setTimeout(() => {
      iframe.contentWindow?.focus()
      iframe.contentWindow?.print()
      document.body.removeChild(iframe)
    }, 300)
  }
  iframe.src = url
  document.body.appendChild(iframe)
}
</script>
