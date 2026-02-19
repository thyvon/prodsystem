<template>
  <div class="card mb-0 shadow" style="overflow-x:auto;">

    <!-- Header -->
    <PurchaseRequestHeader
    @goBack="goBack"
    @printPdf="() => openPdfViewer(purchaseRequest.id)"
    @assignPurchaser="openAssignPurchaserModal"
    @editPurchaseRequest="editPurchaseRequest(purchaseRequest.id)"
    :show-assign-purchaser-button="showAssignPurchaserButton"
    :show-edit-purchase-request-button="showEditPurchaseRequestButton"
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
    <div class="row mt-2">

    <!-- Approval Actions -->
    <div class="col-md-6 mb-3">
        <div class="card h-100 shadow-sm ml-3">
        <div class="card-header bg-light">
            <h5 class="mb-0 font-weight-bold text-dark">Approval Actions</h5>
        </div>
        <div class="card-body">
            <div v-if="showApprovalButton" class="d-flex flex-wrap gap-2">
            <button @click="openConfirmModal('approve')" class="btn btn-sm btn-success" :disabled="loading">
                <i class="fal fa-check"></i> {{ capitalize(approvalRequestType) }}
            </button>
            <button @click="openConfirmModal('reject')" class="btn btn-sm btn-danger" :disabled="loading">
                <i class="fal fa-times"></i> Reject
            </button>
            <button @click="openConfirmModal('return')" class="btn btn-sm btn-warning" :disabled="loading">
                <i class="fal fa-undo"></i> Return
            </button>
            <button @click="openReassignModal(purchaseRequest)" class="btn btn-sm btn-primary" :disabled="loading">
                <i class="fal fa-exchange"></i> Reassign
            </button>
            </div>
            <div v-else>
            <p class="text-muted mb-0">No approval action available at this time.</p>
            </div>
        </div>
        </div>
    </div>

    <!-- Procurement Actions -->
    <div class="col-md-6 mb-3" v-if="showProcurementActions">
        <div class="card h-100 shadow-sm mr-3">
        <div class="card-header bg-light">
            <h5 class="mb-0 font-weight-bold text-dark text-center">Procurement Actions</h5>
        </div>
        <div class="card-body d-flex flex-wrap justify-content-center gap-2">
            <button v-if="showAssignPurchaserButton" class="btn btn-sm btn-primary" @click="openAssignPurchaserModal">
            <i class="fal fa-user-plus"></i> Assign Purchaser
            </button>
            <button v-if="showProcurementReceiveButton" @click="openProdModal('receive')" class="btn btn-sm btn-success" :disabled="loading">
            <i class="fal fa-check"></i> Receive
            </button>
            <button v-if="showProcurementReceiveButton" @click="openProdModal('return')" class="btn btn-sm btn-warning" :disabled="loading">
            <i class="fal fa-undo"></i> Return
            </button>
            <button v-if="showProcurementVerifyButton" @click="openProdModal('verify')" class="btn btn-sm btn-success" :disabled="loading">
            <i class="fal fa-check"></i> Verify
            </button>
            <button v-if="showProcurementVerifyButton" @click="openProdModal('return')" class="btn btn-sm btn-warning" :disabled="loading">
            <i class="fal fa-undo"></i> Return
            </button>
            <button v-if="showProcurementVerifyButton" @click="openProdModal('reject')" class="btn btn-sm btn-danger" :disabled="loading">
            <i class="fal fa-times"></i> Reject
            </button>
        </div>
        </div>
    </div>

    </div>
    <!-- Procurement Modal -->
    <div class="modal fade" id="prodModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Action Confirmation</h5>
            <button type="button" class="close" @click="resetProdModal">&times;</button>
        </div>

        <div class="modal-body">
            <!-- Return user select (only for return action) -->
            <textarea
            v-model="commentInput"
            class="form-control mb-3"
            rows="4"
            placeholder="Enter your comment (optional)"
            :disabled="loading"
            ></textarea>

            <div v-if="currentAction === 'return'">
            <label class="form-label">Return to user (Optional)</label>
            <select id="prodReturnUserSelect" class="form-control" :disabled="loading">
                <option value="">-- Select user --</option>
                <option v-for="approval in usersList" :key="approval.id" :value="approval.id">
                    {{ approval.name }} - ({{ approval.request_type }})
                </option>
            </select>
            </div>
        </div>

        <div class="modal-footer">
            <button class="btn btn-secondary" @click="resetProdModal" :disabled="loading">Cancel</button>
            <button
            class="btn"
            :class="actionButtonClass"
            @click="submitProdAction(currentAction)"
            :disabled="loading"
            >
            {{ actionButtonLabel }}
            </button>
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
                      <option v-for="user in purchaserList" :key="user.id" :value="user.id">{{ user.name }} - {{ user.card_number }}</option>
                    </select>
                  </div>
                  <div class="col-md-3 mt-3 mt-md-0">
                    <button class="btn btn-success w-100" @click="applyBulkPurchaser">
                      <i class="fal fa-check-circle me-1"></i> Apply to All Items
                    </button>
                  </div>
                  <div class="col-md-3 mt-3 mt-md-0 text-muted small">This will assign the selected purchaser to all items.</div>
                </div>
              </div>
            </div>

            <!-- Items Table -->
            <div class="table-responsive border rounded">
              <table class="table table-hover mb-0 table-sm">
                <thead class="thead-light">
                  <tr class="bg-secondary text-white">
                    <th width="5%" class="text-center">#</th>
                    <th width="40%">Item Details</th>
                    <th width="15%" class="text-center">Quantity</th>
                    <th width="15%" class="text-center">Unit</th>
                    <th width="40%">Purchaser</th>
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
                      <select :id="'purchaserSelect-' + item.id" class="form-control" v-model="item.purchaser_id">
                        <option value="">-- Select Purchaser --</option>
                        <option v-for="user in purchaserList" :key="user.id" :value="user.id">{{ user.name }} - {{ user.card_number }}</option>
                      </select>
                    </td>
                  </tr>
                  <tr v-if="!assignItems.length">
                    <td colspan="5" class="text-center text-muted py-4"><i class="fal fa-box-open me-1"></i> No items available</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button class="btn btn-outline-secondary" @click="cleanupAssignPurchaserModal">Cancel</button>
            <button class="btn btn-primary px-4" :disabled="loading" @click="submitAssignPurchasers"><i class="fal fa-save me-1"></i> Assign Selected</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Confirm Modal (Approve / Reject / Return) -->
    <div class="modal fade" id="confirmModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">
            {{ currentAction === 'approve' ? capitalize(approvalRequestType)
                : currentAction === 'reject' ? 'Reject'
                : 'Return' }} Confirmation
            </h5>
            <button type="button" class="close" @click="resetConfirmModal">&times;</button>
        </div>
        <div class="modal-body">
            <textarea v-model="commentInput" class="form-control mb-3" rows="4"
            placeholder="Enter your comment (optional)" :disabled="loading"></textarea>

            <!-- Return user select -->
            <div v-if="currentAction === 'return'" class="mb-3">
            <label for="returnUserSelect" class="form-label">Select User to Return To</label>
            <select id="returnUserSelect" class="form-control" v-model="selectedUser" :disabled="loading">
                <option value="" disabled>Select a user</option>
                <option v-for="approval in usersList" :key="approval.id" :value="approval.id">
                {{ approval.name }} - ({{ approval.request_type }})
                </option>
            </select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" @click="resetConfirmModal" :disabled="loading">Cancel</button>
            <button class="btn"
                    :class="currentAction === 'approve' ? 'btn-success' : currentAction === 'reject' ? 'btn-danger' : 'btn-warning'"
                    @click="submitApproval(currentAction)"
                    :disabled="loading">
            {{ currentAction === 'approve' ? capitalize(approvalRequestType)
                : currentAction === 'reject' ? 'Reject'
                : 'Return' }}
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
            <label for="userSelect">Select New Responder</label>
            <select id="userSelect" class="form-control w-100" v-model="selectedUser" :disabled="loading">
            <option value="">-- Select a user --</option>
            <option v-for="user in usersList" :key="user.id" :value="user.id">
                {{ user.name }} - {{ user.card_number }}
            </option>
            </select>

            <label for="reassignComment" class="mt-3">Comment (optional)</label>
            <textarea id="reassignComment" class="form-control" rows="3" v-model="commentInput"></textarea>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" @click="cleanupReassignModal">Cancel</button>
            <button class="btn btn-primary" @click="confirmReassign" :disabled="!selectedUser">Reassign</button>
        </div>
        </div>
    </div>
    </div>

    <!-- File Viewer -->
    <FileViewerModal ref="fileViewerModal" />

  </div>
</template>


<script setup>
import { ref, nextTick, computed } from 'vue'
import axios from 'axios'
import { showAlert } from '@/Utils/bootbox'
import { formatDateShort } from '@/Utils/dateFormat'
import { initSelect2, destroySelect2 } from '@/Utils/select2'
import FileViewerModal from '../Reusable/FileViewerModal.vue'
import PurchaseRequestHeader from '@/components/PurchaseRequest/Partials/Show/Header.vue'
import PurchaseRequestBody from '@/components/PurchaseRequest/Partials/Show/Body.vue'

// -------------------- Props -------------------- //
const props = defineProps({
  purchaseRequestId: { type: Number, required: true },
  initialData: { type: Object }
})

// -------------------- Reactive variables -------------------- //
const purchaseRequest = ref(props.initialData)
const loading = ref(false)
const usersList = ref([])
const assignItems = ref([])
const purchaserList = ref([])

const showApprovalButton = ref(purchaseRequest.value.approval_button_data?.showButton ?? false)
const showProcurementReceiveButton = ref(purchaseRequest.value.procurement_receive_button ?? false)
const showProcurementVerifyButton = ref(purchaseRequest.value.procurement_verify_button ?? false)
const showAssignPurchaserButton = ref(purchaseRequest.value.assign_purchaser_button ?? false)
const showEditPurchaseRequestButton = ref(purchaseRequest.value.edit_purchase_request_button ?? false)

const approvalRequestType = ref(purchaseRequest.value.approval_button_data?.requestType ?? 'approve')
const currentAction = ref('approve')
const commentInput = ref('')
const selectedUser = ref('') // ✅ For Return or Reassign modals

const fileViewerModal = ref(null)

// -------------------- Helpers -------------------- //
const format = val => Number(val || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })
const capitalize = s => s ? s.charAt(0).toUpperCase() + s.slice(1) : ''
const formatDate = date => formatDateShort(date)
const goBack = () => window.history.back()

const daysBetween = (startDate, endDate) => {
  if (!startDate || !endDate) return ''
  const startOnly = new Date(new Date(startDate).setHours(0,0,0,0))
  const endOnly = new Date(new Date(endDate).setHours(0,0,0,0))
  const diffDays = Math.round((endOnly - startOnly) / (1000 * 60 * 60 * 24))
  return `${diffDays} day${diffDays !== 1 ? 's' : ''}`
}

// -------------------- Action Config -------------------- //
const actionConfig = {
  receive: { label: 'Receive', class: 'btn-success' },
  verify: { label: 'Verify', class: 'btn-success' },
  return: { label: 'Return', class: 'btn-warning' },
  reject: { label: 'Reject', class: 'btn-danger' }
}

const actionButtonLabel = computed(() => actionConfig[currentAction.value]?.label ?? 'Submit')
const actionButtonClass = computed(() => actionConfig[currentAction.value]?.class ?? 'btn-primary')
const showProcurementActions = computed(() => (
  showProcurementReceiveButton.value ||
  showProcurementVerifyButton.value
))

// -------------------- Confirm Modal --------------------
const openConfirmModal = async (action) => {
  currentAction.value = action
  commentInput.value = ''
  selectedUser.value = ''

  if (action === 'return') {
    usersList.value = (purchaseRequest.value.approvals || [])
      .filter(a => a.approval_status == 'Approved' && a.request_type !== approvalRequestType.value)
      .map(a => ({
        id: a.id,
        name: a.name ?? 'N/A',
        request_type: a.request_type_label ?? ''
      }))
  }

  $('#confirmModal').modal('show')

  $('#confirmModal')
    .off('shown.bs.modal')
    .on('shown.bs.modal', async () => {
      if (action !== 'return') return
      await nextTick()
      const el = document.getElementById('returnUserSelect')
      if (!el) return
      destroySelect2(el)
      initSelect2(el, {
        width: '100%',
        dropdownParent: $('#confirmModal'),
        placeholder: 'Select a user',
        allowClear: true
      }, value => selectedUser.value = value)
    })
}


const resetConfirmModal = () => {
  commentInput.value = ''
  selectedUser.value = ''

  const el = document.getElementById('returnUserSelect')
  if (el) {
    $(el).val(null).trigger('change')
    destroySelect2(el)
  }
  $('#confirmModal').modal('hide')
}


const submitApproval = async (action) => {
  loading.value = true
  try {
    await axios.post(`/api/purchase-requests/${props.purchaseRequestId}/submit-approval`, {
      request_type: approvalRequestType.value,
      action,
      comment: commentInput.value.trim(),
      approval_id: action === 'return' ? selectedUser.value || null : null
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
// -------------------- Prod Modal --------------------
const openProdModal = async (action) => {
  currentAction.value = action
  commentInput.value = ''
  selectedUser.value = ''

    if (action === 'return') {
    usersList.value = (purchaseRequest.value.approvals || [])
      .filter(a => a.approval_status == 'Approved')
      .map(a => ({
        id: a.id,
        name: a.name ?? 'N/A',
        request_type: a.request_type_label ?? ''
      }))
  }

  $('#prodModal').modal('show')

  $('#prodModal')
    .off('shown.bs.modal')
    .on('shown.bs.modal', async () => {
      if (action !== 'return') return
      await nextTick()

      const el = document.getElementById('prodReturnUserSelect')
      if (!el) return

      destroySelect2(el)
      initSelect2(
        el,
        {
          width: '100%',
          dropdownParent: $('#prodModal'),
          placeholder: 'Select a user',
          allowClear: true
        },
        value => (selectedUser.value = value)
      )
    })
}

const resetProdModal = () => {
  commentInput.value = ''
  selectedUser.value = ''

  const el = document.getElementById('prodReturnUserSelect')
  if (el) {
    $(el).val(null).trigger('change')
    destroySelect2(el)
  }
  $('#prodModal').modal('hide')
}


const submitProdAction = async (action) => {
  loading.value = true
  try {
    await axios.post(`/api/purchase-requests/${props.purchaseRequestId}/submit-prod-action`, {
      action,
      comment: commentInput.value.trim(),
      approval_id: action === 'return' ? selectedUser.value || null : undefined
    })
    showAlert('success', 'Action submitted successfully.')
    $('#prodModal').modal('hide')
    setTimeout(() => window.location.reload(), 1500)
  } catch (err) {
    showAlert('Error', err.response?.data?.message || 'Action failed.', 'danger')
  } finally {
    loading.value = false
  }
}

// -------------------- Assign Purchaser -------------------- //
const openAssignPurchaserModal = async () => {
  loading.value = true
  try {
    assignItems.value = purchaseRequest.value?.items ?? []
    const res = await axios.get('/api/purchase-requests/get-purchasers')
    purchaserList.value = res.data || []

    await nextTick()

    const bulkEl = document.getElementById('bulkPurchaserSelect')
    if (bulkEl) {
      destroySelect2(bulkEl)
      initSelect2(bulkEl, {
        width: '100%',
        dropdownParent: $('#assignPurchaserModal'),
        placeholder: 'Select Purchaser',
        allowClear: true
      })
    }

    assignItems.value.forEach(item => {
      const el = document.getElementById('purchaserSelect-' + item.id)
      if (el) {
        destroySelect2(el)
        initSelect2(el, {
          width: '100%',
          dropdownParent: $('#assignPurchaserModal'),
          placeholder: 'Select Purchaser',
          allowClear: true,
          value: item.purchaser_id || null
        }, value => item.purchaser_id = value)

        $(el).val(item.purchaser_id || null).trigger('change')
      }
    })

    $('#assignPurchaserModal').modal('show')
  } catch (err) {
    console.error(err)
    showAlert('Error', 'Failed to load items or purchasers.', 'danger')
  } finally { loading.value = false }
}

const applyBulkPurchaser = () => {
  const bulkId = document.getElementById('bulkPurchaserSelect')?.value
  if (!bulkId) return showAlert('Error', 'Please select a purchaser first.', 'danger')
  assignItems.value.forEach(item => {
    const el = document.getElementById('purchaserSelect-' + item.id)
    if (el) { $(el).val(bulkId).trigger('change') }
  })
}

const submitAssignPurchasers = async () => {
  const assignments = assignItems.value.map(item => {
    const selectEl = document.getElementById('purchaserSelect-' + item.id)
    const purchaserId = selectEl ? selectEl.value : null
    return purchaserId ? { item_id: item.id, purchaser_id: purchaserId } : null
  }).filter(Boolean)

  if (!assignments.length) return showAlert('Error', 'Please select at least one purchaser.', 'danger')

  loading.value = true
  try {
    await axios.post(`/api/purchase-requests/${props.purchaseRequestId}/assign-purchasers`, { assignments })
    showAlert('success', 'Purchasers assigned successfully.')
    $('#assignPurchaserModal').modal('hide')

    assignItems.value.forEach(item => destroySelect2(document.getElementById('purchaserSelect-' + item.id)))
    destroySelect2(document.getElementById('bulkPurchaserSelect'))

    setTimeout(() => window.location.reload(), 1500)
  } catch (err) {
    showAlert('Error', err.response?.data?.message || 'Assignment failed.', 'danger')
  } finally { loading.value = false }
}

const cleanupAssignPurchaserModal = () => {
  assignItems.value.forEach(item => destroySelect2(document.getElementById('purchaserSelect-' + item.id)))
  destroySelect2(document.getElementById('bulkPurchaserSelect'))
  assignItems.value = []
  $('#assignPurchaserModal').modal('hide')
}

// -------------------- File / PDF Viewer -------------------- //
const openFileViewer = (url, name) => { if (fileViewerModal.value) fileViewerModal.value.openModal(url, name) }
const openPdfViewer = (purchaseRequestId) => {
  const url = `/purchase-requests/${purchaseRequestId}/pdf?print=1`
  const iframe = document.createElement('iframe')
  iframe.style.position = 'fixed'
  iframe.style.right = '0'
  iframe.style.bottom = '0'
  iframe.style.width = '0'
  iframe.style.height = '0'
  iframe.style.border = '0'
  iframe.onload = () => setTimeout(() => { iframe.contentWindow.focus(); iframe.contentWindow.print(); document.body.removeChild(iframe) }, 300)
  iframe.src = url
  document.body.appendChild(iframe)
}

// -------------------- Reassign Modal -------------------- //
const openReassignModal = async (item) => {
  loading.value = true
  commentInput.value = ''
  selectedUser.value = item.purchaser_id || ''

  try {
    const res = await axios.get('/api/purchase-requests/get-approval-users', { params: { request_type: approvalRequestType.value } })
    usersList.value = Object.values(res.data || {})[0] || []
  } catch (err) {
    console.error(err)
    showAlert('Error', 'Failed to load users.', 'danger')
  } finally { loading.value = false }

  $('#reassignModal').modal('show')

  $('#reassignModal').off('shown.bs.modal').on('shown.bs.modal', async () => {
    await nextTick()
    const selectEl = document.getElementById('userSelect')
    if (!selectEl) return

    destroySelect2(selectEl)
    initSelect2(selectEl, {
      width: '100%',
      dropdownParent: $('#reassignModal'),
      placeholder: 'Select a user',
      allowClear: true
    }, value => selectedUser.value = value)

    $(selectEl).val(selectedUser.value || null).trigger('change')
  })
}

const confirmReassign = async () => {
  if (!selectedUser.value) return showAlert('Error', 'Please select a user.', 'danger')

  loading.value = true
  try {
    await axios.post(`/api/purchase-requests/${props.purchaseRequestId}/reassign-approval`, {
      request_type: approvalRequestType.value,
      new_user_id: selectedUser.value,
      comment: commentInput.value.trim()
    })
    showAlert('success', 'Responder reassigned successfully.')
    $('#reassignModal').modal('hide')
    destroySelect2(document.getElementById('userSelect'))
    setTimeout(() => window.location.reload(), 1500)
  } catch (err) {
    showAlert('Error', err.response?.data?.message || 'Reassignment failed.', 'danger')
  } finally { loading.value = false }
}

const cleanupReassignModal = () => {
  destroySelect2(document.getElementById('userSelect'))
  selectedUser.value = ''
  $('#reassignModal').modal('hide')
}

const editPurchaseRequest = () => {
  window.location.href = `/purchase-requests/${props.purchaseRequestId}/edit`
}
</script>
