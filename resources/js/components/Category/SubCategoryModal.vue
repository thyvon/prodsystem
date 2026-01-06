<template>
  <BaseModal
    v-model="showModal"
    id="subCategoryModal"
    :title="isEditing ? 'Edit Sub Category' : 'Create Sub Category'"
    size="lg"
  >
    <template #body>
      <form @submit.prevent="submitForm">
        <div class="card border shadow-sm mb-0">
          <div class="card-header py-2 bg-light">
            <h6 class="mb-0 font-weight-bold">Sub Category Information</h6>
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
                <label>Main Category</label>
                <select
                  ref="mainCategorySelect"
                  v-model="form.main_category_id"
                  class="form-control"
                  required
                >
                  <option value="">Select Main Category</option>
                  <option v-for="mainCategory in mainCategories" :key="mainCategory.id" :value="mainCategory.id">
                    {{ mainCategory.text }}
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

const props = defineProps({ isEditing: Boolean })
const emit = defineEmits(['submitted'])

const showModal = ref(false)
const isSubmitting = ref(false)
const mainCategories = ref([])
const mainCategorySelect = ref(null)
const form = ref({
  id: null,
  short_name: '',
  name: '',
  khmer_name: '',
  description: '',
  main_category_id: null,
  is_active: 1,
})

const fetchMainCategories = async () => {
  try {
    const response = await axios.get('/api/main-value-lists/get-main-categories')
    mainCategories.value = Array.isArray(response.data) ? response.data : response.data.data
  } catch (err) {
    console.error('Failed to load main categories:', err)
    showAlert('Error', 'Failed to load main categories.', 'danger')
  }
}

const resetForm = () => {
  form.value = {
    id: null,
    short_name: '',
    name: '',
    khmer_name: '',
    description: '',
    main_category_id: null,
    is_active: 1,
  }
}

const show = async (subCategory = null) => {
  resetForm()
  await fetchMainCategories()
  if (subCategory) {
    form.value = {
      id: subCategory.id,
      short_name: subCategory.short_name ?? '',
      name: subCategory.name ?? '',
      khmer_name: subCategory.khmer_name ?? '',
      description: subCategory.description ?? '',
      main_category_id: subCategory.main_category_id ?? null,
      is_active: subCategory.is_active !== undefined ? subCategory.is_active : 1,
    }
  }
  await nextTick()
  showModal.value = true
}

const hideModal = () => {
  if (mainCategorySelect.value) {
    destroySelect2(mainCategorySelect.value)
  }
  showModal.value = false
}

const submitForm = async () => {
  if (isSubmitting.value) return
  isSubmitting.value = true
  try {
    const method = props.isEditing ? 'put' : 'post'
    const url = props.isEditing && form.value.id
      ? `/api/sub-categories/${form.value.id}`
      : '/api/sub-categories'

    const payload = {
      short_name: form.value.short_name?.toString().trim() ?? '',
      name: form.value.name?.toString().trim() ?? '',
      khmer_name: form.value.khmer_name?.toString().trim() ?? '',
      description: form.value.description?.toString().trim() ?? '',
      main_category_id: form.value.main_category_id,
      is_active: form.value.is_active ? 1 : 0,
    }

    await axios[method](url, payload)
    emit('submitted')
    hideModal()
    showAlert('Success', `Sub-category ${props.isEditing ? 'updated' : 'created'} successfully.`, 'success')
  } catch (err) {
    console.error('Submit error:', err.response?.data || err)
    showAlert('Error', err.response?.data?.message || err.message || 'Failed to save sub-category.', 'danger')
  } finally {
    isSubmitting.value = false
  }
}

// Initialize Select2 for main category selection when modal is shown
watch(showModal, async (val) => {
  if (val) {
    await nextTick()
    const $modal = window.$('#subCategoryModal')
    initSelect2(mainCategorySelect.value, {
      placeholder: 'Select Main Category',
      width: '100%',
      allowClear: true,
      dropdownParent: $modal
    }, v => form.value.main_category_id = v)
    // Set initial value if exists
    await nextTick()
    window.$(mainCategorySelect.value).val(form.value.main_category_id).trigger('change')
  } else {
    destroySelect2(mainCategorySelect.value)
  }
})

defineExpose({ show })

onMounted(fetchMainCategories)
onUnmounted(() => {
  if (mainCategorySelect.value) {
    destroySelect2(mainCategorySelect.value)
  }
})
</script>