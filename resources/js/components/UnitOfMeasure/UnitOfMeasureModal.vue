<template>
  <BaseModal
    v-model="showModal"
    id="unitOfMeasureModal"
    :title="isEditing ? 'Edit Unit of Measure' : 'Create Unit of Measure'"
    size="lg"
  >
    <template #body>
      <form @submit.prevent="submitForm">
        <div class="card border shadow-sm mb-0">
          <div class="card-header py-2 bg-light">
            <h6 class="mb-0 font-weight-bold">Unit of Measure Information</h6>
          </div>
          <div class="card-body">
            <div class="form-row">
              <div class="form-group col-md-4">
                <label>Name</label>
                <input
                  v-model="form.name"
                  type="text"
                  class="form-control"
                  required
                />
              </div>
              <div class="form-group col-md-4">
                <label>Khmer Name</label>
                <input
                  v-model="form.khmer_name"
                  type="text"
                  class="form-control"
                  required
                />
              </div>
              <div class="form-group col-md-4">
                <label>Short Name</label>
                <input
                  v-model="form.short_name"
                  type="text"
                  class="form-control"
                  required
                />
              </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                <label>Operator</label>
                <select  
                    ref="operatorSelect"
                    v-model="form.operator"
                    class="form-control"
                    required
                >
                    <option value="">Select Operator</option>
                    <option v-for="operator in operators" :key="operator" :value="operator">
                    {{ operator }}
                    </option>
                </select>
                </div>

                <div class="form-group col-md-6">
                    <label>Conversion Factor</label>
                    <input
                      v-model="form.conversion_factor"
                      type="number"
                      class="form-control"
                      required
                    />
                </div>
            </div>
            <div class="form-row">
              <div class="form-group col-md-6">
                <label>Parent Unit</label>
                <select
                  ref="parentUnitSelect"
                  v-model="form.parent_unit_id"
                  class="form-control"
                >
                  <option value="">Select Parent Unit</option>
                  <option v-for="parentUnit in parentUnits" :key="parentUnit.id" :value="parentUnit.id">
                    {{ parentUnit.text }}
                  </option>
                </select>
              </div>
                <div class="form-group col-md-6">
                <label>Description</label>
                <textarea
                  v-model="form.description"
                  class="form-control" rows="1"
                  required
                ></textarea>
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
import { ref, nextTick, onMounted, onUnmounted, watch } from 'vue'
import axios from 'axios'
import BaseModal from '@/components/Reusable/BaseModal.vue'
import { showAlert } from '@/Utils/bootbox'
import { initSelect2, destroySelect2 } from '@/Utils/select2'

const operators = ['*', '/', '+', '-']
const props = defineProps({ isEditing: Boolean })
const emit = defineEmits(['submitted'])

const showModal = ref(false)
const isSubmitting = ref(false)
const parentUnits = ref([])
const parentUnitSelect = ref(null)
const operatorSelect = ref(null)
const form = ref({
  id: null,
  short_name: '',
  name: '',
  khmer_name: '',
  operator: '',
  conversion_factor: 1,
  description: '',
  parent_unit_id: null,
  is_active: 1,
})


const fetchParentUnits = async () => {
  try {
    const response = await axios.get('/api/main-value-lists/get-unit-of-measures')
    parentUnits.value = Array.isArray(response.data) ? response.data : response.data.data
  } catch (err) {
    console.error('Failed to load parent units:', err)
    showAlert('Error', 'Failed to load parent units.', 'danger')
  }
}

const resetForm = () => {
  form.value = {
    id: null,
    short_name: '',
    name: '',
    khmer_name: '',
    operator: '',
    conversion_factor: 1,
    description: '',
    parent_unit_id: null,
    is_active: 1,
  }
}

const show = async (unitOfMeasure = null) => {
  resetForm()
  await fetchParentUnits()
  if (unitOfMeasure) {
    form.value = {
      id: unitOfMeasure.id,
      short_name: unitOfMeasure.short_name ?? '',
      name: unitOfMeasure.name ?? '',
      khmer_name: unitOfMeasure.khmer_name ?? '',
      operator: unitOfMeasure.operator ?? '',
      conversion_factor: unitOfMeasure.conversion_factor ?? 1,
      description: unitOfMeasure.description ?? '',
      parent_unit_id: unitOfMeasure.parent_unit_id ?? null,
      is_active: unitOfMeasure.is_active !== undefined ? unitOfMeasure.is_active : 1,
    }
  }
  await nextTick()
  showModal.value = true
}

const hideModal = () => {
  if (parentUnitSelect.value) {
    destroySelect2(parentUnitSelect.value)
  }
  showModal.value = false
}

const submitForm = async () => {
  if (isSubmitting.value) return
  isSubmitting.value = true
  try {
    const method = props.isEditing ? 'put' : 'post'
    const url = props.isEditing && form.value.id
      ? `/api/unit-of-measures/${form.value.id}`
      : '/api/unit-of-measures'

    const payload = {
      short_name: form.value.short_name?.toString().trim() ?? '',
      name: form.value.name?.toString().trim() ?? '',
      khmer_name: form.value.khmer_name?.toString().trim() ?? '',
      operator: form.value.operator?.toString().trim() ?? '',
      conversion_factor: form.value.conversion_factor?.toString().trim() ?? 1,
      description: form.value.description?.toString().trim() ?? '',
      parent_unit_id: form.value.parent_unit_id,
      is_active: form.value.is_active ? 1 : 0,
    }

    await axios[method](url, payload)
    emit('submitted')
    hideModal()
    showAlert('Success', `Unit of Measure ${props.isEditing ? 'updated' : 'created'} successfully.`, 'success')
  } catch (err) {
    console.error('Submit error:', err.response?.data || err)
    showAlert('Error', err.response?.data?.message || err.message || 'Failed to save Unit of Measure.', 'danger')
  } finally {
    isSubmitting.value = false
  }
}

// Initialize Select2 for parent unit selection when modal is shown
watch(showModal, async (val) => {
  if (val) {
    await nextTick()
    const $modal = window.$('#unitOfMeasureModal')
    initSelect2(parentUnitSelect.value, {
      placeholder: 'Select Parent Unit',
      width: '100%',
      allowClear: true,
      dropdownParent: $modal
    }, v => form.value.parent_unit_id = v)

        // Init Operator Select2
    initSelect2(operatorSelect.value, {
      placeholder: 'Select Operator',
      width: '100%',
      allowClear: true,
      dropdownParent: $modal
    }, v => form.value.operator = v)

    // Set initial value if exists
    await nextTick()
    window.$(parentUnitSelect.value).val(form.value.parent_unit_id).trigger('change')
    window.$(operatorSelect.value).val(form.value.operator).trigger('change')
  } else {
    destroySelect2(parentUnitSelect.value)
    destroySelect2(operatorSelect.value)
  }
})

defineExpose({ show })

onMounted(fetchParentUnits)
onUnmounted(() => {
  if (parentUnitSelect.value) destroySelect2(parentUnitSelect.value)
  if (operatorSelect.value) destroySelect2(operatorSelect.value)
})
</script>