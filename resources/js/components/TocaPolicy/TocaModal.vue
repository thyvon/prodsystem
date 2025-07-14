<template>
  <BaseModal
    v-model="showModal"
    id="tocaModal"
    :title="isEditing ? 'Edit Toca' : 'Create Toca'"
    size="lg"
  >
    <template #body>
      <form @submit.prevent="submitForm">
        <div class="card border shadow-sm mb-0">
          <div class="card-header py-2 bg-light">
            <h6 class="mb-0 font-weight-bold">Toca Information</h6>
          </div>
          <div class="card-body">
            <div class="form-row">
              <div class="form-group col-md-6">
                <label>Toca Name</label>
                <textarea
                  v-model="form.name"
                  class="form-control"
                  required
                ></textarea>
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
import BaseModal from '@/components/reusable/BaseModal.vue'
import { showAlert } from '@/utils/bootbox'

const props = defineProps({ isEditing: Boolean })
const emit = defineEmits(['submitted'])

const showModal = ref(false)
const isSubmitting = ref(false)
const tocaPolicy = ref([])
const form = ref({
  id: null,
  short_name: '',
  name: '',
  is_active: 1,
})

const fetchTocaPolicy = async () => {
  try {
    const response = await axios.get('/api/toca-policies')
    const tocaList = Array.isArray(response.data) ? response.data : response.data.data
    tocaPolicy.value = tocaList
  } catch (err) {
    console.error('Failed to load TOCA policies:', err)
  }
}

const resetForm = () => {
  form.value = {
    id: null,
    short_name: '',
    name: '',
    is_active: 1,
  }
}

const show = async (tocaPolicy = null) => {
  resetForm()
  await fetchTocaPolicy()
  if (tocaPolicy) {
    form.value = {
      id: tocaPolicy.id,
      short_name: tocaPolicy.short_name ?? '',
      name: tocaPolicy.name ?? '',
      is_active: tocaPolicy.is_active !== undefined ? tocaPolicy.is_active : 1,
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
      ? `/api/toca-policies/${form.value.id}`
      : '/api/toca-policies'

    const payload = {
      short_name: form.value.short_name?.toString().trim() ?? '',
      name: form.value.name?.toString().trim() ?? '',
      is_active: form.value.is_active ? 1 : 0,
    }

    await axios[method](url, payload)
    emit('submitted')
    hideModal()
    showAlert('Success', `Toca Policy ${props.isEditing ? 'updated' : 'created'} successfully.`, 'success')
  } catch (err) {
    console.error('Submit error:', err.response?.data || err)
    showAlert('Error', err.response?.data?.message || err.message || 'Failed to save toca policy.', 'danger')
  } finally {
    isSubmitting.value = false
  }
}

defineExpose({ show })

onMounted(fetchTocaPolicy)
</script>