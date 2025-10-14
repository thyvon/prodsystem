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
            CREATED BY / អ្នករៀបចំ:
            <span class="font-weight-bold">{{ digitalDoc.creator?.name ?? 'N/A' }}</span>
          </p>
          <p class="text-muted mb-1">
            DATE / កាលបរិច្ឆេទ:
            <span class="font-weight-bold">{{ formatDate(digitalDoc.created_at) ?? 'N/A' }}</span>
          </p>
        </div>

        <div class="col-6 text-center">
          <h4 class="font-weight-bold text-dark">ឯកសារ</h4>
          <h4 class="font-weight-bold text-dark">DIGITAL DOCUMENT APPROVAL</h4>
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

      <!-- Description and File Preview -->
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

        <!-- PDF.js Preview -->
        <div v-if="isPdf" class="mt-3 border rounded bg-light overflow-hidden" style="height: 800px;">
          <div class="p-3 bg-white">
            <h6 class="mb-2"><i class="fal fa-file-pdf text-danger"></i> PDF Preview</h6>
            <div id="pdf-viewer-container" class="border" style="height: 700px; overflow: auto; background: #f8f9fa;">
              <div v-if="pdfLoading" class="text-center p-4">
                <i class="fal fa-spinner fa-spin"></i> Loading PDF...
              </div>
            </div>
          </div>
          <div class="p-2 text-center bg-light">
            <small class="text-muted">Use mouse wheel to zoom. Scroll to navigate pages.</small>
          </div>
        </div>

        <!-- Image Preview -->
        <div v-else-if="isImage" class="mt-3 text-center border rounded bg-light p-3">
          <img :src="streamUrl" class="img-fluid rounded" style="max-height: 700px;" :alt="digitalDoc.sharepoint_file_name">
        </div>

        <p v-else class="text-muted mt-3">Preview not available for this file type. Use the links above.</p>
      </div>

      <!-- Approvals -->
      <div class="mt-4">
        <div class="row justify-content-center">
          <!-- Requested By -->
          <div class="col-md-3 mb-4">
            <div class="card border shadow-sm h-100">
              <div class="card-body">
                <label class="font-weight-bold text-center d-block w-100">Requested By</label>
                <div class="d-flex align-items-center mb-2 justify-content-center">
                  <img :src="digitalDoc.creator?.profile_url" class="rounded-circle" width="50" height="50">
                </div>
                <div class="font-weight-bold text-center mb-2">{{ digitalDoc.creator?.name ?? 'N/A' }}</div>
                <div v-if="digitalDoc.creator?.signature_url" class="d-flex justify-content-center mb-2">
                  <img :src="digitalDoc.creator.signature_url" height="50">
                </div>
                <p class="mb-1">Status: <span class="badge badge-primary"><strong>Requested</strong></span></p>
                <p class="mb-1">Position: {{ digitalDoc.creator_position?.title ?? 'N/A' }}</p>
                <p class="mb-0">Date: {{ formatDateTime(digitalDoc.created_at) || 'N/A' }}</p>
              </div>
            </div>
          </div>

          <!-- Approvals Cards -->
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
                    <strong>{{ approval.approval_status === 'Approved' ? 'Signed' : capitalize(approval.approval_status) }}</strong>
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

  </div>
</template>

<script setup>
import { ref, onMounted, computed, nextTick } from 'vue'
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

// File preview
const isPdf = computed(() => props.digitalDoc?.sharepoint_file_name?.toLowerCase().endsWith('.pdf'))
const isImage = computed(() => /\.(jpg|jpeg|png|gif|bmp|tiff)$/i.test(props.digitalDoc?.sharepoint_file_name ?? ''))
const streamUrl = computed(() => `/digital-docs-approvals/${props.digitalDoc.id}/view`)

// PDF.js setup
let pdfjsLib = null
const pdfLoading = ref(false)
onMounted(async () => {
  if (isPdf.value) {
    await loadPdfJsFromCdn()
    await renderPdf()
  }
})

const loadPdfJsFromCdn = async () => {
  if (pdfjsLib) return
  pdfLoading.value = true
  try {
    const pdfModule = await import('https://cdnjs.cloudflare.com/ajax/libs/pdf.js/5.4.149/pdf.min.mjs')
    pdfjsLib = pdfModule
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/5.4.149/pdf.worker.min.mjs'
  } catch (err) {
    console.error('PDF.js load error:', err)
    showAlert('Error', 'Failed to load PDF viewer.', 'danger')
  } finally { pdfLoading.value = false }
}

const renderPdf = async () => {
  if (!pdfjsLib || !streamUrl.value) return
  const container = document.getElementById('pdf-viewer-container')
  if (!container) return
  container.innerHTML = '<div class="text-center p-4"><i class="fal fa-spinner fa-spin"></i> Loading PDF...</div>'

  try {
    const loadingTask = pdfjsLib.getDocument({
      url: streamUrl.value,
      withCredentials: true,
      httpHeaders: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' }
    })
    const pdf = await loadingTask.promise
    container.innerHTML = ''

    for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
      const page = await pdf.getPage(pageNum)
      const scale = 1.5
      const viewport = page.getViewport({ scale })
      const canvas = document.createElement('canvas')
      canvas.width = viewport.width
      canvas.height = viewport.height
      const ctx = canvas.getContext('2d')
      await page.render({ canvasContext: ctx, viewport }).promise

      const pageDiv = document.createElement('div')
      pageDiv.style.marginBottom = '20px'
      pageDiv.style.textAlign = 'center'
      pageDiv.appendChild(canvas)

      const label = document.createElement('small')
      label.textContent = `Page ${pageNum} of ${pdf.numPages}`
      label.style.display = 'block'
      label.style.color = '#6c757d'
      pageDiv.appendChild(label)

      container.appendChild(pageDiv)
    }
  } catch (err) {
    console.error('PDF.js render error:', err)
    container.innerHTML = `
      <div class="text-center p-4">
        <i class="fal fa-exclamation-triangle text-warning fa-2x"></i>
        <p class="mt-2">Failed to load PDF preview.</p>
        <a href="${streamUrl.value}" class="btn btn-sm btn-primary" target="_blank">Open PDF in New Tab</a>
      </div>
    `
  }
}

// Approvals
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
  } catch (err) { showAlert('Error', err.response?.data?.message || 'Action failed.','danger') }
  finally { loading.value = false }
}

const openReassignModal = async () => {
  loading.value = true
  try {
    const res = await axios.get('/api/digital-approvals/get-users-for-approval', { params: { request_type: props.approvalRequestType } })
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
    await axios.post(`/api/digital-approvals/${props.digitalDoc.id}/reassign-approval`, { request_type: props.approvalRequestType, new_user_id: newUserId, comment })
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
.pdf-page-canvas { image-rendering: -webkit-optimize-contrast; }
</style>
