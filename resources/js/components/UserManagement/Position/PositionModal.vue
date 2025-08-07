<template>
  <BaseModal
    v-model="showModal"
    id="positionModal"
    :title="isEditing ? 'Edit Position' : 'Create Position'"
    size="lg"
  >
    <template #body>
      <form @submit.prevent="submitForm">
        <div class="card border shadow-sm mb-0">
          <div class="card-header py-2 bg-light">
            <h6 class="mb-0 font-weight-bold">Position Information</h6>
          </div>
          <div class="card-body">
            <div class="form-row">
              <div class="form-group col-md-6">
                <label>Position Title</label>
                <input
                  v-model="form.title"
                  type="text"
                  class="form-control"
                  required
                />
              </div>
              <div class="form-group col-md-6">
                <label>Short Title</label>
                <input
                  v-model="form.short_title"
                  type="text"
                  class="form-control"
                  required
                />
              </div>
            </div>
            <div class="form-row">
              <div class="form-group col-md-12">
                <label>Department</label>
                <select
                  ref="departmentSelect"
                  v-model="form.department_id"
                  class="form-control"
                  required
                >
                  <option value="">Select Department</option>
                  <option
                    v-for="department in departments"
                    :key="department.id"
                    :value="department.id"
                  >
                    {{ department.name }} ({{ department.short_name }})
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
import BaseModal from '@/components/Reusable/BaseModal.vue'
import { showAlert } from '@/Utils/bootbox'
import { initSelect2, destroySelect2 } from '@/Utils/select2'

const props = defineProps({ isEditing: Boolean })
const emit = defineEmits(['submitted'])

const showModal = ref(false)
const isSubmitting = ref(false)
const departments = ref([])
const departmentSelect = ref(null)
const form = ref({
  id: null,
  title: '',
  short_title: '',
  department_id: null,
  is_active: 1,
})

const fetchDepartments = async () => {
  try {
    const response = await axios.get('/api/departments')
    departments.value = Array.isArray(response.data) ? response.data : response.data.data
  } catch (err) {
    console.error('Failed to load departments:', err)
    showAlert('Error', 'Failed to load departments.', 'danger')
  }
}

const resetForm = () => {
  form.value = {
    id: null,
    title: '',
    short_title: '',
    department_id: null,
    is_active: 1,
  }
}

const show = async (position = null) => {
  resetForm()
  await fetchDepartments()
  if (position) {
    form.value = {
      id: position.id,
      title: position.title ?? '',
      short_title: position.short_title ?? '',
      department_id: position.department_id ?? null,
      is_active: position.is_active !== undefined ? position.is_active : 1,
    }
  }
  await nextTick()
  showModal.value = true
}

const hideModal = () => {
  if (departmentSelect.value) {
    destroySelect2(departmentSelect.value)
  }
  showModal.value = false
}

const submitForm = async () => {
  if (isSubmitting.value) return
  isSubmitting.value = true
  try {
    const method = props.isEditing ? 'put' : 'post'
    const url = props.isEditing && form.value.id
      ? `/api/positions/${form.value.id}`
      : '/api/positions'

    const payload = {
      title: form.value.title?.toString().trim() ?? '',
      short_title: form.value.short_title?.toString().trim() ?? '',
      department_id: form.value.department_id,
      is_active: form.value.is_active ? 1 : 0,
    }

    await axios[method](url, payload)
    emit('submitted')
    hideModal()
    showAlert('Success', `Position ${props.isEditing ? 'updated' : 'created'} successfully.`, 'success')
  } catch (err) {
    console.error('Submit error:', err.response?.data || err)
    showAlert('Error', err.response?.data?.message || err.message || 'Failed to save position.', 'danger')
  } finally {
    isSubmitting.value = false
  }
}

watch(showModal, async (val) => {
  if (val) {
    await nextTick()
    const $modal = window.$('#positionModal')
    initSelect2(departmentSelect.value, {
      placeholder: 'Select Department',
      width: '100%',
      allowClear: true,
      dropdownParent: $modal
    }, v => form.value.department_id = v)
    await nextTick()
    window.$(departmentSelect.value).val(form.value.department_id).trigger('change')
  } else {
    destroySelect2(departmentSelect.value)
  }
})

defineExpose({ show })

onMounted(fetchDepartments)
onUnmounted(() => {
  if (departmentSelect.value) {
    destroySelect2(departmentSelect.value)
  }
})
</script>
