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
      <div class="row mb-2">
        <div class="col-3">
          <p class="text-muted mb-1">
            REQUESTER/ អ្នកស្នើសុំ:
            <span class="font-weight-bold">{{ stock.created_by?.name ?? 'N/A' }}</span>
          </p>
          <p class="text-muted mb-1">
            CARD ID/ អត្តលេខ:
            <span class="font-weight-bold">{{ stock.created_by?.card_number ?? 'N/A' }}</span>
          </p>
          <p class="text-muted mb-1">
            CAMPUS/ សាខា:
            <span class="font-weight-bold">{{ stock.campus?.short_name ?? 'N/A' }}</span>
          </p>
        </div>

        <div class="col-6 text-center">
          <h4 class="font-weight-bold text-dark">បញ្ជាទិញក្នុងក្រុមហ៊ុន</h4>
          <h4 class="font-weight-bold text-dark">INTERNAL ORDER</h4>
        </div>

        <div class="col-3">
          <p class="text-muted mb-1">
            TYPE/ ប្រភេទសំណើ: <span class="font-weight-bold">{{ stock.type ?? 'N/A' }}</span>
          </p>
          <p class="text-muted mb-1">
            REF. /លេខយោង: <span class="font-weight-bold">{{ stock.request_number ?? 'N/A' }}</span>
          </p>
          <p class="text-muted mb-1">
            DATE/ កាលបរិច្ឆេទ:
            <span class="font-weight-bold">{{ formatDate(stock.request_date) ?? 'N/A' }}</span>
          </p>
        </div>
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
              <th>Department</th>
              <th>Campus</th>
              <th>Remarks</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, i) in stock.stock_request_items" :key="i">
              <td class="text-center">{{ i + 1 }}</td>
              <td>{{ item.product_variant?.item_code ?? 'N/A' }}</td>
              <td>{{ item.product_variant?.product?.name ?? 'N/A' }} {{ item.product_variant?.description ?? '' }}</td>
              <td>{{ item.product_variant?.product?.khmer_name ?? 'N/A' }}</td>
              <td>{{ item.product_variant?.product?.unit?.name ?? 'N/A' }}</td>
              <td class="text-end">{{ format(item.average_price) }}</td>
              <td class="text-center">{{ format(item.quantity) }}</td>
              <td class="text-end">{{ format(item.total_price) }}</td>
              <td class="text-center">{{ item.department?.short_name ?? 'N/A' }}</td>
              <td class="text-center">{{ item.campus?.short_name ?? 'N/A' }}</td>
              <td class="text-start">{{ item.remarks ?? '-' }}</td>
            </tr>
            <tr class="table-secondary">
              <td colspan="6" class="text-end font-weight-bold">Total</td>
              <td class="text-center font-weight-bold">{{ format(totalQuantity) }}</td>
              <td class="text-end font-weight-bold">{{ format(totalValue) }}</td>
              <td></td>
              <td></td>
              <td></td>
            </tr>
          </tbody>
        </table> 
      </div>

      <div class="row">
        <div class="col-12">
          <p class="mb-2">PURPOSE/គោលបំណង: <span class="font-weight-bold">{{ stock.purpose ?? 'N/A' }}</span></p>
        </div>
      </div> 

      <div class="mt-4">
        <div class="row justify-content-center">
          <!-- Requested By Card -->
          <div class="col-md-3 mb-4">
            <div class="card border shadow-sm h-100">
              <div class="card-body">
                <label class="font-weight-bold text-center d-block w-100">Requested By</label>
                <div class="d-flex align-items-center mb-2 justify-content-center">
                  <div class="mr-2 text-center">
                    <span>
                      <img :src="stock.created_by?.profile_url"
                          alt="User" 
                          class="rounded-circle" 
                          width="50" 
                          height="50">
                    </span>
                    <div class="font-weight-bold mt-1">{{ stock.created_by?.name ?? 'N/A' }}</div>
                  </div>
                </div>

                <!-- Signature -->
                <div class="d-flex align-items-center mb-2 justify-content-center">
                  <div class="mr-2">
                    <span>
                      <img :src="stock.created_by?.signature_url"
                          width="auto"
                          height="80">
                    </span>
                  </div>
                </div>

                <p class="mb-1">
                  Status:
                  <span class="badge badge-primary">
                    <strong>Requested</strong>
                  </span>
                </p>
                <p class="mb-1">Position: {{ stock.creator_position?.title ?? 'N/A' }}</p>
                <p class="mb-0">Date: {{ formatDateTime(stock.created_at) || 'N/A' }}</p>
              </div>
            </div>
          </div>

          <!-- Approval Cards -->
          <div v-for="(approval, i) in approvals" :key="i" class="col-md-3 mb-4">
            <div class="card border shadow-sm h-100">
              <div class="card-body">
                <label class="font-weight-bold text-center d-block w-100">Approved by</label>
                <div class="d-flex align-items-center mb-2 justify-content-center">
                  <div class="mr-2 text-center">
                    <span>
                      <img :src="approval.responder_profile_url"
                          alt="User"
                          class="rounded-circle"
                          width="50"
                          height="50">
                    </span>
                    <div class="font-weight-bold mt-1">{{ approval.responder_name }}</div>
                  </div>
                </div>

                <!-- Signature (optional: show only if available) -->
                <div class="d-flex align-items-center mb-2 justify-content-center" v-if="approval.approval_status === 'Approved'">
                  <div class="mr-2">
                    <span>
                      <img :src="approval.responder_signature_url"
                          width="auto"
                          height="80">
                    </span>
                  </div>
                </div>

                <p class="mb-1">
                  Status: 
                  <span class="badge"
                        :class="{
                          'badge-success': approval.approval_status === 'Approved',
                          'badge-danger': approval.approval_status === 'Rejected',
                          'badge-warning': approval.approval_status === 'Pending',
                          'badge-info': approval.approval_status === 'Returned'
                        }">
                    <strong>{{ capitalize(approval.approval_status) }}</strong>
                  </span>
                </p>
                <p class="mb-1">Position: {{ approval.position_name }}</p>
                <p class="mb-0">Date: {{ formatDateTime(approval.responded_date) || 'N/A' }}</p>
                <p class="mb-0">Comment: {{ approval.comment ?? '-' }}</p>
              </div>
            </div>
          </div>

          <!-- Empty state -->
          <div v-if="approvals.length === 0" class="col-12 text-center">
            No approvals available.
          </div>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div class="card-footer">
      <h5 class="font-weight-bold text-dark mb-3">Approval Action</h5>
      <div v-if="showApprovalButton">
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <button @click="openConfirmModal('approve')" class="btn btn-sm btn-success mr-1" :disabled="loading">
            <i class="fal fa-check"></i> {{ capitalize(approvalRequestType) }}
          </button>
          <button @click="openConfirmModal('reject')" class="btn btn-sm btn-danger mr-1" :disabled="loading">
            <i class="fal fa-times"></i> Reject
          </button>
          <button @click="openConfirmModal('return')" class="btn btn-sm btn-warning mr-1" :disabled="loading">
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

    <!-- Reassign Modal -->
    <div class="modal fade" id="reassignModal" tabindex="-1" role="dialog" aria-labelledby="reassignModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
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
            <button type="button" class="btn btn-primary" @click="confirmReassign">Reassign</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Confirm Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1" role="dialog" aria-labelledby="confirmModalLabel" aria-hidden="true" ref="confirmModal">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="confirmModalLabel">
              {{ currentAction === 'approve' ? capitalize(approvalRequestType) 
                 : currentAction === 'reject' ? 'Reject' 
                 : 'Return' }} Confirmation
            </h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close" @click="resetConfirmModal">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <p>Please enter an optional comment before you {{ currentAction }} this stock request.</p>
            <textarea
              v-model="commentInput"
              class="form-control"
              rows="4"
              placeholder="Enter your comment here (optional)"
              :disabled="loading"
            ></textarea>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal" @click="resetConfirmModal" :disabled="loading">
              Cancel
            </button>
            <button type="button" class="btn"
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

  </div>
</template>

<script setup>
import { ref, nextTick } from 'vue'
import axios from 'axios'
import { showAlert } from '@/Utils/bootbox'
import { formatDateWithTime, formatDateShort } from '@/Utils/dateFormat'
import { initSelect2, destroySelect2 } from '@/Utils/select2'

const props = defineProps({
  stock: Object,
  approvals: Array,
  showApprovalButton: Boolean,
  approvalRequestType: { type: String, default: 'approve' },
  submitUrl: String,
  totalQuantity: Number,
  totalValue: Number
})

const loading = ref(false)
const usersList = ref([])
const currentAction = ref('approve')
const commentInput = ref('')

// Helpers
const format = val => Number(val || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })
const capitalize = s => s?.charAt(0).toUpperCase() + s.slice(1)
const formatDateTime = date => formatDateWithTime(date)
const formatDate = date => formatDateShort(date)
const goBack = () => window.history.back()

// Modal open/reset
const openConfirmModal = (action) => {
  currentAction.value = action
  commentInput.value = ''
  $('#confirmModal').modal('show')
}
const resetConfirmModal = () => {
  commentInput.value = ''
  $('#confirmModal').modal('hide')
}

// Submit Approval / Reject / Return
const submitApproval = async (action) => {
  loading.value = true
  try {
    const response = await axios.post(props.submitUrl, {
      request_type: props.approvalRequestType,
      action,
      comment: commentInput.value.trim(),
    })
    showAlert('success', response.data.message || 'Action submitted successfully.')
    $('#confirmModal').modal('hide')
    setTimeout(() => {
      window.location.href = response.data.redirect_url || window.location.href
    }, 1500)
  } catch (error) {
    showAlert('Error', error.response?.data?.message || 'Action failed.','danger')
  } finally {
    loading.value = false
  }
}

// Reassign
const openReassignModal = async () => {
  loading.value = true
  try {
    const response = await axios.get('/api/inventory/stock-requests/users-for-approval', {
      params: { request_type: props.approvalRequestType ?? 'approve' },
    })
    usersList.value = response.data.data || []
    await nextTick()
    const el = document.getElementById('userSelect')
    initSelect2(el, { width: '100%', dropdownParent: $('#reassignModal') })
    $('#reassignModal').modal('show')
  } catch (err) {
    showAlert('Error', 'Failed to load users.', 'danger')
  } finally { loading.value = false }
}

const confirmReassign = async () => {
  const newUserId = document.getElementById('userSelect')?.value
  const comment = document.getElementById('reassignComment')?.value.trim()
  if (!newUserId) { showAlert('Error', 'Please select a user.', 'danger'); return }
  loading.value = true
  try {
    await axios.post(`/api/inventory/stock-requests/${props.stock.id}/reassign-approval`, {
      request_type: props.approvalRequestType,
      new_user_id: newUserId,
      comment,
    })
    showAlert('success', 'Responder reassigned successfully.')
    $('#reassignModal').modal('hide')
    destroySelect2(document.getElementById('userSelect'))
    setTimeout(() => window.location.reload(), 1500)
  } catch (error) {
    showAlert('Error', error.response?.data?.message || 'Reassignment failed.', 'danger')
  } finally { loading.value = false }
}

const cleanupReassignModal = () => {
  const el = document.getElementById('userSelect')
  if (el) destroySelect2(el)
}
</script>

<style scoped>
.modal { overflow: visible !important; }
.select2-container--default .select2-dropdown { z-index: 1060 !important; }
</style>
