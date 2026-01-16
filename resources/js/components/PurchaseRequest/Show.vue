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


    <div class="row">
        <!-- Approval Action (Left – Centered) -->
        <div class="col-md-6">
            <div class="card-footer w-100">
            <h5 class="font-weight-bold text-dark mb-3">Approval Action</h5>

            <div v-if="showApprovalButton">
                <div class="d-flex gap-2 flex-wrap">
                <button @click="openConfirmModal('approve')" class="btn btn-sm btn-success" :disabled="loading">
                    <i class="fal fa-check"></i> {{ capitalize(approvalRequestType) }}
                </button>
                <button @click="openConfirmModal('reject')" class="btn btn-sm btn-danger" :disabled="loading">
                    <i class="fal fa-times"></i> Reject
                </button>
                <button @click="openConfirmModal('return')" class="btn btn-sm btn-warning" :disabled="loading">
                    <i class="fal fa-undo"></i> Return
                </button>
                <button @click="openReassignModal" class="btn btn-sm btn-primary" :disabled="loading">
                    <i class="fal fa-exchange"></i> Reassign
                </button>
                </div>
            </div>

            <div v-else>
                <p class="text-muted">No approval action available at this time.</p>
            </div>
            </div>
        </div>


        <!-- -------------------------------
            1️⃣ Procurement Action Buttons
        ------------------------------- -->
        <div class="col-md-6 d-flex justify-content-center" v-if="showProcurementActions">
        <div class="card-footer text-center w-100">
            <h5 class="font-weight-bold text-dark mb-3">Procurement Action</h5>

            <div class="d-flex justify-content-center gap-2 flex-wrap">
            <!-- Receive & Return buttons -->
            <button
                v-if="showProcurementReceiveButton"
                @click="openProdModal('receive')"
                class="btn btn-sm btn-success"
                :disabled="loading"
            >
                <i class="fal fa-check"></i> Receive
            </button>

            <button
                v-if="showProcurementReceiveButton"
                @click="openProdModal('return')"
                class="btn btn-sm btn-warning"
                :disabled="loading"
            >
                <i class="fal fa-undo"></i> Return
            </button>

            <button
            class="btn btn-sm btn-primary"
            @click="openAssignPurchaserModal"
            >
            <i class="fal fa-user-plus"></i> Assign Purchaser
            </button>

            <!-- Verify, Return & Reject buttons -->
            <button
                v-if="showProcurementVerifyButton"
                @click="openProdModal('prod-verify')"
                class="btn btn-sm btn-success"
                :disabled="loading"
            >
                <i class="fal fa-check"></i> Verify
            </button>

            <button
                v-if="showProcurementVerifyButton"
                @click="openProdModal('return')"
                class="btn btn-sm btn-warning"
                :disabled="loading"
            >
                <i class="fal fa-undo"></i> Return
            </button>

            <button
                v-if="showProcurementVerifyButton"
                @click="openProdModal('reject')"
                class="btn btn-sm btn-danger"
                :disabled="loading"
            >
                <i class="fal fa-times"></i> Reject
            </button>
            </div>
        </div>
        </div>
        <div class="col-md-6 d-flex justify-content-center" v-else>
        <div class="card-footer w-100">
            <h5 class="font-weight-bold text-dark mb-3 text-center">
            Procurement Action Note
            </h5>

            <!-- approvals row -->
            <div class="row justify-content-center">
            <div
                v-for="(approval, i) in purchaseRequest.approvals.filter(a => a.prod_action == 1)"
                :key="'prod-action-' + i"
                class="col-md-4 col-sm-6 col-12 mb-3"
            >
                <div
                class="rounded p-3 h-100 text-start shadow-lg"
                :class="{
                    'text-success': !['Returned','Rejected'].includes(approval.approval_status),
                    'text-warning': approval.approval_status === 'Returned',
                    'text-danger': approval.approval_status === 'Rejected'
                }"
                :style="{
                    border: '1px solid ' + (
                    approval.approval_status === 'Returned' ? '#ffc107' :
                    approval.approval_status === 'Rejected' ? '#dc3545' :
                    '#28a745'
                    )
                }"
                >
                <!-- status icon -->
                <i
                    v-if="approval.approval_status === 'Returned'"
                    class="fal fa-undo fa-2x mb-2"
                ></i>
                <i
                    v-else-if="approval.approval_status === 'Rejected'"
                    class="fal fa-times-circle fa-2x mb-2"
                ></i>
                <i
                    v-else
                    class="fal fa-check-circle fa-2x mb-2"
                ></i>

                <!-- type -->
                <div class="mb-1">
                    {{ approval.request_type_label || approval.request_type }}
                </div>

                <!-- approval name -->
                <div class="font-weight-bold mb-1">
                    {{ approval.name ?? 'N/A' }}
                </div>

                <!-- date -->
                <div class="text-muted small">
                    Date: {{ formatDate(approval.responded_date) ?? 'N/A' }}
                </div>

                <!-- comment -->
                <div v-if="approval.comment" class="text-muted small">
                    Comment: {{ approval.comment }}
                </div>
                </div>
            </div>

            <!-- empty state -->
            <div
                v-if="purchaseRequest.approvals.filter(a => a.prod_action == 1).length === 0"
                class="col-12 text-center text-muted"
            >
                No procurement action available at this time.
            </div>
            </div>
        </div>
        </div>
    </div>

    <!-- Confirm Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1" role="dialog" ref="confirmModal">
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
            <textarea v-model="commentInput" class="form-control" rows="4" placeholder="Enter your comment (optional)" :disabled="loading"></textarea>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="resetConfirmModal" :disabled="loading">Cancel</button>
            <button class="btn"
                    :class="currentAction === 'approve' ? 'btn-success'
                              : currentAction === 'reject' ? 'btn-danger'
                              : 'btn-warning'"
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

    <!-- Procurement Action -->
    <div class="modal fade" id="prodModal" tabindex="-1" role="dialog" ref="prodModal">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">
              Action Confirmation
            </h5>
            <button type="button" class="close" @click="resetProdModal">&times;</button>
          </div>
          <div class="modal-body">
            <textarea v-model="commentInput" class="form-control" rows="4" placeholder="Enter your comment (optional)" :disabled="loading"></textarea>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary"
                    @click="resetProdModal"
                    :disabled="loading">
            Cancel
            </button>

            <button class="btn"
                    :class="actionButtonClass"
                    @click="submitProdAction(currentAction)"
                    :disabled="loading">
            {{ actionButtonLabel }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Assign Purchaser Modal -->
    <div
    class="modal fade"
    id="assignPurchaserModal"
    tabindex="-1"
    role="dialog"
    ref="assignPurchaserModal"
    >
    <div class="modal-dialog modal-xl modal-dialog" role="document">
        <div class="modal-content shadow-lg">

        <!-- HEADER -->
        <div class="modal-header bg-primary text-white">
            <h5 class="modal-title d-flex align-items-center gap-2">
            <i class="fal fa-users"></i>
            Assign Purchasers to PR Items
            </h5>
            <button
            type="button"
            class="close text-white"
            @click="cleanupAssignPurchaserModal"
            >
            <span>&times;</span>
            </button>
        </div>

        <!-- BODY -->
        <div class="modal-body">

            <!-- 🔹 BULK ASSIGN -->
            <div class="card border-0 shadow-sm mb-4">
            <div class="card-body bg-light">
                <div class="row align-items-end">
                <div class="col-md-6">
                    <label class="fw-bold mb-1">
                    Bulk Assign Purchaser
                    </label>
                    <select
                    id="bulkPurchaserSelect"
                    class="form-control"
                    >
                    <option value="">-- Select Purchaser --</option>
                    <option
                        v-for="user in purchaserList"
                        :key="user.id"
                        :value="user.id"
                    >
                        {{ user.name }} - {{ user.card_number }}
                    </option>
                    </select>
                </div>

                <div class="col-md-3 mt-3 mt-md-0">
                    <button
                    type="button"
                    class="btn btn-success w-100"
                    @click="applyBulkPurchaser"
                    >
                    <i class="fal fa-check-circle me-1"></i>
                    Apply to All Items
                    </button>
                </div>

                <div class="col-md-3 mt-3 mt-md-0 text-muted small">
                    This will assign the selected purchaser to all items.
                </div>
                </div>
            </div>
            </div>

            <!-- 🔹 ITEMS TABLE -->
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
                <tr
                    v-for="(item, index) in assignItems"
                    :key="item.id"
                >
                    <td class="text-center">
                    {{ index + 1 }}
                    </td>

                    <td>
                    <div class="fw-semibold">
                        {{ item.product_description ?? item.name }}
                    </div>
                    <div class="small text-muted">
                        Code: {{ item.product_code ?? '-' }}
                    </div>
                    </td>

                    <td class="text-center">
                    <div class="fw-semibold">
                        {{ item.quantity }}
                    </div>
                    </td>

                    <td class="text-center">
                    <div class="fw-semibold">
                        {{ item.unit_name }}
                    </div>
                    </td>

                    <td>
                    <select
                        :id="'purchaserSelect-' + item.id"
                        class="form-control"
                        v-model="item.purchaser_id"
                    >
                        <option value="">-- Select Purchaser --</option>
                        <option
                            v-for="user in purchaserList"
                            :key="user.id"
                            :value="user.id"
                        >
                            {{ user.name }} - {{ user.card_number }}
                        </option>
                    </select>

                    </td>

                </tr>

                <tr v-if="!assignItems.length">
                    <td colspan="4" class="text-center text-muted py-4">
                    <i class="fal fa-box-open me-1"></i>
                    No items available
                    </td>
                </tr>
                </tbody>
            </table>
            </div>

        </div>

        <!-- FOOTER -->
        <div class="modal-footer bg-light">
            <button
            type="button"
            class="btn btn-outline-secondary"
            @click="cleanupAssignPurchaserModal"
            >
            Cancel
            </button>

            <button
            type="button"
            class="btn btn-primary px-4"
            :disabled="loading"
            @click="submitAssignPurchasers"
            >
            <i class="fal fa-save me-1"></i>
            Assign Selected
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
            <button type="button" class="close" data-dismiss="modal" aria-label="Close" @click="cleanupReassignModal">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label for="userSelect">Select New Responder</label>
              <select id="userSelect" class="form-control w-100">
                <option value="">-- Select a user --</option>
                <option v-for="user in usersList" :value="user.id" :key="user.id">{{ user.name }} - {{ user.card_number }}</option>
              </select>
            </div>
            <div class="form-group">
              <label for="reassignComment">Comment (optional)</label>
              <textarea id="reassignComment" class="form-control" rows="3"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal" @click="cleanupReassignModal">Cancel</button>
            <button class="btn btn-primary" @click="confirmReassign">Reassign</button>
          </div>
        </div>
      </div>
    </div>

    <!-- File Viewer Modal -->
    <FileViewerModal ref="fileViewerModal" />
  </div>
</template>

<script setup>
import { ref, nextTick, computed } from 'vue'
import { showAlert } from '@/Utils/bootbox'
import { formatDateShort } from '@/Utils/dateFormat'
import { initSelect2, destroySelect2 } from '@/Utils/select2'
import axios from 'axios'
import FileViewerModal from '../Reusable/FileViewerModal.vue'
import PurchaseRequestHeader from '@/components/PurchaseRequest/Partials/Show/Header.vue'
import PurchaseRequestBody from '@/components/PurchaseRequest/Partials/Show/Body.vue'

// Props
const props = defineProps({
  purchaseRequestId: { type: Number, required: true },
  initialData: {
    type: Object
  }
})

// Reactive refs
const purchaseRequest = ref(props.initialData)
const loading = ref(false)
const usersList = ref([])
const showApprovalButton = ref(purchaseRequest.value.approval_button_data?.showButton ?? false)
const showProcurementReceiveButton = ref(purchaseRequest.value.procurement_receive_button ?? false)
const showProcurementVerifyButton = ref(purchaseRequest.value.procurement_verify_button ?? false)
const approvalRequestType = ref(purchaseRequest.value.approval_button_data?.requestType ?? 'approve')
const currentAction = ref('approve')
const commentInput = ref('')
const fileViewerModal = ref(null)
const assignItems = ref([])
const purchaserList = ref([])

// Helpers
const format = val => Number(val || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })
const capitalize = s => s ? s.charAt(0).toUpperCase() + s.slice(1) : ''
const formatDate = date => formatDateShort(date)
const goBack = () => window.history.back()

// Approval Modals
const openConfirmModal = (action) => {
  currentAction.value = action
  commentInput.value = ''
  $('#confirmModal').modal('show')
}

const openProdModal = (action) => {
  currentAction.value = action
  commentInput.value = ''
  $('#prodModal').modal('show')
}

const openAssignPurchaserModal = async () => {
  loading.value = true
  try {
    // Load PR items safely
    assignItems.value = purchaseRequest.value?.items ?? []

    // Fetch all purchasers
    const res = await axios.get('/api/purchase-requests/get-purchasers')
    purchaserList.value = res.data || []

    await nextTick()

    // Initialize bulk select2
    const bulkEl = document.getElementById('bulkPurchaserSelect')
    if (bulkEl) {
      initSelect2(bulkEl, {
        width: '100%',
        dropdownParent: $('#assignPurchaserModal')
      })
    }

    // Initialize select2 for each item and pre-select purchaser if exists
    assignItems.value.forEach(item => {
      const el = document.getElementById('purchaserSelect-' + item.id)
      if (el) {
        initSelect2(el, {
          width: '100%',
          dropdownParent: $('#assignPurchaserModal')
        })

        // 🔹 Pre-select existing purchaser
        if (item.purchaser_id) {
          $(el).val(item.purchaser_id).trigger('change')
        }

      } else {
        console.warn('Select element not found for item:', item.id)
      }
    })

    $('#assignPurchaserModal').modal('show')
  } catch (err) {
    console.error('Error in openAssignPurchaserModal:', err)
    showAlert('Error', 'Failed to load items or purchasers.', 'danger')
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
    const el = document.getElementById('purchaserSelect-' + item.id)
    if (el) {
      el.value = bulkId
      $(el).trigger('change') // important for Select2
    }
  })
}


const submitAssignPurchasers = async () => {
  // Wrap the payload in `assignments`
  const assignments = assignItems.value
    .map(item => {
      const selectEl = document.getElementById('purchaserSelect-' + item.id)
      const purchaserId = selectEl ? selectEl.value : null
      return purchaserId ? { item_id: item.id, purchaser_id: purchaserId } : null
    })
    .filter(Boolean)

  if (!assignments.length) {
    showAlert('Error', 'Please select at least one purchaser.', 'danger')
    return
  }

  loading.value = true
  try {
    // Send wrapped array
    await axios.post(`/api/purchase-requests/${props.purchaseRequestId}/assign-purchasers`, { assignments })

    showAlert('success', 'Purchasers assigned successfully.')
    $('#assignPurchaserModal').modal('hide')

    assignItems.value.forEach(item => {
      destroySelect2(document.getElementById('purchaserSelect-' + item.id))
    })

    setTimeout(() => window.location.reload(), 1500)
  } catch (err) {
    console.error('Assign purchasers error:', err)
    showAlert('Error', err.response?.data?.message || 'Assignment failed.', 'danger')
  } finally {
    loading.value = false
  }
}




const cleanupAssignPurchaserModal = () => {
  assignItems.value.forEach(item => {
    const el = document.getElementById('purchaserSelect-' + item.id)
    if (el) destroySelect2(el)
  })

  const bulkEl = document.getElementById('bulkPurchaserSelect')
  if (bulkEl) destroySelect2(bulkEl)

  assignItems.value = []
  $('#assignPurchaserModal').modal('hide')
}



const resetConfirmModal = () => {
  commentInput.value = ''
  $('#confirmModal').modal('hide')
}

const resetProdModal = () => {
  commentInput.value = ''
  $('#prodModal').modal('hide')
}

const actionConfig = {
  receive: {
    label: 'Receive',
    class: 'btn-success',
  },
  'prod-verify': {
    label: 'Verify',
    class: 'btn-success',
  },
  return: {
    label: 'Return',
    class: 'btn-warning',
  },
  reject: {
    label: 'Reject',
    class: 'btn-danger',
  },
}

const daysBetween = (startDate, endDate) => {
  if (!startDate || !endDate) return ''
  const start = new Date(startDate)
  const end = new Date(endDate)
  const startOnly = new Date(start.getFullYear(), start.getMonth(), start.getDate())
  const endOnly = new Date(end.getFullYear(), end.getMonth(), end.getDate())
  const diffMs = endOnly - startOnly
  const diffDays = Math.round(diffMs / (1000 * 60 * 60 * 24))
  return `${diffDays} day${diffDays !== 1 ? 's' : ''}`
}


const actionButtonLabel = computed(() => {
  return actionConfig[currentAction.value]?.label ?? 'Submit'
})

const actionButtonClass = computed(() => {
  return actionConfig[currentAction.value]?.class ?? 'btn-primary'
})

// Submit approval action
const submitApproval = async (action) => {
  loading.value = true
  try {
    const res = await axios.post(`/api/purchase-requests/${props.purchaseRequestId}/submit-approval`, {
      request_type: approvalRequestType.value,
      action,
      comment: commentInput.value.trim()
    })
    showAlert('success', res.data.message || 'Action submitted successfully.')
    $('#confirmModal').modal('hide')
    setTimeout(() => window.location.reload(), 1500)
  } catch (err) {
    showAlert('Error', err.response?.data?.message || 'Action failed.', 'danger')
  } finally { loading.value = false }
}

const submitProdAction = async (action) => {
  loading.value = true
  try {
    const res = await axios.post(`/api/purchase-requests/${props.purchaseRequestId}/submit-prod-action`, {
      action,
      comment: commentInput.value.trim()
    })
    showAlert('success', res.data.message || 'Action submitted successfully.')
    $('#confirmModal').modal('hide')
    setTimeout(() => window.location.reload(), 1500)
  } catch (err) {
    showAlert('Error', err.response?.data?.message || 'Action failed.', 'danger')
  } finally {
    loading.value = false
  }
}

// Button procurement

const showProcurementActions = computed(() => {
  return (
    showProcurementReceiveButton.value ||
    showProcurementVerifyButton.value
  )
})

// File Viewer
const openFileViewer = (url, name) => {
  if (fileViewerModal.value) fileViewerModal.value.openModal(url, name)
}

// PDF Viewer
const openPdfViewer = (purchaseRequestId) => {
  const url = `/purchase-requests/${purchaseRequestId}/pdf?print=1`
  const iframe = document.createElement('iframe')
  iframe.style.position = 'fixed'
  iframe.style.right = '0'
  iframe.style.bottom = '0'
  iframe.style.width = '0'
  iframe.style.height = '0'
  iframe.style.border = '0'
  iframe.onload = () => setTimeout(() => {
    iframe.contentWindow.focus();
    iframe.contentWindow.print();
    document.body.removeChild(iframe)
  }, 300)
  iframe.src = url
  document.body.appendChild(iframe)
}

// Reassign Modal using initialData.users (if passed)
const openReassignModal = async (item) => { // pass the item to modal
  loading.value = true
  try {
    const res = await axios.get('/api/purchase-requests/get-approval-users', {
      params: { request_type: approvalRequestType.value }
    })

    const data = res.data || {}
    usersList.value = Object.values(data)[0] || []

    await nextTick()

    const selectEl = document.getElementById('userSelect')
    if (selectEl) {
      const $select = $(selectEl)
      initSelect2(selectEl, { width: '100%', dropdownParent: $('#reassignModal') })

      // 🔹 Pre-select the existing purchaser
      if (item.purchaser_id) {
        $select.val(item.purchaser_id).trigger('change')
      }

      // 🔹 Update Vue reactive variable on change
      $select.off('change').on('change', function () {
        selectedUser.value = $select.val()
      })
    }

    $('#reassignModal').modal('show')

  } catch (err) {
    console.error('Failed to load users:', err)
    showAlert('Error', 'Failed to load users.', 'danger')
  } finally {
    loading.value = false
  }
}


const confirmReassign = async () => {
  const newUserId = document.getElementById('userSelect')?.value
  const comment = document.getElementById('reassignComment')?.value.trim()
  if (!newUserId) { showAlert('Error', 'Please select a user.', 'danger'); return }

  loading.value = true
  try {
    await axios.post(`/api/purchase-requests/${props.purchaseRequestId}/reassign-approval`, {
      request_type: approvalRequestType.value,
      new_user_id: newUserId,
      comment
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
  const el = document.getElementById('userSelect')
  if (el) destroySelect2(el)
}
</script>
