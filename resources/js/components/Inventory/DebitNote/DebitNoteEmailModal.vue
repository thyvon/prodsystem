<template>
  <BaseModal
    v-model="showModal"
    id="debitNoteEmailModal"
    :title="isEditing ? 'Edit Debit Note Email' : 'Create Debit Note Email'"
    size="xl"
    :loading="isLoading"
  >
    <template #body>
      <form @submit.prevent="submitForm">
        <div class="card border shadow-sm mb-0">
          <div class="card-header py-2 bg-light">
            <h6 class="mb-0 font-weight-bold">Debit Note Email Configuration</h6>
          </div>
          <div class="card-body">
            <div class="form-row">
              <div class="form-group col-md-4">
                    <label>Campus <span class="text-danger">*</span></label>
                    <select
                    ref="campusSelect"
                    v-model="form.campus_id"
                    class="form-control"
                    required
                    >
                    <option value="">Select Campus</option>
                    <option
                        v-for="campus in campuses"
                        :key="campus.id"
                        :value="campus.id"
                    >
                        {{ campus.text }}
                    </option>
                    </select>
              </div>
              <div class="form-group col-md-4">
                <label>Department <span class="text-danger">*</span></label>
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
                    {{ department.text }}
                  </option>
                </select>
              </div>
              <div class="form-group col-md-4">
                <label>Warehouse <span class="text-danger">*</span></label>
                <select
                  ref="warehouseSelect"
                  v-model="form.warehouse_id"
                  class="form-control"
                  required
                >
                  <option value="">Select Warehouse</option>
                  <option
                    v-for="warehouse in warehouses"
                    :key="warehouse.id"
                    :value="warehouse.id"
                  >
                    {{ warehouse.text }}
                  </option>
                </select>
              </div>
            </div>

            <div class="form-row mb-4">
              <div class="form-group col-md-4">
                <label>Receipient <span class="text-danger">*</span></label>
                <input type="text" class="form-control" v-model="form.receiver_name" required />
              </div>
            </div>

            <div class="form-row">
              <div class="form-group col-md-6">
                <label>Send To Emails <span class="text-danger">*</span></label>
                <textarea
                  v-model="form.send_to_email"
                  class="form-control"
                  placeholder="Separate multiple emails with commas"
                  rows="2"
                  required
                ></textarea>
              </div>

              <div class="form-group col-md-6">
                <label>CC Emails</label>
                <textarea
                  v-model="form.cc_to_email"
                  class="form-control"
                  placeholder="Separate multiple emails with commas"
                  rows="2"
                ></textarea>
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
const isLoading = ref(false)

// Form
const form = ref({
  id: null,
  campus_id: null,
  department_id: null,
  warehouse_id: null,
  receiver_name: '',
  send_to_email: '',
  cc_to_email: ''
})

// Select lists
const campuses = ref([])
const departments = ref([])
const warehouses = ref([])
const campusSelect = ref(null)
const departmentSelect = ref(null)
const warehouseSelect = ref(null)

// Fetch lists

const fetchCampuses = async () => {
    try {
        const res = await axios.get('/api/main-value-lists/get-campuses')
        campuses.value = Array.isArray(res.data) ? res.data : res.data.data
    } catch (err){
        console.error(err)
        showAlert('Error', 'Failed to load campuses', 'danger')
    }
}

const fetchDepartments = async () => {
  try {
    const res = await axios.get('/api/main-value-lists/get-departments')
    departments.value = Array.isArray(res.data) ? res.data : res.data.data
  } catch (err) {
    console.error(err)
    showAlert('Error', 'Failed to load departments', 'danger')
  }
}

const fetchWarehouses = async () => {
  try {
    const res = await axios.get('/api/main-value-lists/get-warehouses')
    warehouses.value = Array.isArray(res.data) ? res.data : res.data.data
  } catch (err) {
    console.error(err)
    showAlert('Error', 'Failed to load warehouses', 'danger')
  }
}

// Reset form
const resetForm = () => {
  form.value = {
    id: null,
    campus_id: null,
    department_id: null,
    warehouse_id: null,
    receiver_name: '',
    send_to_email: '',
    cc_to_email: ''
  }
}

// Show modal
const show = async (row = null) => {
  resetForm()
  isLoading.value = true
  await fetchDepartments()
  await fetchWarehouses()

  if (row) {
    form.value = {
      id: row.id,
      campus_id: row.campus_id,
      department_id: row.department_id,
      warehouse_id: row.warehouse_id,
      receiver_name: row.receiver_name,
      send_to_email: (row.send_to_email || []).join(', '),
      cc_to_email: (row.cc_to_email || []).join(', ')
    }
  }

  isLoading.value = false
  await nextTick()
  showModal.value = true
}

// Hide modal
const hideModal = () => {
  destroySelect2(campusSelect.value)
  destroySelect2(departmentSelect.value)
  destroySelect2(warehouseSelect.value)
  showModal.value = false
}

// Submit
const submitForm = async () => {
  if (isSubmitting.value) return
  isSubmitting.value = true

  try {
    const payload = {
      campus_id: form.value.campus_id,
      department_id: form.value.department_id,
      warehouse_id: form.value.warehouse_id,
      receiver_name: form.value.receiver_name,
      send_to_email: form.value.send_to_email
        .split(',')
        .map(e => e.trim())
        .filter(e => e),
      cc_to_email: form.value.cc_to_email
        .split(',')
        .map(e => e.trim())
        .filter(e => e)
    }

    const method = props.isEditing ? 'put' : 'post'
    const url = props.isEditing && form.value.id
      ? `/api/inventory/debit-note/emails/${form.value.id}/update`
      : '/api/inventory/debit-note/emails/store'

    await axios[method](url, payload)
    emit('submitted')
    hideModal()
    showAlert('Success', `Debit Note Email ${props.isEditing ? 'updated' : 'created'} successfully.`, 'success')
  } catch (err) {
    console.error(err)
    showAlert('Error', err.response?.data?.message || err.message || 'Failed to save.', 'danger')
  } finally {
    isSubmitting.value = false
  }
}

// Init Select2
watch(showModal, async (val) => {
  if (val) {
    await nextTick()
    const $modal = window.$('#debitNoteEmailModal')
    initSelect2(campusSelect.value, { placeholder: 'Select Campus', width: '100%', dropdownParent: $modal }, v => form.value.campus_id = v)
    initSelect2(departmentSelect.value, { placeholder: 'Select Department', width: '100%', dropdownParent: $modal }, v => form.value.department_id = v)
    initSelect2(warehouseSelect.value, { placeholder: 'Select Warehouse', width: '100%', dropdownParent: $modal }, v => form.value.warehouse_id = v)

    await nextTick()
    window.$(campusSelect.value).val(form.value.campus_id).trigger('change')
    window.$(departmentSelect.value).val(form.value.department_id).trigger('change')
    window.$(warehouseSelect.value).val(form.value.warehouse_id).trigger('change')
  } else {
    destroySelect2(campusSelect.value)
    destroySelect2(departmentSelect.value)
    destroySelect2(warehouseSelect.value)
  }
})

defineExpose({ show })

onMounted(async () => {
  await fetchCampuses()
  await fetchDepartments()
  await fetchWarehouses()
})

onUnmounted(() => {
  destroySelect2(campusSelect.value)
  destroySelect2(departmentSelect.value)
  destroySelect2(warehouseSelect.value)
})
</script>
