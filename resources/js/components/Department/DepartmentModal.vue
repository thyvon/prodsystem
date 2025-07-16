<template>
  <BaseModal
    v-model="showModal"
    id="departmentModal"
    :title="isEditing ? 'Edit Department' : 'Create Department'"
    size="lg"
  >
    <template #body>
      <form @submit.prevent="submitForm">
        <div class="card border shadow-sm mb-0">
          <div class="card-header py-2 bg-light">
            <h6 class="mb-0 font-weight-bold">Department Information</h6>
          </div>
          <div class="card-body">
            <div class="form-row">
              <div class="form-group col-md-6">
                <label>Department Name</label>
                <input
                  v-model="form.name"
                  type="text"
                  class="form-control"
                  required
                />
              </div>
              <div class="form-group col-md-6">
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
              <div class="form-group col-md-12">
                <label>Division</label>
                <select
                  ref="divisionSelect"
                  v-model="form.division_id"
                  class="form-control"
                  required
                >
                  <option value="">Select Division</option>
                  <option v-for="division in divisions" :key="division.id" :value="division.id">
                    {{ division.name }} ({{ division.short_name }})
                  </option>
                </select>
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
import BaseModal from '@/components/reusable/BaseModal.vue'
import { showAlert } from '@/Utils/bootbox'
import { initSelect2, destroySelect2 } from '@/Utils/select2'

const props = defineProps({ isEditing: Boolean })
const emit = defineEmits(['submitted'])

const showModal = ref(false)
const isSubmitting = ref(false)
const divisions = ref([])
const divisionSelect = ref(null)
const form = ref({
  id: null,
  short_name: '',
  name: '',
  division_id: null,
  is_active: 1,
})

const fetchDivisions = async () => {
  try {
    const response = await axios.get('/api/divisions')
    divisions.value = Array.isArray(response.data) ? response.data : response.data.data
  } catch (err) {
    console.error('Failed to load divisions:', err)
    showAlert('Error', 'Failed to load divisions.', 'danger')
  }
}

const resetForm = () => {
  form.value = {
    id: null,
    short_name: '',
    name: '',
    division_id: null,
    is_active: 1,
  }
}

const show = async (department = null) => {
  resetForm()
  await fetchDivisions()
  if (department) {
    form.value = {
      id: department.id,
      short_name: department.short_name ?? '',
      name: department.name ?? '',
      division_id: department.division_id ?? null,
      is_active: department.is_active !== undefined ? department.is_active : 1,
    }
  }
  await nextTick()
  showModal.value = true
}

const hideModal = () => {
  if (divisionSelect.value) {
    destroySelect2(divisionSelect.value)
  }
  showModal.value = false
}

const submitForm = async () => {
  if (isSubmitting.value) return
  isSubmitting.value = true
  try {
    const method = props.isEditing ? 'put' : 'post'
    const url = props.isEditing && form.value.id
      ? `/api/departments/${form.value.id}`
      : '/api/departments'

    const payload = {
      short_name: form.value.short_name?.toString().trim() ?? '',
      name: form.value.name?.toString().trim() ?? '',
      division_id: form.value.division_id,
      is_active: form.value.is_active ? 1 : 0,
    }

    await axios[method](url, payload)
    emit('submitted')
    hideModal()
    showAlert('Success', `Department ${props.isEditing ? 'updated' : 'created'} successfully.`, 'success')
  } catch (err) {
    console.error('Submit error:', err.response?.data || err)
    showAlert('Error', err.response?.data?.message || err.message || 'Failed to save department.', 'danger')
  } finally {
    isSubmitting.value = false
  }
}

// Initialize Select2 for division selection when modal is shown
watch(showModal, async (val) => {
  if (val) {
    await nextTick()
    const $modal = window.$('#departmentModal')
    initSelect2(divisionSelect.value, {
      placeholder: 'Select Division',
      width: '100%',
      allowClear: true,
      dropdownParent: $modal
    }, v => form.value.division_id = v)
    // Set initial value if exists
    await nextTick()
    window.$(divisionSelect.value).val(form.value.division_id).trigger('change')
  } else {
    destroySelect2(divisionSelect.value)
  }
})

defineExpose({ show })

onMounted(fetchDivisions)
onUnmounted(() => {
  if (divisionSelect.value) {
    destroySelect2(divisionSelect.value)
  }
})
</script>