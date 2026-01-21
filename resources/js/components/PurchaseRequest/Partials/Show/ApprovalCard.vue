<template>
  <div class="card border shadow-sm h-100">
    <div class="card-body">
      <label class="font-weight-bold d-block text-center">
        <span v-html="approval.request_type_label || approval.request_type"></span>
      </label>

      <div class="d-flex mb-2">
        <img
          :src="approval.user_profile_url ? `/storage/${approval.user_profile_url}` : '/images/default-avatar.png'"
          class="rounded-circle"
          width="50"
          height="50"
        />
      </div>
      <hr>

      <div class="font-weight-bold mb-1">{{ approval.name ?? 'N/A' }}</div>

      <p class="mb-1 text-start">
        Status:
        <span
          class="badge"
          :class="{
            'badge-success': approval.approval_status === 'Approved',
            'badge-danger': approval.approval_status === 'Rejected',
            'badge-warning': approval.approval_status === 'Pending',
            'badge-info': approval.approval_status === 'Returned',
            'badge-secondary': approval.approval_status === 'Received',
            'badge-primary': approval.approval_status === 'Verified'
          }"
        >
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
</template>

<script setup>
const props = defineProps({
  approval: Object,
  formatDate: Function,
  capitalize: Function
})
</script>
