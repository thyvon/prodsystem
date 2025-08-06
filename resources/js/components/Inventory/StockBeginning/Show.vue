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
    <div class="card-body bg-white p-3" style="font-family: 'TW Cen MT', 'Khmer OS Content';">
      <!-- Title -->
      <div class="text-center mb-4">
        <h4 class="font-weight-bold text-dark">ស្តុកដើមគ្រា</h4>
        <h5 class="font-weight-bold text-dark">CAMPUS: {{ stock.warehouse?.building?.campus?.name ?? 'N/A' }}</h5>
        <h6 class="font-weight-bold text-dark">WAREHOUSE: {{ stock.warehouse?.name ?? 'N/A' }}</h6>
      </div>

      <!-- Line Items Table -->
      <div class="table-responsive">
        <table class="table table-bordered table-sm">
          <thead class="table-secondary">
            <tr>
              <th class="text-center">#</th>
              <th>Item Code</th>
              <th>Product Description</th>
              <th>Khmer Name</th>
              <th>Unit</th>
              <th class="text-end">Unit Price</th>
              <th class="text-center">Quantity</th>
              <th class="text-end">Total Value</th>
              <th>Remarks</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, i) in stock.stock_beginnings" :key="i">
              <td class="text-center">{{ i + 1 }}</td>
              <td>{{ item.product_variant?.item_code ?? 'N/A' }}</td>
              <td>
                {{ item.product_variant?.product?.name ?? 'N/A' }}
                {{ item.product_variant?.description ?? '' }}
              </td>
              <td>{{ item.product_variant?.product?.khmer_name ?? 'N/A' }}</td>
              <td>{{ item.product_variant?.product?.unit?.name ?? 'N/A' }}</td>
              <td class="text-end">{{ format(item.unit_price) }}</td>
              <td class="text-center">{{ format(item.quantity) }}</td>
              <td class="text-end">{{ format(item.total_value) }}</td>
              <td>{{ item.remarks ?? '-' }}</td>
            </tr>
            <tr class="table-secondary">
              <td colspan="6" class="text-end font-weight-bold">Total</td>
              <td class="text-center font-weight-bold">{{ format(totalQuantity) }}</td>
              <td class="text-end font-weight-bold">{{ format(totalValue) }}</td>
              <td></td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Approval History -->
      <div class="mt-4">
        <h6 class="font-weight-bold text-dark mb-2">Approvals</h6>
        <div class="row">
          <div v-for="(approval, i) in approvals" :key="i" class="col-12 col-md-4 mb-3">
            <div class="border rounded p-3 bg-light h-100">
              <p><strong>#{{ i + 1 }} - Request Type:</strong> {{ capitalize(approval.request_type) }}</p>
              <p><strong>Status:</strong> {{ capitalize(approval.approval_status) }}</p>
              <p><strong>Responder:</strong> {{ approval.responder_name }}</p>
              <p><strong>Comment:</strong> {{ approval.comment ?? '-' }}</p>
              <p><strong>Responded Date:</strong> {{ formatDateTime(approval.responded_date) || 'N/A' }}</p>
            </div>
          </div>
          <div v-if="approvals.length === 0" class="col-12">
            <div class="border rounded p-3 bg-light text-center">
              No approvals available.
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div class="card-footer">
      <h5 class="font-weight-bold text-dark mb-3">Approval Action</h5>
      <div v-if="showApprovalButton">
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <button @click="openConfirmModal('approve')" class="btn btn-sm btn-success" :disabled="loading">
            <i class="fal fa-check"></i> {{ capitalize(approvalRequestType) }}
          </button>
          <button @click="openConfirmModal('reject')" class="btn btn-sm btn-danger" :disabled="loading">
            <i><i class="fal fa-times"></i></i> Reject
          </button>
          <button @click="openReassignModal" class="btn btn-sm btn-warning" :disabled="loading">
            <i><i class="fal fa-exchange"></i></i> Reassign
          </button>
        </div>
      </div>
      <div v-else>
        <p class="text-muted">No approval action available at this time.</p>
      </div>
    </div>

    <!-- Modal for Reassignment -->
    <div class="modal fade" id="reassignModal" tabindex="-1" role="dialog" aria-labelledby="reassignModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
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
                <option v-for="user in usersList" :value="user.id" :key="user.id">{{ user.name }}</option>
              </select>
            </div>
            <div class="form-group">
              <label for="reassignComment">Comment (optional)</label>
              <textarea id="reassignComment" class="form-control" rows="3"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal" @click="cleanupReassignModal">Cancel</button>
            <button type="button" class="btn btn-warning" @click="confirmReassign">Reassign</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal for Approval/Reject Confirmation -->
    <div class="modal fade" id="confirmModal" tabindex="-1" role="dialog" aria-labelledby="confirmModalLabel" aria-hidden="true" ref="confirmModal">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="confirmModalLabel">{{ currentAction === 'approve' ? capitalize(approvalRequestType) : 'Reject' }} Confirmation</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close" @click="resetConfirmModal">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <p>Please enter an optional comment before you {{ currentAction === 'approve' ? capitalize(approvalRequestType) : 'Reject' }} this stock beginning.</p>
            <textarea
              v-model="commentInput"
              class="form-control"
              rows="4"
              placeholder="Enter your comment here (optional)"
              :disabled="loading"
            ></textarea>
          </div>
          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-secondary"
              data-dismiss="modal"
              @click="resetConfirmModal"
              :disabled="loading"
            >
              Cancel
            </button>
            <button
              type="button"
              class="btn"
              :class="currentAction === 'approve' ? 'btn-success' : 'btn-danger'"
              @click="submitApproval(currentAction)"
              :disabled="loading"
            >
              {{currentAction === 'approve' ? capitalize(approvalRequestType) : 'Reject'}}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, nextTick } from 'vue'
import axios from 'axios'
import { showAlert} from '@/Utils/bootbox'
import { formatDateWithTime } from '@/Utils/dateFormat'
import { initSelect2, destroySelect2 } from '@/Utils/select2'

const props = defineProps({
  stock: Object,
  approvals: Array,
  showApprovalButton: Boolean,
  approvalRequestType: {
    type: String,
    default: 'approve'
  },
  submitUrl: String,
})

const loading = ref(false)
const usersList = ref([])

const currentAction = ref('approve') // 'approve' or 'reject'
const commentInput = ref('')

// Helpers
const format = val => Number(val || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })
const capitalize = s => s?.charAt(0).toUpperCase() + s.slice(1)
const formatDateTime = date => formatDateWithTime(date)
const goBack = () => window.history.back()

const totalQuantity = computed(() =>
  props.stock.stock_beginnings.reduce((sum, i) => sum + Number(i.quantity || 0), 0)
)
const totalValue = computed(() =>
  props.stock.stock_beginnings.reduce((sum, i) => sum + Number(i.total_value || 0), 0)
)

// Open confirmation modal and set current action
const openConfirmModal = (action) => {
  currentAction.value = action
  commentInput.value = ''
  $('#confirmModal').modal('show')
}

// Reset comment and hide modal
const resetConfirmModal = () => {
  commentInput.value = ''
  $('#confirmModal').modal('hide')
}

// Submit Approve or Reject with modal comment
const submitApproval = async (action) => {
  loading.value = true
  try {
    const response = await axios.post(props.submitUrl, {
      request_type: props.approvalRequestType,
      action,
      comment: commentInput.value.trim(),
    })

    showAlert('success', response.data.message || 'Approval submitted successfully.')
    $('#confirmModal').modal('hide')
    setTimeout(() => {
      window.location.href = response.data.redirect_url || window.location.href
    }, 1500)

  } catch (error) {
    showAlert('Error', error.response?.data?.message || 'Approval failed.','danger')
  } finally {
    loading.value = false
  }
}

// Open Reassign Modal and load users
const openReassignModal = async () => {
  loading.value = true
  try {
    const response = await axios.get('/api/inventory/stock-beginnings/users', {
      params: { request_type: props.approvalRequestType ?? 'approve' },
    })
    usersList.value = response.data.data || []

    await nextTick()
    const el = document.getElementById('userSelect')
    initSelect2(el, {
      width: '100%',
      dropdownParent: $('#reassignModal')  // Fix dropdown inside modal
    })

    $('#reassignModal').modal('show')
  } catch (err) {
    console.error('Error loading users:', err)
    showAlert('Error', 'Failed to load users.', 'danger')
  } finally {
    loading.value = false
  }
}

// Confirm reassignment
const confirmReassign = async () => {
  const userSelectEl = document.getElementById('userSelect')
  const commentEl = document.getElementById('reassignComment')

  const newUserId = userSelectEl?.value
  const comment = commentEl?.value.trim()

  if (!newUserId) {
    showAlert('Error', 'Please select a user.', 'danger')
    return
  }

  loading.value = true
  try {
    await axios.post(`/api/inventory/stock-beginnings/${props.stock.id}/reassign-approval`, {
      request_type: props.approvalRequestType,
      new_user_id: newUserId,
      comment,
    })

    showAlert('success', 'Responder reassigned successfully.')
    $('#reassignModal').modal('hide')
    destroySelect2(userSelectEl)
    setTimeout(() => window.location.reload(), 1500)

  } catch (error) {
    showAlert('Error', error.response?.data?.message || 'Reassignment failed.', 'danger')
  } finally {
    loading.value = false
  }
}

// Cleanup on modal close
const cleanupReassignModal = () => {
  const el = document.getElementById('userSelect')
  if (el) destroySelect2(el)
}
</script>

<style scoped>
/* Fix z-index of Select2 dropdown inside Bootstrap modal */
.modal {
  overflow: visible !important; /* Prevent dropdown from being clipped */
}
.select2-container--default .select2-dropdown {
  z-index: 1060 !important; /* Above Bootstrap modal z-index (1050) */
}
</style>
