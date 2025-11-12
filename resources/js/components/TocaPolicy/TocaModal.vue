<template>
  <BaseModal
    v-model="showModal"
    id="tocaModal"
    :title="isEditing ? 'Edit Toca' : 'Create Toca'"
    size="lg"
  >
    <template #body>
      <form @submit.prevent="submitForm">
        <!-- TOCA Information Card -->
        <div class="card border shadow-sm mb-3">
          <div class="card-header py-2 bg-light">
            <h6 class="mb-0 font-weight-bold">Toca Information</h6>
          </div>
          <div class="card-body">
            <div class="form-row">
              <div class="form-group col-md-6">
                <label>Toca Name</label>
                <textarea v-model="form.name" class="form-control" rows="1" required></textarea>
              </div>
              <div class="form-group col-md-6">
                <label>Short Name</label>
                <input v-model="form.short_name" type="text" class="form-control" required />
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

        <!-- TOCA Value Items Table -->
        <div class="card border shadow-sm">
          <div class="card-header py-2 bg-light">
            <h6 class="mb-0 font-weight-bold">TOCA Value Items</h6>
          </div>
          <div class="card-body p-0">
            <table class="table table-striped mb-0">
              <thead class="thead-light">
                <tr>
                  <th>#</th>
                  <th>Min Amount</th>
                  <th>Max Amount</th>
                  <th>Active</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, index) in valueItems" :key="item.id || index">
                  <td>{{ index + 1 }}</td>
                  <td>
                    <input
                      v-model="item.min_amount"
                      type="number"
                      class="form-control"
                      placeholder="Min Amount"
                      required
                    />
                  </td>
                  <td>
                    <input
                      v-model="item.max_amount"
                      type="number"
                      class="form-control"
                      placeholder="Max Amount"
                      required
                    />
                  </td>
                  <td>
                    <div class="custom-control custom-checkbox">
                      <input
                        type="checkbox"
                        class="custom-control-input"
                        :id="`itemActive_${index}`"
                        v-model="item.is_active"
                        :true-value="1"
                        :false-value="0"
                      />
                      <label class="custom-control-label" :for="`itemActive_${index}`"></label>
                    </div>
                  </td>
                  <td>
                    <button type="button" class="btn btn-sm btn-danger" @click="removeItem(index)">
                      Remove
                    </button>
                  </td>
                </tr>
                <tr v-if="valueItems.length === 0">
                  <td colspan="5" class="text-center text-muted">No value items added yet.</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="card-footer text-right">
            <button type="button" class="btn btn-sm btn-primary" @click="addItem">
              + Add Item
            </button>
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
const tocaPolicy = ref([])
const valueItems = ref([])

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
  valueItems.value = []
}

const show = async (tocaData = null) => {
  resetForm()
  await fetchTocaPolicy()
  if (tocaData) {
    form.value = {
      id: tocaData.id,
      short_name: tocaData.short_name ?? '',
      name: tocaData.name ?? '',
      is_active: tocaData.is_active !== undefined ? tocaData.is_active : 1,
    }
    valueItems.value = Array.isArray(tocaData.items) ? tocaData.items : []
  }
  await nextTick()
  showModal.value = true
}

const hideModal = () => {
  showModal.value = false
}

const addItem = () => valueItems.value.push({ id: null, min_amount: '', max_amount: '', is_active: 1 })
const removeItem = (index) => valueItems.value.splice(index, 1)

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
      items: valueItems.value.map(item => ({
        id: item.id,
        min_amount: item.min_amount?.toString().trim() ?? '',
        max_amount: item.max_amount?.toString().trim() ?? '',
        is_active: item.is_active ? 1 : 0
      }))
    }

    await axios[method](url, payload)
    emit('submitted')
    hideModal()
    showAlert('Success', `Toca ${props.isEditing ? 'updated' : 'created'} successfully.`, 'success')
  } catch (err) {
    console.error('Submit error:', err.response?.data || err)
    showAlert('Error', err.response?.data?.message || err.message || 'Failed to save Toca.', 'danger')
  } finally {
    isSubmitting.value = false
  }
}

defineExpose({ show })
onMounted(fetchTocaPolicy)
</script>
