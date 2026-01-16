<template>
  <div class="d-flex align-items-center">
    <!-- Approval Box -->
    <div class="d-flex flex-column align-items-center" style="min-width: 120px;">
      <div class="mb-1 font-weight-bold text-dark">{{ index }}</div>
      <span class="badge px-3 py-2 mb-1" :class="badgeClass(approval)">
        {{ approval.approval_status === 'Approved'
            ? (approval.request_type_label || approval.request_type)
            : approval.approval_status }}
      </span>
      <small class="text-muted text-center">
        Name: {{ approval.name ?? 'N/A' }}<br>
        Date: {{ formatDate(approval.responded_date) ?? 'N/A' }}
      </small>
    </div>

    <!-- Arrow to Next Approval -->
    <template v-if="nextApproval">
      <div class="d-flex flex-column align-items-center mx-2">
        <!-- Days from purchase request creation to next approval responded_date -->
        <small class="text-secondary mb-1 font-weight-bold">
          {{ daysBetween(request_date, nextApproval.responded_date) }}
        </small>
        <i class="fal fa-arrow-right text-secondary fa-2x"></i>
      </div>
    </template>
  </div>
</template>

<script setup>
const props = defineProps({
  approval: Object,
  index: Number,
  request_date: String, // purchaseRequest.request_date
  nextApproval: Object,
  formatDate: Function,
  daysBetween: Function
})

const badgeClass = (approval) => {
  if (approval.approval_status === 'Approved') return 'border border-success text-success'
  if (approval.approval_status === 'Returned') return 'bg-warning text-dark'
  if (approval.approval_status === 'Rejected') return 'bg-danger text-white'
  return 'border border-secondary text-secondary'
}
</script>
