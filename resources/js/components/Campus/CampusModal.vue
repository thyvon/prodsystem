<template>
  <BaseModal
    v-model="showModal"
    id="campusModal"
    :title="isEditing ? 'Edit Campus' : 'Create Campus'"
    size="lg"
  >
    <template #body>
      <form @submit.prevent="submitForm">
        <div class="card border shadow-sm mb-0">
          <div class="card-header py-2 bg-light">
            <h6 class="mb-0 font-weight-bold">Campus Information</h6>
          </div>
          <div class="card-body">
            <div class="form-row">
              <div class="form-group col-md-6">
                <label>Campus Name</label>
                <input
                  v-model="form.name"
                  type="text"
                  class="form-control"
                  required
                />
              </div>
              <div class="form-group col-md-6">
                <label>Campus Code</label>
                <input
                  v-model="form.code"
                  type="text"
                  class="form-control"
                  required
                />
              </div>
            </div>
            <div class="form-row">
              <div class="form-group col-md-6">
                <label>Short Name</label>
                <input
                  v-model="form.short_name"
                  type="text"
                  class="form-control"
                  required
                />
              </div>
              <div class="form-group col-md-6">
                <label>Address</label>
                <input
                  v-model="form.address"
                  type="text"
                  class="form-control"
                  required
                />
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
                  <label class="custom-control-label" for="isActive"
                    >Active</label
                  >
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
import { ref, nextTick, onMounted } from 'vue'
import axios from 'axios'
import BaseModal from '@/components/Reusable/BaseModal.vue'
import { showAlert } from '@/Utils/bootbox'

const props = defineProps({ isEditing: Boolean })
const emit = defineEmits(['submitted'])

const showModal = ref(false)
const isSubmitting = ref(false)
const campuses = ref([])
const form = ref({
  id: null,
  code: '',
  short_name: '',
  name: '',
  address: '',
  is_active: 1,
})

const fetchCampuses = async () => {
  try {
    const response = await axios.get('/api/campuses')
    const campusList = Array.isArray(response.data) ? response.data : response.data.data
    campuses.value = campusList
  } catch (err) {
    console.error('Failed to load campuses:', err)
  }
}

const resetForm = () => {
  form.value = {
    id: null,
    code: '',
    short_name: '',
    name: '',
    address: '',
    is_active: 1,
  }
}

const show = async (campus = null) => {
  resetForm()
  await fetchCampuses()
  if (campus) {
    form.value = {
      id: campus.id,
      code: campus.code ?? '',
      short_name: campus.short_name ?? '',
      name: campus.name ?? '',
      address: campus.address ?? '',
      is_active: campus.is_active !== undefined ? campus.is_active : 1,
    }
  }
  await nextTick()
  showModal.value = true
}

const hideModal = () => {
  showModal.value = false
}

const submitForm = async () => {
  if (isSubmitting.value) return
  isSubmitting.value = true
  try {
    const method = props.isEditing ? 'put' : 'post'
    const url = props.isEditing && form.value.id
      ? `/api/campuses/${form.value.id}`
      : '/api/campuses'

    const payload = {
      code: form.value.code?.toString().trim() ?? '',
      short_name: form.value.short_name?.toString().trim() ?? '',
      name: form.value.name?.toString().trim() ?? '',
      address: form.value.address?.toString().trim() ?? '',
      is_active: form.value.is_active ? 1 : 0,
    }

    await axios[method](url, payload)
    emit('submitted')
    hideModal()
    showAlert('Success', `Campus ${props.isEditing ? 'updated' : 'created'} successfully.`, 'success')
  } catch (err) {
    console.error('Submit error:', err.response?.data || err)
    showAlert('Error', err.response?.data?.message || err.message || 'Failed to save campus.', 'danger')
  } finally {
    isSubmitting.value = false
  }
}

defineExpose({ show })

onMounted(fetchCampuses)
</script>