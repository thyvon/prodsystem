<template>
  <BaseModal
    v-model="showModal"
    id="tocaAmountModal"
    :title="isEditing ? 'Edit TOCA Amount' : 'Create TOCA Amount'"
    size="lg"
  >
    <template #body>
      <form @submit.prevent="submitForm">
        <div class="card border shadow-sm mb-0">
          <div class="card-header py-2 bg-light">
            <h6 class="mb-0 font-weight-bold">TOCA Amount Information</h6>
          </div>
          <div class="card-body">
            <div class="form-row">
              <div class="form-group col-md-12">
                <label for="tocaPolicySelect">TOCA Name</label>
                <select
                  ref="tocaPolicySelect"
                  v-model="form.toca_id"
                  id="tocaPolicySelect"
                  class="form-control"
                  :class="{ 'is-invalid': errors.toca_id }"
                  required
                >
                  <option value="">Select TOCA</option>
                  <option v-for="toca in tocas" :key="toca.id" :value="toca.id">
                    {{ toca.name }} ({{ toca.short_name }})
                  </option>
                </select>
                <div v-if="errors.toca_id" class="invalid-feedback">{{ errors.toca_id }}</div>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group col-md-6">
                <label for="minAmount">Min Value</label>
                <input
                  id="minAmount"
                  v-model="form.min_amount"
                  type="number"
                  class="form-control"
                  :class="{ 'is-invalid': errors.min_amount }"
                  required
                />
                <div v-if="errors.min_amount" class="invalid-feedback">{{ errors.min_amount }}</div>
              </div>
              <div class="form-group col-md-6">
                <label for="maxAmount">Max Value</label>
                <input
                  id="maxAmount"
                  v-model="form.max_amount"
                  type="number"
                  class="form-control"
                  :class="{ 'is-invalid': errors.max_amount }"
                  required
                />
                <div v-if="errors.max_amount" class="invalid-feedback">{{ errors.max_amount }}</div>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group col-md-4">
                <div class="custom-control custom-checkbox mt-4">
                  <input
                    type="checkbox"
                    class="custom-control-input"
                    id="isActive"
                    v-model="form.is_active"
                    :true-value="1"
                    :false-value="0"
                  />
                  <label class="custom-control-label" for="isActive">Active</label>
                </div>
              </div>
            </div>
          </div>
        </div>
      </form>
    </template>

    <template #footer>
      <button type="button" class="btn btn-secondary" @click="hideModal">Cancel</button>
      <button
        type="submit"
        class="btn btn-primary"
        @click="submitForm"
        :disabled="isSubmitting"
      >
        <span v-if="isSubmitting" class="spinner-border spinner-border-sm mr-1"></span>
        {{ isEditing ? 'Update' : 'Create' }}
      </button>
    </template>
  </BaseModal>
</template>

<script setup>
import { ref, nextTick, watch } from 'vue'
import axios from 'axios'
import BaseModal from '@/components/Reusable/BaseModal.vue'
import { showAlert } from '@/Utils/bootbox'
import { initSelect2, destroySelect2 } from '@/Utils/select2'

const props = defineProps({ isEditing: Boolean })
const emit = defineEmits(['submitted'])

const showModal = ref(false)
const isSubmitting = ref(false)
const tocas = ref([])
const tocaPolicySelect = ref(null)
const form = ref({
  id: null,
  min_amount: '',
  max_amount: '',
  toca_id: null,
  is_active: 1,
})
const errors = ref({})

const fetchTocaPolicy = async () => {
  try {
    const response = await axios.get('/api/toca-policies')
    tocas.value = Array.isArray(response.data) ? response.data : response.data.data
  } catch (err) {
    console.error('Failed to load TOCAs:', err)
    showAlert('Error', 'Failed to load TOCAs.', 'danger')
  }
}

const resetForm = () => {
  form.value = {
    id: null,
    min_amount: '',
    max_amount: '',
    toca_id: null,
    is_active: 1,
  }
  errors.value = {}
}

const show = async (tocaAmount = null) => {
  resetForm()
  await fetchTocaPolicy()
  const data = typeof tocaAmount === 'string' ? JSON.parse(tocaAmount) : tocaAmount
  if (data) {
    form.value = {
      id: data.id || null,
      min_amount: data.min_amount ?? '',
      max_amount: data.max_amount ?? '',
      toca_id: data.toca_id ?? null,
      is_active: data.is_active !== undefined ? (data.is_active ? 1 : 0) : 1,
    }
  }
  await nextTick()
  showModal.value = true
}

const hideModal = () => {
  if (tocaPolicySelect.value) {
    destroySelect2(tocaPolicySelect.value)
  }
  showModal.value = false
  errors.value = {}
}

const submitForm = async () => {
  if (isSubmitting.value) return
  isSubmitting.value = true
  errors.value = {}

  try {
    const method = props.isEditing ? 'put' : 'post'
    const url = props.isEditing && form.value.id
      ? `/api/toca-amounts/${form.value.id}`
      : '/api/toca-amounts'

    const payload = {
      min_amount: form.value.min_amount?.toString().trim() ?? '',
      max_amount: form.value.max_amount?.toString().trim() ?? '',
      toca_id: form.value.toca_id,
      is_active: form.value.is_active ? 1 : 0,
    }

    await axios[method](url, payload)
    emit('submitted')
    hideModal()
    showAlert('Success', `TOCA Amount ${props.isEditing ? 'updated' : 'created'} successfully.`, 'success')
  } catch (err) {
    console.error('Submit error:', err.response?.data || err)
    if (err.response?.status === 422) {
      errors.value = err.response.data.errors || {}
      showAlert('Validation Error', 'Please check the form for errors.', 'danger')
    } else {
      showAlert('Error', err.response?.data?.message || err.message || 'Failed to save TOCA Amount.', 'danger')
    }
  } finally {
    isSubmitting.value = false
  }
}

// Watcher to initialize Select2 whenever modal shows
watch(showModal, async (val) => {
  if (val) {
    await nextTick()
    try {
      const $modal = window.$('#tocaAmountModal')
      if (!$modal.length) throw new Error('Modal element #tocaAmountModal not found')

      initSelect2(tocaPolicySelect.value, {
        placeholder: 'Select TOCA',
        width: '100%',
        allowClear: true,
        dropdownParent: $modal,
      }, (value) => {
        form.value.toca_id = value ? parseInt(value) : null
      })

      // Set Select2 initial value properly after init
      await nextTick()
      if (form.value.toca_id !== null && form.value.toca_id !== '') {
        window.$(tocaPolicySelect.value).val(form.value.toca_id.toString()).trigger('change')
      }
    } catch (err) {
      console.error('Failed to initialize Select2:', err)
      showAlert('Error', 'Failed to initialize TOCA selector.', 'danger')
    }
  } else {
    if (tocaPolicySelect.value) {
      destroySelect2(tocaPolicySelect.value)
    }
  }
})

defineExpose({ show })
</script>
