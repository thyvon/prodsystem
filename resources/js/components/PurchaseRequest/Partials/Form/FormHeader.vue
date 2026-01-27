<template>
  <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
    <h4 class="mb-0 font-weight-bold">
      {{ isEditMode ? '✏️ Edit' : '➕ Create' }} Purchase Request
    </h4>
    <button type="button" class="btn btn-outline-primary btn-sm" @click="$emit('back')">
      <i class="fal fa-backward"></i> Back
    </button>
  </div>
</template>

<script setup>
defineProps({
  isEditMode: { type: Boolean, required: true },
})

defineEmits(['back'])
</script>
