<template>
  <div class="col-md-6 d-flex">
    <div class="border rounded p-3 flex-fill">
      <h5 class="text-primary font-weight-bold mb-3">👤 Requester Info</h5>
      <div v-for="(value, label) in requester" :key="label" class="row mb-2">
        <div class="col-4 font-weight-bold text-muted">{{ label }}:</div>
        <div class="col-8 border-bottom py-1">{{ value || 'N/A' }}</div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  requester: { type: Object, required: true },
})
</script>
