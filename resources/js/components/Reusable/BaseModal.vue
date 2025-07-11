<template>
  <Teleport to="body">
    <transition name="modal-fade">
      <div
        v-show="modelValue"
        :class="['modal', 'fade', { show: modelValue }]"
        :id="id"
        tabindex="-1"
        role="dialog"
        :aria-labelledby="id + '-label'"
        aria-modal="true"
        :aria-hidden="!modelValue"
        ref="modalRef"
        @keydown.esc="handleEsc"
        @mousedown.self="handleBackdrop"
        style="display: block;"
      >
        <div :class="dialogClass" role="document">
          <div class="modal-content border-0 shadow-lg">
            <!-- Header -->
            <div
              class="modal-header d-flex align-items-center"
              :class="['modal-header-slim', headerClass]"
            >
              <h5 class="modal-title font-weight-bold mb-0" :id="id + '-label'">{{ title }}</h5>
              <button type="button" class="close text-danger" @click="close" aria-label="Close">
                <span aria-hidden="true">&times;</span>
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
  modelValue: { type: Boolean, default: false }
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

// Trap focus inside modal when open
watch(() => props.modelValue, (val) => {
  if (val) {
    nextTick(() => {
      modalRef.value?.focus()
    })
  }
})

defineExpose({ show, close })
</script>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.3s;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
</style>
