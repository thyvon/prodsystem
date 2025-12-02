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
            STAFF/ អ្នករៀបចំ:
            <span class="font-weight-bold">{{ stock.created_by?.name ?? 'N/A' }}</span>
          </p>
          <p class="text-muted mb-1">
            CARD ID/ អត្តលេខ:
            <span class="font-weight-bold">{{ stock.created_by?.card_number ?? 'N/A' }}</span>
          </p>
          <p class="text-muted mb-1">
            CAMPUS/ សាខា:
            <span class="font-weight-bold">{{ stock.warehouse?.building?.campus?.name ?? 'N/A' }}</span>
          </p>
        </div>

        <div class="col-6 text-center">
          <h4 class="font-weight-bold text-dark">ស្តុកដើមគ្រា</h4>
          <h4 class="font-weight-bold text-dark">STOCK BEGINNING</h4>
        </div>

        <div class="col-3">
          <p class="text-muted mb-1">
            REF./លេខយោង: <span class="font-weight-bold">{{ stock.reference_no ?? 'N/A' }}</span>
          </p>
          <p class="text-muted mb-1">
            WAREHOUSE/ឃ្លាំង: <span class="font-weight-bold">{{ stock.warehouse?.name ?? 'N/A' }}</span>
          </p>
          <p class="text-muted mb-1">
            DATE/កាលបរិច្ឆេទ: <span class="font-weight-bold">{{ formatDate(stock.beginning_date) ?? 'N/A' }}</span>
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
              <th>Unit</th>
              <th class="text-end">Unit Price</th>
              <th class="text-center">Quantity</th>
              <th class="text-end">Total Value</th>
              <th>Remarks</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, i) in stock.items" :key="i">
              <td class="text-center">{{ i + 1 }}</td>
              <td>{{ item.product_variant?.item_code ?? 'N/A' }}</td>
              <td>{{ item.product_variant?.product?.name ?? 'N/A' }} {{ item.product_variant?.description ?? '' }}</td>
              <td>{{ item.product_variant?.product?.unit?.name ?? 'N/A' }}</td>
              <td class="text-end">{{ format(item.unit_price) }}</td>
              <td class="text-end">{{ formatQty(item.quantity) }}</td>
              <td class="text-end">{{ format(item.total_value) }}</td>
              <td>{{ item.remarks ?? '-' }}</td>
            </tr>
            <tr class="table-secondary">
              <td colspan="6" class="text-end font-weight-bold">Total</td>
              <td class="text-center font-weight-bold">{{ formatQty(totalQuantity) }}</td>
              <td class="text-end font-weight-bold">{{ format(totalValue) }}</td>
              <td></td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Requested By & Approval Cards -->
      <div class="mt-4">
        <div class="row justify-content-center">
          <!-- Requested By -->
          <div class="col-md-3 mb-4">
            <div class="card border shadow-sm h-100">
              <div class="card-body">
                <label class="font-weight-bold text-center d-block w-100">Requested By</label>
                <div class="d-flex align-items-center mb-2 justify-content-center">
                  <img :src="stock.created_by?.profile_url" class="rounded-circle" width="50" height="50">
                </div>
                <div class="font-weight-bold text-center mb-2">{{ stock.created_by?.name ?? 'N/A' }}</div>
                <div v-if="stock.created_by?.signature_url" class="d-flex justify-content-center mb-2">
                  <img :src="stock.created_by.signature_url" height="50">
                </div>
                <p class="mb-1">Status: <span class="badge badge-primary"><strong>Requested</strong></span></p>
                <p class="mb-1">Position: {{ stock.creator_position?.title ?? 'N/A' }}</p>
                <p class="mb-0">Date: {{ formatDateTime(stock.created_at) || 'N/A' }}</p>
              </div>
            </div>
          </div>

          <!-- Approvals -->
          <div v-for="(approval, i) in approvals" :key="i" class="col-md-3 mb-4">
            <div class="card border shadow-sm h-100">
              <div class="card-body">
                <label class="font-weight-bold text-center d-block w-100">{{ approval.request_type_label }} By</label>
                <div class="d-flex align-items-center mb-2 justify-content-center">
                  <img :src="approval.responder_profile_url" class="rounded-circle" width="50" height="50">
                </div>
                <div class="font-weight-bold text-center mb-2">{{ approval.responder_name }}</div>
                <div v-if="approval.approval_status === 'Approved'" class="d-flex justify-content-center mb-2">
                  <img :src="approval.responder_signature_url" height="50">
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
                    <strong>
                      {{
                        approval.approval_status === 'Approved' ? 'Signed' : capitalize(approval.approval_status)
                      }}
                    </strong>
                  </span>
                </p>
                <p class="mb-1">Position: {{ approval.position_name }}</p>
                <p class="mb-0">Date: {{ formatDateTime(approval.responded_date) || 'N/A' }}</p>
                <p class="mb-0">Comment: {{ approval.comment ?? '-' }}</p>
              </div>
            </div>
          </div>

          <div v-if="approvals.length === 0" class="col-12 text-center">
            No approvals available.
          </div>
        </div>
      </div>
    </div>

    <!-- Footer: Approval Action -->
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

    <!-- Modals -->
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
            <button class="btn btn-primary" @click="confirmReassign">Reassign</button>
          </div>
        </div>
      </div>
    </div>

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
            <textarea v-model="commentInput" class="form-control" rows="4" placeholder="Enter your comment here (optional)" :disabled="loading"></textarea>
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

const format = val => Number(val || 0).toLocaleString(undefined, { minimumFractionDigits: 4 })
const formatQty = val => Number(val || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })
const capitalize = s => s?.charAt(0).toUpperCase() + s.slice(1)
const formatDateTime = date => formatDateWithTime(date)
const formatDate = date => formatDateShort(date)
const goBack = () => window.history.back()

const openConfirmModal = (action) => { currentAction.value = action; commentInput.value = ''; $('#confirmModal').modal('show') }
const resetConfirmModal = () => { commentInput.value = ''; $('#confirmModal').modal('hide') }

const submitApproval = async (action) => {
  loading.value = true
  try {
    const res = await axios.post(props.submitUrl, { request_type: props.approvalRequestType, action, comment: commentInput.value.trim() })
    showAlert('success', res.data.message || 'Action submitted successfully.')
    $('#confirmModal').modal('hide')
    setTimeout(() => window.location.href = res.data.redirect_url || window.location.href, 1500)
  } catch (err) {
    showAlert('Error', err.response?.data?.message || 'Action failed.','danger')
  } finally { loading.value = false }
}

const openReassignModal = async () => {
  loading.value = true
  try {
    const res = await axios.get('/api/inventory/stock-beginnings/users', { params: { request_type: props.approvalRequestType ?? 'approve' } })
    usersList.value = res.data.data || []
    await nextTick()
    initSelect2(document.getElementById('userSelect'), { width: '100%', dropdownParent: $('#reassignModal') })
    $('#reassignModal').modal('show')
  } catch (err) { showAlert('Error', 'Failed to load users.', 'danger') }
  finally { loading.value = false }
}

const confirmReassign = async () => {
  const newUserId = document.getElementById('userSelect')?.value
  const comment = document.getElementById('reassignComment')?.value.trim()
  if (!newUserId) { showAlert('Error', 'Please select a user.', 'danger'); return }
  loading.value = true
  try {
    await axios.post(`/api/inventory/stock-beginnings/${props.stock.id}/reassign-approval`, { request_type: props.approvalRequestType, new_user_id: newUserId, comment })
    showAlert('success', 'Responder reassigned successfully.')
    $('#reassignModal').modal('hide')
    destroySelect2(document.getElementById('userSelect'))
    setTimeout(() => window.location.reload(), 1500)
  } catch (err) { showAlert('Error', err.response?.data?.message || 'Reassignment failed.', 'danger') }
  finally { loading.value = false }
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
