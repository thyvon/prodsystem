<template>
  <div class="card mb-0 shadow">
    <!-- Header -->
    <div class="card-header bg-light py-2 d-flex justify-between items-center">
      <button class="btn btn-sm btn-outline-success" @click="goBack">
        <i class="fal fa-backward"></i> Back
      </button>
      <button class="btn btn-sm btn-outline-secondary" @click="window.print()">
        <i class="fal fa-print"></i> Print
      </button>
    </div>

    <!-- Body -->
    <div class="card-body bg-white p-3">
      <!-- Header Info -->
      <div class="row mb-2">
        <div class="col-3">
          <p class="text-muted mb-1">
            PREPARED BY/ រៀបចំដោយ: <span class="font-weight-bold">{{ stock.prepared_by ?? 'N/A' }}</span>
          </p>
          <p class="text-muted mb-1">
            CARD ID/ អត្តលេខ: <span class="font-weight-bold">{{ stock.card_number ?? 'N/A' }}</span>
          </p>
          <p class="text-muted mb-1">
            DATE/កាលបរិច្ឆេទ: <span class="font-weight-bold">{{ formatDate(stock.transaction_date) ?? 'N/A' }}</span>
          </p>
        </div>

        <div class="col-6 text-center">
          <h4 class="font-weight-bold text-dark">របាយការណ៍រាប់ស្តុក</h4>
          <h4 class="font-weight-bold text-dark">STOCK COUNT REPORT</h4>
        </div>

        <div class="col-3">
          <p class="text-muted mb-1">
            REF./លេខយោង: <span class="font-weight-bold">{{ stock.reference_no ?? 'N/A' }}</span>
          </p>
          <p class="text-muted mb-1">
            WAREHOUSE/ឃ្លាំង: <span class="font-weight-bold">{{ stock.warehouse_name ?? 'N/A' }}</span>
          </p>
          <p class="text-muted mb-1">
            CAMPUS/ សាខា: <span class="font-weight-bold">{{ stock.warehouse_campus ?? 'N/A' }}</span>
          </p>
        </div>
      </div>

      <!-- Line Items Table -->
      <div class="table-responsive">
        <table class="table table-sm table-bordered table-striped table-hover">
          <thead class="table-secondary">
            <tr>
              <th class="text-center">#</th>
              <th>Item Code</th>
              <th>Product Description</th>
              <th>Unit</th>
              <th class="text-center">Ending Qty</th>
              <th class="text-center">Counted Qty</th>
              <th class="text-end">Variance</th>
              <th>Remarks</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, i) in stock.items || []" :key="i">
              <td class="text-center">{{ i + 1 }}</td>
              <td class="text-center">{{ item.product_code ?? 'N/A' }}</td>
              <td>{{ item.description ?? 'N/A' }}</td>
              <td>{{ item.unit_name ?? 'N/A' }}</td>
              <td class="text-center">{{ formatQty(item.ending_quantity) }}</td>
              <td class="text-center">{{ formatQty(item.counted_quantity) }}</td>
              <td class="text-center">{{ formatQty(item.ending_quantity - item.counted_quantity) }}</td>
              <td>{{ item.remarks ?? '-' }}</td>
            </tr>
            <tr class="table-secondary font-weight-bold">
              <td colspan="4" class="text-end">Total</td>
              <td class="text-center">{{ formatTotal(stock.items, 'ending_quantity') }}</td>
              <td class="text-center">{{ formatTotal(stock.items, 'counted_quantity') }}</td>
                <td class="text-center">
                  {{ formatQty(formatTotalVariance(stock.items, 'ending_quantity') - formatTotalVariance(stock.items, 'counted_quantity')) }}
                </td>
              <td></td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Requested By & Approvals -->
      <div class="mt-4">
        <div class="row justify-content-center">
          <!-- Counted By -->
          <div class="col-md-3 mb-4">
            <div class="card border shadow-sm h-100">
              <div class="card-body">
                <label class="font-weight-bold d-block w-100 mb-2 text-center">Counted By</label>
                <div class="d-flex align-items-center mb-4 justify-content-center">
                  <img :src="`/storage/${stock.creator_profile_picture}`" class="rounded-circle" width="50" height="50">
                </div>
                <div v-if="stock.creator_profile_picture" class="d-flex justify-content-center mb-2">
                  <img :src="`/storage/${stock.creator_signature}`" height="50">
                </div>
                <div class="border-bottom mb-2"></div>
                <div class="font-weight-bold">{{ stock.prepared_by ?? 'N/A' }}</div>
                <p class="mb-1 text-start">Status: <span class="badge badge-primary"><strong>Counted</strong></span></p>
                <p class="mb-1 text-start">Position: {{ stock.creator_position ?? 'N/A' }}</p>
                <p class="mb-0 text-start">Date: {{ formatDate(stock.transaction_date) || 'N/A' }}</p>
              </div>
            </div>
          </div>

          <!-- Approvals -->
          <div v-for="(approval, i) in approvals || []" :key="i" class="col-md-3 mb-4">
            <div class="card border shadow-sm h-100">
              <div class="card-body">
                <label class="font-weight-bold d-block w-100 mb-2 text-center">{{ approval.label }} By</label>

                <!-- Profile Picture -->
                <div class="d-flex align-items-center justify-content-center mb-4">
                  <img :src="`/storage/${approval.profile_picture}`" class="rounded-circle" width="50" height="50">
                </div>

                <!-- Signature -->
                <div v-if="approval.approval_status === 'Approved' && approval.signature" class="d-flex justify-content-center mb-2">
                  <img :src="`/storage/${approval.signature}`" height="50">
                </div>
                <div class="border-bottom mb-2"></div>

                <!-- Responder Name -->
                <div class="font-weight-bold mb-2">{{ approval.responder_name ?? 'N/A' }}</div>

                <!-- Status Badge -->
                <p class="mb-1">
                  Status:
                  <span class="badge"
                        :class="{
                          'badge-success': approval.approval_status === 'Approved',
                          'badge-danger': approval.approval_status === 'Rejected',
                          'badge-warning': approval.approval_status === 'Pending',
                          'badge-info': approval.approval_status === 'Returned'
                        }">
                    <strong>{{ approval.approval_status === 'Approved' ? 'Signed' : approval.approval_status }}</strong>
                  </span>
                </p>

                <!-- Position -->
                <p class="mb-1">Position: {{ approval.position_name ?? 'N/A' }}</p>

                <!-- Date -->
                <p class="mb-0">Date: {{ formatDateTime(approval.responded_date) || 'N/A' }}</p>

                <!-- Comment -->
                <p class="mb-0">Comment: {{ approval.comment ?? '-' }}</p>
              </div>
            </div>
          </div>

          <div v-if="!approvals?.length" class="col-12 text-center">
            No approvals available.
          </div>
        </div>
      </div>

      <!-- Approval Action -->
      <div class="card-footer mt-4">
        <h5 class="font-weight-bold text-dark mb-3">Approval Action</h5>
        <div v-if="stock.approval_buttons?.showButton">
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <button @click="openConfirmModal('approve')" class="btn btn-sm btn-success">
              <i class="fal fa-check"></i> {{ currentActionDisplay }}
            </button>
            <button @click="openConfirmModal('reject')" class="btn btn-sm btn-danger">
              <i class="fal fa-times"></i> Reject
            </button>
            <button @click="openConfirmModal('return')" class="btn btn-sm btn-warning">
              <i class="fal fa-undo"></i> Return
            </button>
            <button @click="openReassignModal" class="btn btn-sm btn-primary">
              <i class="fal fa-exchange"></i> Reassign
            </button>
          </div>
        </div>
        <div v-else>
          <p class="text-muted">No approval action available at this time.</p>
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
              <button class="btn" :class="currentActionBtnClass" @click="submitApproval(currentAction)">
                {{ currentActionDisplay }}
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
              <h5 class="modal-title">Reassign Approval</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close" @click="cleanupReassignModal">
                <span aria-hidden="true">&times;</span>
              </button>
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
              <button type="button" class="btn btn-secondary" data-dismiss="modal" @click="cleanupReassignModal">Cancel</button>
              <button class="btn btn-primary" @click="confirmReassign">Reassign</button>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, nextTick } from 'vue'
import axios from 'axios'
import { formatDateWithTime, formatDateShort } from '@/Utils/dateFormat'
import { showAlert } from '@/Utils/bootbox'
import { initSelect2, destroySelect2 } from '@/Utils/select2'

const props = defineProps({ initialData: Object }) // Use initialData from backend

// Reactive refs
const stock = ref(props.initialData || {})
const approvals = ref(stock.value.approvals || [])
const usersList = ref([])
const currentAction = ref('approve')
const commentInput = ref('')
const reassignComment = ref('')

// Helpers
const formatQty = val => Number(val || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })
const capitalize = s => (s && typeof s === 'string') ? s.charAt(0).toUpperCase() + s.slice(1) : ''
const formatDateTime = date => formatDateWithTime(date)
const formatDate = date => formatDateShort(date)
const goBack = () => window.history.back()
const formatTotal = (items, field) => formatQty((items || []).reduce((sum, i) => sum + (i[field] || 0), 0))
// Change formatTotal to return number
const formatTotalVariance = (items, field) =>
  (items || []).reduce((sum, i) => sum + (Number(i[field]) || 0), 0)


// Computed
const currentActionBtnClass = computed(() =>
  currentAction.value === 'approve'
    ? 'btn-success'
    : currentAction.value === 'reject'
      ? 'btn-danger'
      : 'btn-warning'
)
const currentActionDisplay = computed(() => {
  if (currentAction.value === 'approve') return capitalize(stock.value.approval_buttons?.requestType) || 'Approve'
  if (currentAction.value === 'reject') return 'Reject'
  return 'Return'
})

// Approval actions
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
  if (!stock.value.approval_buttons?.requestType) {
    showAlert('Error', 'Request type not found.', 'danger')
    return
  }

  try {
    const res = await axios.post(
      `/api/inventory/stock-counts/${stock.value.id}/submit-approval`,
      { 
        request_type: stock.value.approval_buttons.requestType, 
        action, 
        comment: commentInput.value?.trim() || '' 
      }
    )

    showAlert('success', res.data.message || 'Action submitted successfully.')
    $('#confirmModal').modal('hide')

    setTimeout(() => {
      window.location.href = res.data.redirect_url || window.location.href
    }, 1500)

  } catch (err) {
    showAlert('Error', err.response?.data?.message || 'Action failed.', 'danger')
  }
}

// Reassign
const openReassignModal = async () => {
  try {
    const res = await axios.get(`/api/inventory/stock-counts/get-approval-users`)
    const action = currentAction.value || 'approve'
    usersList.value = Array.isArray(res.data?.[action]) ? res.data[action] : []

    await nextTick()
    initSelect2(document.getElementById('userSelect'), { width: '100%', dropdownParent: $('#reassignModal') })
    $('#reassignModal').modal('show')
  } catch (err) {
    showAlert('Error', 'Failed to load users.', 'danger')
  }
}

const confirmReassign = async () => {
  const userSelectEl = document.getElementById('userSelect')
  const commentEl = document.getElementById('reassignComment')

  const newUserId = userSelectEl?.value
  const comment = commentEl?.value?.trim() || ''

  if (!newUserId) {
    showAlert('Error', 'Please select a user.', 'danger')
    return
  }

  if (!stock.value.approval_buttons?.requestType) {
    showAlert('Error', 'Request type not found.', 'danger')
    return
  }

  try {
    await axios.post(
      `/api/inventory/stock-counts/${stock.value.id}/reassign-approval`,
      { 
        request_type: stock.value.approval_buttons.requestType, 
        new_user_id: newUserId, 
        comment 
      }
    )

    showAlert('success', 'Responder reassigned successfully.')
    $('#reassignModal').modal('hide')
    destroySelect2(userSelectEl)

    setTimeout(() => window.location.reload(), 1500)

  } catch (err) {
    showAlert('Error', err.response?.data?.message || 'Reassignment failed.', 'danger')
  }
}

const cleanupReassignModal = () => {
  const el = document.getElementById('userSelect')
  if (el) destroySelect2(el)
}

// No API fetch needed since we use initialData
</script>


<style scoped>
.table-secondary { background-color: #f7f7f7 !important; }
.modal { overflow: visible !important; }
.select2-container--default .select2-dropdown { z-index: 1060 !important; }
</style>
