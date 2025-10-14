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
        <div class="col-6">
          <p class="text-muted mb-1">
            CREATED BY / អ្នករៀបចំ:
            <span class="font-weight-bold">{{ digitalDoc.creator?.name ?? 'N/A' }}</span>
          </p>
          <p class="text-muted mb-1">
            DATE / កាលបរិច្ឆេទ:
            <span class="font-weight-bold">{{ formatDate(digitalDoc.created_at) ?? 'N/A' }}</span>
          </p>
        </div>

        <div class="col-6 text-right">
          <p class="text-muted mb-1">
            REF / លេខយោង: <span class="font-weight-bold">{{ digitalDoc.reference_no ?? 'N/A' }}</span>
          </p>
          <p class="text-muted mb-1">
            DOCUMENT TYPE / ប្រភេទឯកសារ: <span class="font-weight-bold">{{ digitalDoc.document_type ?? 'N/A' }}</span>
          </p>
        </div>
      </div>

      <!-- Description and File Link -->
      <div class="mb-3">
        <p class="text-muted mb-1">DESCRIPTION / ពិពណ៌នា:</p>
        <p class="font-weight-bold">{{ digitalDoc.description ?? 'N/A' }}</p>
        <p class="text-muted mb-1">FILE / ឯកសារ:</p>
        <p>
          <a :href="digitalDoc.sharepoint_file_url" target="_blank">{{ digitalDoc.sharepoint_file_name }}</a>
        </p>
      </div>

      <!-- Approvals -->
      <div class="mt-4">
        <div class="row justify-content-center">
          <div v-for="(approval, i) in approvals" :key="i" class="col-md-3 mb-4">
            <div class="card border shadow-sm h-100">
              <div class="card-body">
                <label class="font-weight-bold text-center d-block w-100">{{ approval.request_type_label }} By</label>
                <div class="d-flex align-items-center mb-2 justify-content-center">
                  <img :src="approval.responder?.profile_url" class="rounded-circle" width="50" height="50">
                </div>
                <div class="font-weight-bold text-center mb-2">{{ approval.responder?.name ?? 'N/A' }}</div>
                <div v-if="approval.approval_status === 'Approved'" class="d-flex justify-content-center mb-2">
                  <img :src="approval.responder?.signature_url" height="50">
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

    <!-- Footer -->
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
  </div>
</template>

<script setup>
import { ref, nextTick } from 'vue'
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
</style>
