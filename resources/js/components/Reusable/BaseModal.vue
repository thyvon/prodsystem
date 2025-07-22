<template>
  <Teleport to="body">
    <transition name="modal-fade">
      <div
        v-show="modelValue || loading"
        :class="['modal', 'fade', { show: modelValue || loading }]"
        :id="id"
        tabindex="-1"
        role="dialog"
        :aria-labelledby="id + '-label'"
        aria-modal="true"
        :aria-hidden="!(modelValue || loading)"
        ref="modalRef"
        @keydown.esc="handleEsc"
        @mousedown.self="handleBackdrop"
        style="display: block;"
      >
        <div :class="dialogClass" role="document">
          <div class="modal-content border-0 shadow-lg">
            <!-- Loading Spinner -->
            <div v-if="loading" class="d-flex justify-content-center align-items-center" style="height: 100px;">
              <div class="spinner-border text-primary" role="status">
              </div>
            </div>

            <!-- Modal Content -->
            <div v-else>
              <!-- Header -->
              <div
                class="modal-header d-flex align-items-center"
                :class="['modal-header-slim', headerClass]"
              >
                <h5 class="modal-title font-weight-bold mb-0" :id="id + '-label'">{{ title }}</h5>
                <button type="button" class="close text-danger" @click="close" aria-label="Close">
                  <span aria-hidden="true">×</span>
                </button>
              </div>

              <!-- Body -->
              <div class="modal-body">
                <slot name="body" />
              </div>

              <!-- Footer -->
              <div class="modal-footer bg-light">
                <slot name="footer">
                  <button type="button" class="btn btn-secondary" @click="close">Close</button>
                </slot>
              </div>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'

const props = defineProps({
  id: { type: String, required: true },
  title: { type: String, default: '' },
  size: { type: String, default: 'xl' },
  headerClass: { type: String, default: 'bg-secondary text-white' },
  modelValue: { type: Boolean, default: false },
  loading: { type: Boolean, default: false }
})

const emit = defineEmits(['update:modelValue'])

const modalRef = ref(null)

const dialogClass = computed(() => {
  const sizeMap = {
    sm: 'modal-sm',
    md: 'modal-md',
    lg: 'modal-lg',
    xl: 'modal-xl'
  }
  return `modal-dialog ${sizeMap[props.size] || 'modal-xl'} modal-dialog-centered modal-dialog-scrollable`
})

const close = () => {
  emit('update:modelValue', false)
}

const show = () => {
  emit('update:modelValue', true)
}

const handleEsc = () => {
  close()
}

const handleBackdrop = (e) => {
  if (e.target === modalRef.value) close()
}

// Trap focus inside modal when open and not loading
watch(() => props.modelValue, (val) => {
  if (val && !props.loading) {
    nextTick(() => {
      modalRef.value?.focus()
    })
  }
})

defineExpose({ show, close })
</script>

<style scoped>
.modal {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
  z-index: 1050;
  overflow: hidden;
}

.modal-dialog {
  max-height: 90vh;
  margin: 1.75rem auto;
}

.modal-content {
  max-height: 90vh;
  overflow-y: auto !important;
  border-radius: 0.3rem; /* Align with SmartAdmin styling */
}

.modal-body {
  max-height: calc(90vh - 130px); /* Adjusted for SmartAdmin header/footer */
  overflow-y: auto !important;
  padding: 15px; /* Match SmartAdmin's default padding */
}

.modal-header-slim {
  padding: 10px 15px; /* Match SmartAdmin's slimmer header */
}

.modal-footer {
  padding: 10px 15px; /* Match SmartAdmin's footer styling */
}

.spinner-border {
  width: 2rem;
  height: 2rem;
  border-width: 0.3em;
}

.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.3s;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
</style>