<template>
  <BaseModal
    v-model="showModal"
    id="divisionModal"
    :title="isEditing ? 'Edit Division' : 'Create Division'"
    size="lg"
  >
    <template #body>
      <form @submit.prevent="submitForm">
        <div class="card border shadow-sm mb-0">
          <div class="card-header py-2 bg-light">
            <h6 class="mb-0 font-weight-bold">Main Category Information</h6>
          </div>
          <div class="card-body">
            <div class="form-row">
              <div class="form-group col-md-4">
                <label>Main Category Name</label>
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
              <div class="form-group col-md-12">
                <label>Description</label>
                <textarea
                  v-model="form.description"
                  class="form-control"
                  rows="3"
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
const mainCategories = ref([])
const form = ref({
  id: null,
  short_name: '',
  name: '',
  khmer_name: '',
  description: '',
  is_active: 1,
})

const fetchMainCategories = async () => {
  try {
    const response = await axios.get('/api/main-categories')
    const mainCategoryList = Array.isArray(response.data) ? response.data : response.data.data
    mainCategories.value = mainCategoryList
  } catch (err) {
    console.error('Failed to load main categories:', err)
  }
}

const resetForm = () => {
  form.value = {
    id: null,
    short_name: '',
    name: '',
    khmer_name: '',
    description: '',
    is_active: 1,
  }
}

const show = async (mainCategory = null) => {
  resetForm()
  await fetchMainCategories()
  if (mainCategory) {
    form.value = {
      id: mainCategory.id,
      short_name: mainCategory.short_name ?? '',
      name: mainCategory.name ?? '',
      khmer_name: mainCategory.khmer_name ?? '',
      description: mainCategory.description ?? '',
      is_active: mainCategory.is_active !== undefined ? mainCategory.is_active : 1,
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
      ? `/api/main-categories/${form.value.id}`
      : '/api/main-categories'

    const payload = {
      short_name: form.value.short_name?.toString().trim() ?? '',
      name: form.value.name?.toString().trim() ?? '',
      khmer_name: form.value.khmer_name?.toString().trim() ?? '',
      description: form.value.description?.toString().trim() ?? '',
      is_active: form.value.is_active ? 1 : 0,
    }

    await axios[method](url, payload)
    emit('submitted')
    hideModal()
    showAlert('Success', `Main Category ${props.isEditing ? 'updated' : 'created'} successfully.`, 'success')
  } catch (err) {
    console.error('Submit error:', err.response?.data || err)
    showAlert('Error', err.response?.data?.message || err.message || 'Failed to save Main Category.', 'danger')
  } finally {
    isSubmitting.value = false
  }
}

defineExpose({ show })

onMounted(fetchMainCategories)
</script>