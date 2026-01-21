<template>
  <div class="card-header bg-light py-2 d-flex justify-between items-center">
    <button class="btn btn-sm btn-outline-success" @click="$emit('goBack')">
      <i class="fal fa-backward"></i> Back
    </button>
    <button class="btn btn-outline-secondary btn-sm" @click="$emit('printPdf')">
      <i class="fal fa-print"></i> Print
    </button>
    <button v-if="showEditPurchaseRequestButton" class="btn btn-outline-secondary btn-sm" @click="$emit('editPurchaseRequest')">
      <i class="fal fa-edit"></i> Edit
    </button>
    <button v-if="showAssignPurchaserButton" class="btn btn-sm btn-outline-primary" @click="$emit('assignPurchaser')">
      <i class="fal fa-user-plus"></i> Assign Purchaser
    </button>
  </div>
</template>

<script setup>
    defineProps({
        showAssignPurchaserButton: {
            type: Boolean,
            default: false
        },
        showEditPurchaseRequestButton: {
            type: Boolean,
            default: false
        }
    });
</script>
