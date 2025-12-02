<template>
  <BaseModal
    v-model="showModal"
    id="buildingModal"
    :title="isEditing ? 'Edit Building' : 'Create Building'"
    size="lg"
  >
    <template #body>
      <form @submit.prevent="submitForm">
        <div class="card border shadow-sm mb-0">
          <div class="card-header py-2 bg-light">
            <h6 class="mb-0 font-weight-bold">Building Information</h6>
          </div>
          <div class="card-body">
            <div class="form-row">
              <div class="form-group col-md-6">
                <label>Building Name</label>
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
              <div class="form-group col-md-6">
                <label>Address</label>
                <input
                  v-model="form.address"
                  type="text"
                  class="form-control"
                  required
                />
              </div>
              <div class="form-group col-md-6">
                <label>Campus</label>
                <select
                  ref="campusSelect"
                  v-model="form.campus_id"
                  class="form-control"
                  required
                >
                  <option value="">Select Campus</option>
                  <option v-for="campus in campuses" :key="campus.id" :value="campus.id">
                    {{ campus.text }}
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
const campuses = ref([])
const campusSelect = ref(null)
const form = ref({
  id: null,
  short_name: '',
  name: '',
  address: '',
  campus_id: null,
  is_active: 1,
})

const fetchCampuses = async () => {
  try {
    const response = await axios.get('/api/main-value-lists/get-campuses')
    campuses.value = Array.isArray(response.data) ? response.data : response.data.data
  } catch (err) {
    console.error('Failed to load campuses:', err)
    showAlert('Error', 'Failed to load campuses.', 'danger')
  }
}

const resetForm = () => {
  form.value = {
    id: null,
    short_name: '',
    name: '',
    address: '',
    campus_id: null,
    is_active: 1,
  }
}

const show = async (building = null) => {
  resetForm()
  await fetchCampuses()
  if (building) {
    form.value = {
      id: building.id,
      short_name: building.short_name ?? '',
      name: building.name ?? '',
      campus_id: building.campus_id ?? null,
      address: building.address ?? '',
      is_active: building.is_active !== undefined ? building.is_active : 1,
    }
  }
  await nextTick()
  showModal.value = true
}

const hideModal = () => {
  if (campusSelect.value) {
    destroySelect2(campusSelect.value)
  }
  showModal.value = false
}

const submitForm = async () => {
  if (isSubmitting.value) return
  isSubmitting.value = true
  try {
    const method = props.isEditing ? 'put' : 'post'
    const url = props.isEditing && form.value.id
      ? `/api/buildings/${form.value.id}`
      : '/api/buildings'

    const payload = {
      short_name: form.value.short_name?.toString().trim() ?? '',
      name: form.value.name?.toString().trim() ?? '',
      address: form.value.address?.toString().trim() ?? '',
      is_active: form.value.is_active ? 1 : 0,
      campus_id: form.value.campus_id,
    }

    await axios[method](url, payload)
    emit('submitted')
    hideModal()
    showAlert('Success', `Building ${props.isEditing ? 'updated' : 'created'} successfully.`, 'success')
  } catch (err) {
    console.error('Submit error:', err.response?.data || err)
    showAlert('Error', err.response?.data?.message || err.message || 'Failed to save building.', 'danger')
  } finally {
    isSubmitting.value = false
  }
}

// Initialize Select2 for campus selection when modal is shown
watch(showModal, async (val) => {
  if (val) {
    await nextTick()
    const $modal = window.$('#buildingModal')
    initSelect2(campusSelect.value, {
      placeholder: 'Select Campus',
      width: '100%',
      allowClear: true,
      dropdownParent: $modal
    }, v => form.value.campus_id = v)
    // Set initial value if exists
    await nextTick()
    window.$(campusSelect.value).val(form.value.campus_id).trigger('change')
  } else {
    destroySelect2(campusSelect.value)
  }
})

defineExpose({ show })

onMounted(fetchCampuses)
onUnmounted(() => {
  if (campusSelect.value) {
    destroySelect2(campusSelect.value)
  }
})
</script>