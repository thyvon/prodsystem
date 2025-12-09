<template>
  <Teleport to="body">
    <transition name="modal-fade">
      <div
        v-show="modelValue || loading"
        :class="['modal','fade', { show: modelValue || loading }]"
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
              <div class="spinner-border text-primary" role="status"></div>
            </div>

            <div v-else>
              <!-- Header with SmartAdmin animated gradient -->
              <div class="modal-header smart-gradient-header d-flex align-items-center">
                <h5 class="modal-title font-weight-bold mb-0" :id="id + '-label'">{{ title }}</h5>
                <button type="button" class="close text-white" @click="close" aria-label="Close">
                  <span aria-hidden="true">×</span>
                </button>
              </div>

              <!-- Scrollable body -->
              <div class="modal-body">
                <slot name="body"/>
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
  modelValue: { type: Boolean, default: false },
  loading: { type: Boolean, default: false }
})

const emit = defineEmits(['update:modelValue'])
const modalRef = ref(null)

const dialogClass = computed(() => {
  const sizeMap = { sm:'modal-sm', md:'modal-md', lg:'modal-lg', xl:'modal-xl' }
  return `modal-dialog ${sizeMap[props.size]||'modal-xl'} modal-dialog-centered modal-dialog-scrollable`
})

const close = () => emit('update:modelValue', false)
const show = () => emit('update:modelValue', true)
const handleEsc = () => close()
const handleBackdrop = (e) => { if (e.target === modalRef.value) close() }

watch(() => props.modelValue, (val) => {
  if (val && !props.loading) nextTick(() => modalRef.value?.focus())
})

defineExpose({ show, close })
</script>

<style scoped>
/* Modal container & scrollable body */
.modal-content {
  max-height: 90vh;
  overflow: hidden;
  border-radius: 0.3rem;
}

.modal-body {
  max-height: calc(90vh - 120px); /* subtract header/footer height */
  overflow-y: auto;
  padding: 15px;
}

/* SmartAdmin animated gradient header */
.smart-gradient-header {
  position: relative;
  overflow: hidden;
  color: white;
  border-radius: 0.3rem 0.3rem 0 0;
  background-color: var(--bs-primary, #007bff);
}

.smart-gradient-header::before {
  content: '';
  position: absolute;
  inset: 0;
  z-index: 0;
  background: linear-gradient(
    90deg,
    rgba(var(--bs-primary-rgb,0,123,255),1),
    rgba(var(--bs-success-rgb,40,167,69),1),
    rgba(var(--bs-info-rgb,23,162,184),1)
  );
  background-size: 300% 100%;
  animation: slide-gradient 8s linear infinite;
  filter: brightness(1.1);
}

.smart-gradient-header > * {
  position: relative;
  z-index: 1; /* ensure text & close button above gradient */
}

@keyframes slide-gradient {
  0% { background-position: 0% 0%; }
  50% { background-position: 100% 0%; }
  100% { background-position: 0% 0%; }
}

/* Fade animation */
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.3s;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
</style>
