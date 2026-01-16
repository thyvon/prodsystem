<template>
  <div class="card-header bg-light py-2 d-flex justify-between items-center">
    <button class="btn btn-sm btn-outline-success" @click="$emit('goBack')">
      <i class="fal fa-backward"></i> Back
    </button>
    <button class="btn btn-outline-secondary btn-sm" @click="$emit('printPdf')">
      <i class="fal fa-print"></i> Print
    </button>
  </div>
</template>

<script setup>
</script>
