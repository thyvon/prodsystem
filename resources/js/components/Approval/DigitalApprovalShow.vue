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
      <!-- Document Info -->
      <div class="row mb-2">
        <div class="col-3">
          <p class="text-muted mb-1">
            DATE / កាលបរិច្ឆេទ:
            <span class="font-weight-bold">{{ formatDate(digitalDoc.created_at) ?? 'N/A' }}</span>
          </p>
        </div>

        <div class="col-6 text-center">
          <h4 class="font-weight-bold text-dark">ឯកសារ</h4>
          <h4 class="font-weight-bold text-dark">DIGITAL DOCUMENT</h4>
        </div>

        <div class="col-3 text-right">
          <p class="text-muted mb-1">
            REF / លេខយោង: <span class="font-weight-bold">{{ digitalDoc.reference_no ?? 'N/A' }}</span>
          </p>
          <p class="text-muted mb-1">
            DOCUMENT TYPE / ប្រភេទឯកសារ: <span class="font-weight-bold">{{ digitalDoc.document_type ?? 'N/A' }}</span>
          </p>
        </div>
      </div>

      <!-- File Preview -->
      <div class="mb-3">
        <p class="text-muted mb-1">DESCRIPTION / ពិពណ៌នា:</p>
        <p class="font-weight-bold">{{ digitalDoc.description ?? 'N/A' }}</p>

        <p class="text-muted mb-1">FILE / ឯកសារ:</p>
        <p>
          <a :href="digitalDoc.sharepoint_file_ui_url" target="_blank" class="btn btn-sm btn-outline-primary me-2">
            <i class="fal fa-external-link"></i> Open in SharePoint
          </a>
          <a :href="streamUrl" target="_blank" download class="btn btn-sm btn-outline-secondary">
            <i class="fal fa-download"></i> Download
          </a>
        </p>

        <!-- PDF Viewer -->
        <div v-if="isPdf" class="mt-3 border rounded" style="height:1000px;">
          <iframe
            :src="pdfViewerUrl"
            style="width:100%; height:100%; border:none;"
            allowfullscreen
          ></iframe>
        </div>

        <!-- Image Preview -->
        <div v-else-if="isImage" class="mt-3 text-center border rounded bg-light p-3">
          <img :src="streamUrl" class="img-fluid rounded" style="max-height:700px;" :alt="digitalDoc.sharepoint_file_name">
        </div>

        <!-- Fallback -->
        <div v-else class="mt-3 border rounded" style="height:1000px;">
          <iframe
            src="/pdfjs/sample.pdf"
            style="width:100%; height:100%; border:none;"
            allowfullscreen
          ></iframe>
        </div>
      </div>

      <!-- Approval Cards -->
      <div class="mt-4">
        <div class="row justify-content-center">
        <!-- Requested By / Creator Card -->
        <div class="col-md-3 mb-4">
          <div class="card border shadow-sm h-100">
            <div class="card-body">
              <label class="font-weight-bold d-block w-100 text-center">Prepared By</label>
              <div class="d-flex align-items-center mb-2 justify-content-center">
                <img :src="`/storage/${digitalDoc.creator?.profile_url}`" class="rounded-circle" width="50" height="50">
              </div>
              <div v-if="digitalDoc.creator?.signature_url" class="d-flex justify-content-center mb-2">
                <img :src="digitalDoc.creator.signature_url" height="50">
              </div>
              <div class="font-weight-bold mb-2">{{ digitalDoc.creator?.name ?? 'N/A' }}</div>
              <p class="mb-1 text-start">Status: <span class="badge badge-primary"><strong>Requested</strong></span></p>
              <p class="mb-1">Position: {{ digitalDoc.creator_position?.title ?? 'N/A' }}</p>
              <p class="mb-0">Date: {{ formatDateTime(digitalDoc.created_at) || 'N/A' }}</p>
            </div>
          </div>
        </div>
          <div v-for="(approval, i) in approvals" :key="i" class="col-md-3 mb-4">
            <div class="card border shadow-sm h-100">
              <div class="card-body">
                <label class="font-weight-bold d-block w-100 text-center">{{ approval.request_type_label }} By</label>
                <div class="d-flex align-items-center mb-2 justify-content-center">
                  <img :src="`/storage/${approval.responder?.profile_url}`" class="rounded-circle" width="50" height="50">
                </div>
                <div v-if="approval.approval_status === 'Approved'" class="d-flex justify-content-center mb-2">
                  <img :src="approval.responder?.signature_url" height="50">
                </div>
                <div class="font-weight-bold mb-2">{{ approval.position_name }}</div>
                <p class="mb-1">
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
                <p class="mb-1">Position: {{ approval.responder?.position_name ?? 'N/A' }}</p>
                <p class="mb-0">Date: {{ formatDateTime(approval.responded_date) || 'N/A' }}</p>
                <p class="mb-0">Comment: {{ approval.comment ?? '-' }}</p>
              </div>
            </div>
          </div>
          <div v-if="approvals.length === 0" class="col-12 text-center">No approvals available.</div>
        </div>
      </div>
    </div>

    <!-- Footer: Approval Action -->
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

    <!-- Reassign Modal -->
    <div class="modal fade" id="reassignModal" tabindex="-1" role="dialog" aria-hidden="true">
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
            <div class="form-group mt-2">
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
  </div>
</template>

<script setup>
import { ref, computed, nextTick } from 'vue'
import axios from 'axios'
import { showAlert } from '@/Utils/bootbox'
import { formatDateWithTime, formatDateShort } from '@/Utils/dateFormat'
import { initSelect2, destroySelect2 } from '@/Utils/select2'

const props = defineProps({
  digitalDoc: Object,
  approvals: Array,
  showApprovalButton: Boolean,
  approvalRequestType: { type: String, default: 'approve' },
  submitUrl: String
})

const loading = ref(false)
const usersList = ref([])
const currentAction = ref('approve')
const commentInput = ref('')

// Helpers
const capitalize = s => s?.charAt(0).toUpperCase() + s.slice(1)
const formatDateTime = date => formatDateWithTime(date)
const formatDate = date => formatDateShort(date)
const goBack = () => window.history.back()

// File type detection
const isPdf = computed(() => props.digitalDoc?.sharepoint_file_name?.toLowerCase().endsWith('.pdf'))
const isImage = computed(() => /\.(jpg|jpeg|png|gif|bmp|tiff)$/i.test(props.digitalDoc?.sharepoint_file_name ?? ''))

// File URLs
const streamUrl = computed(() => {
  if (!props.digitalDoc?.id) return '/pdfjs/sample.pdf'
  return `/digital-docs-approvals/${props.digitalDoc.id}/view`
})

const pdfViewerUrl = computed(() => {
  let fileUrl = props.digitalDoc?.id
      ? `/digital-docs-approvals/${props.digitalDoc.id}/view`
      : '/pdfjs/sample.pdf'
  fileUrl = encodeURIComponent(fileUrl)
  return `/pdfjs/web/viewer.html?file=${fileUrl}`
})

// Approval actions
const openConfirmModal = (action) => { currentAction.value = action; commentInput.value = ''; $('#confirmModal').modal('show') }
const resetConfirmModal = () => { commentInput.value = ''; $('#confirmModal').modal('hide') }

const submitApproval = async (action) => {
  loading.value = true
  try {
    const res = await axios.post(props.submitUrl, { request_type: props.approvalRequestType, action, comment: commentInput.value.trim() })
    showAlert('success', res.data.message || 'Action submitted successfully.')
    $('#confirmModal').modal('hide')
    setTimeout(() => window.location.href = res.data.redirect_url || window.location.href, 1500)
  } catch (err) { showAlert('Error', err.response?.data?.message || 'Action failed.','danger') }
  finally { loading.value = false }
}

// Reassign approval
const openReassignModal = async () => {
  loading.value = true
  try {
    const res = await axios.get('/api/digital-docs-approvals/get-users-for-reassign', { params: { request_type: props.approvalRequestType } })
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
    await axios.post(`/api/digital-docs-approvals/${props.digitalDoc.id}/reassign-approval`, { request_type: props.approvalRequestType, new_user_id: newUserId, comment })
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
