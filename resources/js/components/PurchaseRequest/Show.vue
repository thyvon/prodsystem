<template>
  <div class="card mb-0 shadow">
    <!-- Header -->
    <div class="card-header bg-light py-2 d-flex justify-between items-center">
        <button class="btn btn-sm btn-outline-success" @click="goBack">
          <i class="fal fa-backward"></i> Back
        </button>
        <button @click="openPdfViewer(purchaseRequest.id)" class="btn btn-outline-secondary btn-sm">
          <i class="fal fa-print"></i> Print
        </button>
    </div>

    <!-- Body -->
    <div class="card-body bg-white p-3">
      <!-- General Info -->
      <div class="row mb-3">
        <div class="col-3">
        <div class="row mb-1">
            <div class="col-6 text-muted">Requester / អ្នកស្នើសុំ:</div>
            <div class="col-6 font-weight-bold">{{ purchaseRequest.creator_name ?? 'N/A' }}</div>
        </div>
        <div class="row mb-1">
            <div class="col-6 text-muted">ID Card / អត្តលេខ:</div>
            <div class="col-6 font-weight-bold">{{ purchaseRequest.creator_id_card ?? 'N/A' }}</div>
        </div>
        <div class="row mb-1">
            <div class="col-6 text-muted">Position / មុខតំណែង:</div>
            <div class="col-6 font-weight-bold">{{ purchaseRequest.creator_position ?? 'N/A' }}</div>
        </div>
        </div>

        <div class="col-6 text-center">
          <h4 class="font-weight-bold text-dark">Purchase Request</h4>
          <h4 class="font-weight-bold text-dark">សំណើទិញសម្ភារ</h4>
          <h4 v-if="purchaseRequest.is_urgent" class="font-weight-bold text-danger">Urgent</h4>
        </div>

        <div class="col-3 text-end">
          <p class="text-muted mb-1">
            REF. / លេខយោង: <span class="font-weight-bold">{{ purchaseRequest.reference_no ?? 'N/A' }}</span>
          </p>
          <p class="text-muted mb-1">
            DATE REQUESTED / កាលបរិច្ឆេទ: <span class="font-weight-bold">{{ formatDate(purchaseRequest.request_date) ?? 'N/A' }}</span>
          </p>
          <p class="text-muted mb-1">
            DEADLINE / ថ្ងៃផុតកំណត់: <span class="font-weight-bold">{{ formatDate(purchaseRequest.deadline_date) ?? 'N/A' }}</span>
          </p>
        </div>
      </div>

      <div class="row mb-3">
        <div class="col-12">
          <p class="mb-2">PURPOSE/គោលបំណង: <span class="font-weight-bold">{{ purchaseRequest.purpose ?? 'N/A' }}</span></p>
        </div>
      </div>

      <!-- Line Items -->
      <div class="table-responsive">
        <table class="table table-bordered table-sm">
          <thead class="table-secondary">
            <tr>
              <th class="text-center">#</th>
              <th>Product Code</th>
              <th>Product Description</th>
              <th>Unit</th>
              <th class="text-center">Qty</th>
              <th class="text-end">Unit Price</th>
              <th class="text-end">Total Price</th>
              <th>Division</th>
              <th>Department</th>
              <th>Campus</th>
              <th>Budget Code</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, i) in purchaseRequest.items" :key="i">
              <td class="text-center">{{ i + 1 }}</td>
              <td>{{ item.product_code ?? 'N/A' }}</td>
              <td>{{ item.product_description ?? 'N/A' }}</td>
              <td>{{ item.unit_name ?? 'N/A' }}</td>
              <td class="text-center">{{ format(item.quantity) }}</td>
              <td class="text-end">{{ format(item.unit_price) }}</td>
              <td class="text-end">{{ format(item.total_price) }} {{ item.currency }}</td>
              <td><p>{{ item.division_short_names }}</p></td>
              <td><p>{{ item.department_short_names }}</p></td>
              <td><p>{{ item.campus_short_names }}</p></td>
              <td><p>{{ item.budget_code_ref ?? 'N/A' }}</p></td>
            </tr>
            <tr class="table-secondary">
            <td colspan="6" class="text-end font-weight-bold">Total (USD)</td>
            <td class="text-end font-weight-bold">{{ format(purchaseRequest.total_value_usd) }}</td>
            <td colspan="4"></td>
            </tr>
            <tr class="table-secondary">
            <td colspan="6" class="text-end font-weight-bold">Total (KHR)</td>
            <td class="text-end font-weight-bold">{{ format(purchaseRequest.total_value_khr) }}</td>
            <td colspan="4"></td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Attachement -->
  <div class="mt-4" v-if="purchaseRequest.files && purchaseRequest.files.length > 0">
    <div class="row">
      <div class="col-12">
        <h5 class="text-primary font-weight-bold mb-3">Attachments</h5>
        <div class="card border shadow-sm">
          <div class="card-body">
            <div class="row">
              <div class="col-12">
                <button
                  v-for="attachment in purchaseRequest.files"
                  :key="attachment.id"
                  type="button"
                  class="btn btn-sm btn-outline-info m-1"
                  @click="openFileViewer(attachment.url, attachment.name)"
                >
                  📄 {{ attachment.name }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
      <!-- Requested & Approval Cards -->
      <div class="mt-4">
        <div class="row justify-content-center">
          <!-- Requested By -->
          <div class="col-md-3 mb-4">
            <div class="card border shadow-sm h-100">
              <div class="card-body">
                <label class="font-weight-bold d-block text-center">Requested By</label>
                <div class="d-flex align-items-center justify-content-center mb-2">
                  <img
                      :src="purchaseRequest.creator_profile_url ? '/storage/' + purchaseRequest.creator_profile_url : '/images/default-avatar.png'"
                      class="rounded-circle"
                      width="50"
                      height="50"
                  />
                </div>
                <div class="font-weight-bold mb-1 text-center">{{ purchaseRequest.creator_name ?? 'N/A' }}</div>
                <!-- <div v-if="purchaseRequest.creator_signature_url" class="mb-2 mt-2 text-center">
                  <img
                    :src="'/storage/' + purchaseRequest.creator_signature_url"
                    height="50"
                  />
                </div> -->
                <p class="mb-1">Status: <span class="badge badge-primary">Requested</span></p>
                <p class="mb-1">Position: {{ purchaseRequest.creator_position ?? 'N/A' }}</p>
                <p class="mb-0">Date: {{ formatDate(purchaseRequest.request_date) }}</p>
              </div>
            </div>
          </div>

          <!-- Approvals -->
          <div v-for="(approval, i) in purchaseRequest.approvals" :key="i" class="col-md-3 mb-4">
            <div class="card border shadow-sm h-100">
              <div class="card-body">
                <label class="font-weight-bold d-block text-center">{{ approval.request_type_label || approval.request_type }}</label>
                <div class="d-flex align-items-center justify-content-center mb-2">
                  <img
                    :src="approval.user_profile_url ? `/storage/${approval.user_profile_url}` : '/images/default-avatar.png'"
                    class="rounded-circle"
                    width="50"
                    height="50"
                  />
                </div>
                <div class="font-weight-bold mb-1 text-center">{{ approval.name ?? 'N/A' }}</div>
                <!-- <div v-if="approval.approval_status === 'Approved'" class="mb-2 mt-2 text-center">
                  <img :src="approval.user_signature_url ? `/storage/${approval.user_signature_url}` : ''" height="50" />
                </div> -->
                <p class="mb-1 text-start">
                  Status:
                  <span class="badge"
                        :class="{
                          'badge-success': approval.approval_status === 'Approved',
                          'badge-danger': approval.approval_status === 'Rejected',
                          'badge-warning': approval.approval_status === 'Pending',
                          'badge-info': approval.approval_status === 'Returned'
                        }">
                    <strong>{{ approval.approval_status === 'Approved' ? 'Signed' : capitalize(approval.approval_status) }}</strong>
                  </span>
                </p>
                <p class="mb-1">Position: {{ approval.position_title ?? 'N/A' }}</p>
                <p class="mb-0">Date: {{ formatDate(approval.responded_date) ?? 'N/A' }}</p>
                <p v-if="approval.comment && approval.comment.trim()" class="mb-0">
                  Comment: {{ approval.comment }}
                </p>
              </div>
            </div>
          </div>

          <div v-if="purchaseRequest.approvals.length === 0" class="col-12 text-center text-muted">
            No approvals available.
          </div>
        </div>
      </div>
    </div>

    <!-- Approval Action Footer -->
    <div class="card-footer">
      <h5 class="font-weight-bold text-dark mb-3">Approval Action</h5>
      <div v-if="showApprovalButton">
        <div class="d-flex align-items-center gap-2 flex-wrap">
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
import { ref, nextTick } from 'vue'
import { showAlert } from '@/Utils/bootbox'
import { formatDateShort } from '@/Utils/dateFormat'
import { initSelect2, destroySelect2 } from '@/Utils/select2'
import axios from 'axios'
import FileViewerModal from '../Reusable/FileViewerModal.vue'

// Props
const props = defineProps({
  purchaseRequestId: { type: Number, required: true },
  initialData: {
    type: Object,
    default: () => ({
      items: [],
      approvals: [],
      files: [],
      approval_button_data: {},
      total_value_usd: 0,
      total_value_khr: 0
    })
  }
})

// Reactive refs
const purchaseRequest = ref(props.initialData)
const loading = ref(false)
const usersList = ref([])
const showApprovalButton = ref(purchaseRequest.value.approval_button_data?.showButton ?? false)
const approvalRequestType = ref(purchaseRequest.value.approval_button_data?.requestType ?? 'approve')
const currentAction = ref('approve')
const commentInput = ref('')
const fileViewerModal = ref(null)

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

const resetConfirmModal = () => {
  commentInput.value = ''
  $('#confirmModal').modal('hide')
}

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
const openReassignModal = async () => {
  loading.value = true
  try {
    const res = await axios.get('/api/purchase-requests/get-approval-users', { params: { request_type: approvalRequestType.value } })
    const data = res.data || {}
    usersList.value = Object.values(data)[0] || []
    await nextTick()
    initSelect2(document.getElementById('userSelect'), { width: '100%', dropdownParent: $('#reassignModal') })
    $('#reassignModal').modal('show')
  } catch (err) {
    showAlert('Error', 'Failed to load users.', 'danger')
  }
  finally { loading.value = false }
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
