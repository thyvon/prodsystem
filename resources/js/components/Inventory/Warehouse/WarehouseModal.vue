<template>
  <BaseModal
    v-model="showModal"
    id="warehouseModal"
    :title="isEditing ? 'Edit Warehouse' : 'Create Warehouse'"
    size="lg"
    :loading="isLoading"
  >
    <template #body>
      <form @submit.prevent="submitForm">
        <div class="card border shadow-sm mb-0">
          <div class="card-header py-2 bg-light">
            <h6 class="mb-0 font-weight-bold">Warehouse Information</h6>
          </div>
          <div class="card-body">
            <div class="form-row">
              <!-- <div v-if="isEditing" class="form-group col-md-6">
                <label>Warehouse Code</label>
                <input
                  v-model="form.code"
                  type="text"
                  class="form-control"
                  required
                  :disabled="isEditing"
                />
              </div> -->
              <div class="form-group col-md-6">
                <label>Warehouse Name <span class="text-danger">*</span></label>
                <input
                  v-model="form.name"
                  type="text"
                  class="form-control"
                  required
                />
              </div>
              <div class="form-group col-md-6">
                <label>Khmer Name <span class="text-danger">*</span></label>
                <input
                  v-model="form.khmer_name"
                  type="text"
                  class="form-control"
                />
              </div>
            </div>
            <div class="form-row">
              <div class="form-group col-md-6">
                <label>Address <span class="text-danger">*</span></label>
                <textarea
                  v-model="form.address"
                  class="form-control"
                  rows="2"
                  required
                ></textarea>
              </div>
              <div class="form-group col-md-6">
                <label>Khmer Address</label>
                <textarea
                  v-model="form.address_khmer"
                  class="form-control"
                  rows="2"
                  required
                ></textarea>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group col-md-6">
                <label>Building <span class="text-danger">*</span></label>
                <select
                  ref="buildingSelect"
                  v-model="form.building_id"
                  class="form-control"
                  required
                >
                  <option value="">Select Building</option>
                  <option v-for="building in buildings" :key="building.id" :value="building.id">
                    {{ building.name }} ({{ building.short_name }})
                  </option>
                </select>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group col-md-12">
                <label>Description</label>
                <textarea
                  v-model="form.description"
                  class="form-control"
                  rows="4"
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
const buildings = ref([])
const buildingSelect = ref(null)
const isLoading = ref(false)
const form = ref({
  id: null,
  code: '',
  name: '',
  khmer_name: '',
  address: '',
  address_khmer: '',
  description: '',
  building_id: null,
  is_active: 1,
})

const fetchBuildings = async () => {
  try {
    const response = await axios.get('/api/buildings')
    buildings.value = Array.isArray(response.data) ? response.data : response.data.data
  } catch (err) {
    console.error('Failed to load buildings:', err)
    showAlert('Error', 'Failed to load buildings.', 'danger')
  }
}

const resetForm = () => {
  form.value = {
    id: null,
    code: '',
    name: '',
    khmer_name: '',
    address: '',
    address_khmer: '',
    description: '',
    building_id: null,
    is_active: 1,
  }
}

const show = async (warehouse = null) => {
  resetForm()
  isLoading.value = true
  await fetchBuildings()
  if (warehouse) {
    form.value = {
      id: warehouse.id,
      code: warehouse.code ?? '',
      name: warehouse.name ?? '',
      khmer_name: warehouse.khmer_name ?? '',
      address: warehouse.address ?? '',
      address_khmer: warehouse.address_khmer ?? '',
      description: warehouse.description ?? '',
      building_id: warehouse.building_id ?? null,
      is_active: warehouse.is_active !== undefined ? warehouse.is_active : 1,
    }
  }
  isLoading.value = false
  await nextTick()
  showModal.value = true
}

const hideModal = () => {
  if (buildingSelect.value) {
    destroySelect2(buildingSelect.value)
  }
  showModal.value = false
}

const submitForm = async () => {
  if (isSubmitting.value) return
  isSubmitting.value = true
  try {
    const method = props.isEditing ? 'put' : 'post'
    const url = props.isEditing && form.value.id
      ? `/api/inventory/warehouses/${form.value.id}`
      : '/api/inventory/warehouses'

    const payload = {
      name: form.value.name?.toString().trim() ?? '',
      khmer_name: form.value.khmer_name?.toString().trim() ?? '',
      address: form.value.address?.toString().trim() ?? '',
      address_khmer: form.value.address_khmer?.toString().trim() ?? '',
      description: form.value.description?.toString().trim() ?? '',
      building_id: form.value.building_id,
      is_active: form.value.is_active ? 1 : 0,
      ...(props.isEditing && { code: form.value.code?.toString().trim() ?? '' }),
    }

    await axios[method](url, payload)
    emit('submitted')
    hideModal()
    showAlert('Success', `Warehouse ${props.isEditing ? 'updated' : 'created'} successfully.`, 'success')
  } catch (err) {
    console.error('Submit error:', err.response?.data || err)
    showAlert('Error', err.response?.data?.message || err.message || 'Failed to save warehouse.', 'danger')
  } finally {
    isSubmitting.value = false
  }
}

// Initialize Select2 for building selection when modal is shown
watch(showModal, async (val) => {
  if (val) {
    await nextTick()
    const $modal = window.$('#warehouseModal')
    initSelect2(buildingSelect.value, {
      placeholder: 'Select Building',
      width: '100%',
      allowClear: true,
      dropdownParent: $modal
    }, v => form.value.building_id = v)
    // Set initial value if exists
    await nextTick()
    window.$(buildingSelect.value).val(form.value.building_id).trigger('change')
  } else {
    destroySelect2(buildingSelect.value)
  }
})

defineExpose({ show })

onMounted(fetchBuildings)
onUnmounted(() => {
  if (buildingSelect.value) {
    destroySelect2(buildingSelect.value)
  }
})
</script>