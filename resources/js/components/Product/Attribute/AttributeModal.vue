<template>
  <BaseModal
    v-model="showModal"
    id="divisionModal"
    :title="isEditing ? 'Edit Product Variant Attribute' : 'Create Product Variant Attribute'"
    size="lg"
  >
    <template #body>
      <form @submit.prevent="submitForm">
        <div class="card border shadow-sm mb-4">
          <div class="card-header py-2 bg-light">
            <h6 class="mb-0 font-weight-bold">Attribute Information</h6>
          </div>
          <div class="card-body">
            <div class="form-row">
              <div class="form-group col-md-8">
                <label>Attribute Name</label>
                <input
                  v-model="form.name"
                  type="text"
                  class="form-control"
                  :class="{ 'is-invalid': errors['name'] }"
                  required
                />
                <div v-if="errors['name']" class="invalid-feedback">
                  {{ errors['name'].join(', ') }}
                </div>
              </div>
              <div class="form-group col-md-4">
                <label>Ordinal</label>
                <input
                  v-model.number="form.ordinal"
                  type="number"
                  class="form-control"
                  :class="{ 'is-invalid': errors['ordinal'] }"
                  min="0"
                />
                <div v-if="errors['ordinal']" class="invalid-feedback">
                  {{ errors['ordinal'].join(', ') }}
                </div>
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
                    @change="syncValuesActiveState"
                  />
                  <label class="custom-control-label" for="isActive">Active</label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="card border shadow-sm mb-0">
          <div class="card-header py-2 bg-light">
            <h6 class="mb-0 font-weight-bold">Attribute Values</h6>
          </div>
          <div class="card-body">
            <div class="form-group">
              <label>Add New Value</label>
              <div class="input-group mb-3">
                <input
                  v-model="newValue.value"
                  type="text"
                  class="form-control"
                  :class="{ 'is-invalid': errors['values.new'] }"
                  placeholder="Enter attribute value"
                  @keyup.enter="addNewValue"
                />
                <div class="input-group-append">
                  <button
                    class="btn btn-outline-primary"
                    type="button"
                    @click="addNewValue"
                    :disabled="!newValue.value.trim()"
                  >
                    Add
                  </button>
                </div>
                <div v-if="errors['values.new']" class="invalid-feedback d-block">
                  {{ errors['values.new'].join(', ') }}
                </div>
              </div>
            </div>
            <div v-if="form.values.length" class="table-responsive">
              <table class="table table-bordered">
                <thead>
                  <tr>
                    <th>Value</th>
                    <th>Active</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(value, index) in form.values" :key="value.id || `new-${index}`">
                    <td>
                      <input
                        v-model="value.value"
                        type="text"
                        class="form-control"
                        :class="{ 'is-invalid': errors[`values.${index}.value`] }"
                        required
                      />
                      <div v-if="errors[`values.${index}.value`]" class="invalid-feedback">
                        {{ errors[`values.${index}.value`].join(', ') }}
                      </div>
                    </td>
                    <td>
                    <div class="custom-control custom-checkbox">
                        <input
                        type="checkbox"
                        class="custom-control-input"
                        :id="`isActiveValue-${index}`"
                        v-model="value.is_active"
                        :true-value="1"
                        :false-value="0"
                        :disabled="form.is_active === 0"
                        />
                        <label class="custom-control-label" :for="`isActiveValue-${index}`"></label>
                    </div>
                    </td>
                    <td>
                      <button
                        type="button"
                        class="btn btn-sm btn-danger"
                        @click="removeValue(index)"
                      >
                        <i class="fal fa-trash"></i>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-else class="text-muted">
              No values added yet.
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
import { ref, nextTick, onMounted, watch } from 'vue'
import axios from 'axios'
import { showAlert } from '@/Utils/bootbox'
import BaseModal from '@/components/Reusable/BaseModal.vue'

const props = defineProps({ isEditing: Boolean })
const emit = defineEmits(['submitted'])

const showModal = ref(false)
const isSubmitting = ref(false)
const attributes = ref([])
const newValue = ref({ value: '', is_active: 1 })
const form = ref({
  id: null,
  name: '',
  ordinal: null,
  is_active: 1,
  values: [],
})
const errors = ref({})

const fetchAttributes = async () => {
  try {
    const response = await axios.get('/api/product-variant-attributes')
    const attributeList = Array.isArray(response.data) ? response.data : response.data.data
    attributes.value = attributeList
  } catch (err) {
    console.error('Failed to load attributes:', err)
  }
}

const resetForm = () => {
  form.value = {
    id: null,
    name: '',
    ordinal: null,
    is_active: 1,
    values: [],
  }
  newValue.value = { value: '', is_active: 1 }
  errors.value = {}
}

const addNewValue = () => {
  if (!newValue.value.value.trim()) return
  form.value.values.push({
    id: null,
    value: newValue.value.value.trim(),
    is_active: form.value.is_active,
  })
  newValue.value = { value: '', is_active: form.value.is_active }
  errors.value['values.new'] = null
}

const removeValue = (index) => {
  form.value.values.splice(index, 1)
  delete errors.value[`values.${index}.value`]
}

const syncValuesActiveState = () => {
  form.value.values = form.value.values.map(value => ({
    ...value,
    is_active: form.value.is_active,
  }))
}

const show = async (attribute = null) => {
  resetForm()
  await fetchAttributes()
  if (attribute) {
    form.value = {
      id: attribute.id,
      name: attribute.name ?? '',
      ordinal: attribute.ordinal ?? null,
      is_active: attribute.is_active !== undefined ? attribute.is_active : 1,
      values: attribute.values?.map(value => ({
        id: value.id || null,
        value: value.value,
        is_active: value.is_active ? 1 : 0,
      })) || [],
    }
  }
  syncValuesActiveState()
  await nextTick()
  showModal.value = true
}

const hideModal = () => {
  showModal.value = false
  errors.value = {}
}

const submitForm = async () => {
  if (isSubmitting.value) return
  isSubmitting.value = true
  errors.value = {}
  try {
    const method = props.isEditing ? 'put' : 'post'
    const url = props.isEditing && form.value.id
      ? `/api/product-variant-attributes/${form.value.id}`
      : '/api/product-variant-attributes'

    const payload = {
      name: form.value.name?.toString().trim() ?? '',
      ordinal: form.value.ordinal ? Number(form.value.ordinal) : null,
      is_active: form.value.is_active ? 1 : 0,
      values: form.value.values.map(value => ({
        id: value.id || undefined,
        value: value.value,
        is_active: value.is_active,
      })),
    }

    await axios[method](url, payload)
    emit('submitted')
    hideModal()
    showAlert('Success', `Product Variant Attribute ${props.isEditing ? 'updated' : 'created'} successfully.`, 'success')
  } catch (err) {
    console.error('Submit error:', err.response?.data || err)
    if (err.response?.data?.errors) {
      errors.value = err.response.data.errors
    } else {
      showAlert('Error', err.response?.data?.message || err.message || 'Failed to save Product Variant Attribute.', 'danger')
    }
  } finally {
    isSubmitting.value = false
  }
}

watch(() => form.value.is_active, (newValue) => {
  syncValuesActiveState()
})

defineExpose({ show })

onMounted(fetchAttributes)
</script>