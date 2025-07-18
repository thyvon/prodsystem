<template>
  <BaseModal
    v-model="showModal"
    id="productModal"
    :title="isEditing ? 'Edit Product' : 'Create Product'"
    size="xl"
  >
    <template #body>
      <form @submit.prevent="submitForm">
        <!-- Basic Information -->
        <div class="card border shadow-sm mb-4">
          <div class="card-header py-2 bg-light">
            <h6 class="mb-0 font-weight-bold">Basic Information</h6>
          </div>
          <div class="card-body">
            <div class="form-row">
              <div class="form-group col-md-6">
                <label>Name <span class="text-danger">*</span></label>
                <input v-model="form.name" type="text" class="form-control" required />
              </div>
              <div class="form-group col-md-6">
                <label>Khmer Name</label>
                <input v-model="form.khmer_name" type="text" class="form-control" />
              </div>
            </div>
            <div class="form-row">
              <div class="form-group col-md-3">
                <label>Item Code</label>
                <input v-model="form.item_code" type="text" class="form-control" />
              </div>
              <div class="form-group col-md-3">
                <label>Barcode</label>
                <select ref="barcodeSelect" v-model="form.barcode" class="form-control">
                  <option value="">No Barcode</option>
                  <option value="EAN13">EAN-13</option>
                  <option value="CODE128">Code 128</option>
                </select>
              </div>
              <div class="form-group col-md-3">
                <label>Unit <span class="text-danger">*</span></label>
                <select ref="unitSelect" v-model="form.unit_id" class="form-control" required>
                  <option value="">Select Unit</option>
                  <option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.name }}</option>
                </select>
              </div>
              <div class="form-group col-md-3">
                <label>Main Category <span class="text-danger">*</span></label>
                <select ref="categorySelect" v-model="form.category_id" class="form-control" required>
                  <option value="">Select Main Category</option>
                  <option v-for="cat in mainCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                </select>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group col-md-3">
                <label>Sub-Category</label>
                <select ref="subCategorySelect" v-model="form.sub_category_id" class="form-control">
                  <option value="">Select Sub-Category</option>
                  <option v-for="cat in filteredSubCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                </select>
              </div>
              <div class="form-group col-md-9">
                <label>Description</label>
                <textarea v-model="form.description" class="form-control" rows="1"></textarea>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group col-md-3">
                <div class="custom-control custom-checkbox mt-4">
                  <input
                    type="checkbox"
                    class="custom-control-input"
                    id="manageStock"
                    v-model="form.manage_stock"
                  />
                  <label class="custom-control-label" for="manageStock">Manage Stock</label>
                </div>
              </div>
              <div class="form-group col-md-3">
                <div class="custom-control custom-checkbox mt-4">
                  <input
                    type="checkbox"
                    class="custom-control-input"
                    id="isActive"
                    v-model="form.is_active"
                  />
                  <label class="custom-control-label" for="isActive">Active</label>
                </div>
              </div>
              <div class="form-group col-md-3">
                <div class="custom-control custom-checkbox mt-4">
                  <input
                    type="checkbox"
                    class="custom-control-input"
                    id="hasVariants"
                    v-model="form.has_variants"
                  />
                  <label class="custom-control-label" for="hasVariants">Has Variants</label>
                </div>
              </div>
              <div class="form-group col-md-3">
                <label>Image</label>
                <div class="custom-file">
                  <input
                    type="file"
                    class="custom-file-input"
                    id="productImageInput"
                    @change="onProductImageChange"
                  />
                  <label class="custom-file-label" for="productImageInput">
                    {{ form.image && typeof form.image === 'string' ? 'Change Image' : 'Choose Image' }}
                  </label>
                </div>
                <img
                  v-if="form.image && typeof form.image === 'string'"
                  :src="`/storage/${form.image}`"
                  alt="Product Image"
                  class="mt-1"
                  style="max-width: 100%; max-height: 80px;"
                />
              </div>
            </div>
            <!-- Price Fields for Non-Variant Products -->
            <!-- <div v-if="!form.has_variants" class="form-row">
              <div class="form-group col-md-6">
                <label>Estimated Price <span class="text-danger">*</span></label>
                <input
                  v-model.number="form.estimated_price"
                  type="number"
                  step="0.01"
                  min="0"
                  class="form-control"
                  required
                  placeholder="Enter estimated price"
                />
              </div>
              <div class="form-group col-md-6">
                <label>Average Price <span class="text-danger">*</span></label>
                <input
                  v-model.number="form.average_price"
                  type="number"
                  step="0.01"
                  min="0"
                  class="form-control"
                  required
                  placeholder="Enter average price"
                />
              </div>
            </div> -->
          </div>
        </div>

        <!-- Attribute Management -->
        <div v-if="form.has_variants" class="card border shadow-sm mb-4">
          <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
            <h6 class="mb-0 font-weight-bold">Attribute Management</h6>
            <button
              type="button"
              class="btn btn-sm btn-primary"
              @click="openNewAttributeModal"
            >
              Add New Attribute
            </button>
          </div>
          <div class="card-body">
            <div v-if="isAttributesLoading" class="text-center">Loading attributes...</div>
            <div v-else-if="!availableAttributes.length" class="text-center">No attributes available.</div>
            <div v-else class="row">
              <div v-for="attr in availableAttributes" :key="attr.id" class="col-6 col-md-4 col-lg-3 mb-3">
                <div class="card border h-100">
                  <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 font-weight-bold">{{ attr.name || 'Unknown Attribute' }}</h6>
                    <button
                      type="button"
                      class="btn btn-sm btn-secondary"
                      @click="openAddValueModal(attr)"
                    >
                      Add Value
                    </button>
                  </div>
                  <div class="card-body py-2 px-3">
                    <div v-if="!attr.values?.length">No values for this attribute.</div>
                    <div v-for="val in attr.values" :key="val.id" class="custom-control custom-checkbox">
                      <input
                        type="checkbox"
                        class="custom-control-input"
                        :id="`attr-${attr.id}-val-${val.id}`"
                        :checked="selectedAttributes[attr.id]?.includes(val.id)"
                        @change="toggleAttribute(attr.id, val.id)"
                      />
                      <label class="custom-control-label" :for="`attr-${attr.id}-val-${val.id}`">
                        {{ val.value || 'Unknown Value' }}
                      </label>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Variants Table -->
        <div class="card shadow-sm mb-4">
          <div
            :class="['card-header py-2', form.has_variants ? 'bg-secondary-50' : 'bg-success-50']"
          >
            <h6 class="mb-0">Variant Products</h6>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-bordered table-hover table-sm">
                <thead class="thead-light">
                  <tr>
                    <th style="min-width: 160px;">Variant Description</th>
                    <th style="min-width: 100px;">Item Code</th>
                    <th style="min-width: 100px;">Estimated Price <span class="text-danger">*</span></th>
                    <th style="min-width: 100px;">Average Price <span class="text-danger">*</span></th>
                    <th style="min-width: 160px;">Description</th>
                    <th style="min-width: 120px;">Image</th>
                    <th style="min-width: 70px;">Active</th>
                    <th style="min-width: 70px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(variant, index) in generatedVariants" :key="index">
                    <td class="align-middle">{{ variant.description }}</td>
                    <td>
                      <input
                        v-model="variant.item_code"
                        type="text"
                        class="form-control form-control-sm"
                        placeholder="Item Code"
                        required
                      />
                    </td>
                    <td>
                      <input
                        v-model.number="variant.estimated_price"
                        type="number"
                        step="0.01"
                        min="0"
                        class="form-control form-control-sm"
                        placeholder="Estimated Price"
                        required
                      />
                    </td>
                    <td>
                      <input
                        v-model.number="variant.average_price"
                        type="number"
                        step="0.01"
                        min="0"
                        class="form-control form-control-sm"
                        placeholder="Average Price"
                        required
                      />
                    </td>
                    <td>
                      <input
                        v-model="variant.description"
                        type="text"
                        class="form-control form-control-sm"
                        placeholder="Description"
                      />
                    </td>
                    <td>
                      <div class="custom-file">
                        <input
                          type="file"
                          class="custom-file-input"
                          :id="`variantImageInput-${index}`"
                          @change="onVariantImageChange($event, index)"
                        />
                        <label
                          class="custom-file-label"
                          :for="`variantImageInput-${index}`"
                        >
                          {{ variant.image && typeof variant.image === 'string' ? 'Change Image' : 'Choose Image' }}
                        </label>
                      </div>
                      <img
                        v-if="variant.image && typeof variant.image === 'string'"
                        :src="`/storage/${variant.image}`"
                        alt="Variant Image"
                        class="mt-1"
                        style="max-width: 100%; max-height: 40px;"
                      />
                    </td>
                    <td class="text-center">
                      <div class="custom-control custom-checkbox">
                        <input
                          v-model="variant.is_active"
                          type="checkbox"
                          class="custom-control-input"
                          :id="`variant-active-${index}`"
                          :true-value="1"
                          :false-value="0"
                        />
                        <label
                          class="custom-control-label"
                          :for="`variant-active-${index}`"
                        ></label>
                      </div>
                    </td>
                    <td class="text-center">
                      <button
                        v-if="generatedVariants.length > 1"
                        type="button"
                        class="btn btn-sm btn-danger"
                        @click="removeVariant(index)"
                        title="Remove this variant"
                      >
                        <i class="fal fa-trash"></i>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- New Attribute Modal -->
        <BaseModal
          v-model="showNewAttributeModal"
          id="newAttributeModal"
          title="Add New Attribute"
          size="md"
        >
          <template #body>
            <div class="form-group">
              <label>Attribute Name <span class="text-danger">*</span></label>
              <input
                v-model="newAttribute.name"
                type="text"
                class="form-control"
                placeholder="Enter attribute name"
                required
              />
            </div>
            <div class="form-group">
              <label>Ordinal (optional)</label>
              <input
                v-model.number="newAttribute.ordinal"
                type="number"
                min="0"
                class="form-control"
                placeholder="Enter ordinal value"
              />
            </div>
            <div class="form-group">
              <label>Attribute Values (comma-separated)</label>
              <textarea
                v-model="newAttribute.values"
                class="form-control"
                rows="3"
                placeholder="Enter values separated by commas (e.g., Small, Medium, Large)"
              ></textarea>
            </div>
          </template>
          <template #footer>
            <button type="button" class="btn btn-secondary" @click="showNewAttributeModal = false">Cancel</button>
            <button
              type="button"
              class="btn btn-primary"
              :disabled="isSubmittingAttribute || !newAttribute.name"
              @click="createAttribute"
            >
              <span v-if="isSubmittingAttribute" class="spinner-border spinner-border-sm mr-1"></span>
              Create Attribute
            </button>
          </template>
        </BaseModal>

        <!-- Add Value to Attribute Modal -->
        <BaseModal
          v-model="showAddValueModal"
          id="addValueModal"
          title="Add Value to Attribute"
          size="md"
        >
          <template #body>
            <div class="form-group">
              <label>Attribute</label>
              <input
                type="text"
                class="form-control"
                :value="currentAttribute?.name || ''"
                disabled
              />
            </div>
            <div class="form-group">
              <label>New Value <span class="text-danger">*</span></label>
              <input
                v-model="newValue"
                type="text"
                class="form-control"
                placeholder="Enter new value"
                required
              />
            </div>
          </template>
          <template #footer>
            <button type="button" class="btn btn-secondary" @click="showAddValueModal = false">Cancel</button>
            <button
              type="button"
              class="btn btn-primary"
              :disabled="isSubmittingAttribute || !newValue"
              @click="addValueToAttribute"
            >
              <span v-if="isSubmittingAttribute" class="spinner-border spinner-border-sm mr-1"></span>
              Add Value
            </button>
          </template>
        </BaseModal>
      </form>
    </template>

    <template #footer>
      <button type="button" class="btn btn-secondary" @click="hideModal">Cancel</button>
      <button
        type="submit"
        class="btn btn-primary"
        :disabled="isSubmitting"
        @click="submitForm"
      >
        <span v-if="isSubmitting" class="spinner-border spinner-border-sm mr-1"></span>
        {{ isEditing ? 'Update' : 'Create' }}
      </button>
    </template>
  </BaseModal>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import axios from 'axios'
import BaseModal from '@/components/Reusable/BaseModal.vue'
import { showAlert } from '@/Utils/bootbox'
import { initSelect2, destroySelect2 } from '@/Utils/select2'

// Props and Emits
const props = defineProps({
  isEditing: Boolean,
  currentProduct: Object,
})
const emit = defineEmits(['submitted'])

// State
const showModal = ref(false)
const isSubmitting = ref(false)
const isAttributesLoading = ref(false)
const isSubmittingAttribute = ref(false)
const skipCategoryWatcher = ref(false)
const mainCategories = ref([])
const subCategories = ref([])
const units = ref([])
const availableAttributes = ref([])
const selectedAttributes = ref({})
const generatedVariants = ref([])
const showNewAttributeModal = ref(false)
const showAddValueModal = ref(false)
const newAttribute = ref({ name: '', values: '', ordinal: null })
const newValue = ref('')
const currentAttribute = ref(null)

const form = ref({
  id: null,
  item_code: '',
  name: '',
  khmer_name: '',
  description: '',
  barcode: '',
  category_id: '',
  sub_category_id: null,
  unit_id: '',
  manage_stock: true,
  image: null,
  is_active: true,
  has_variants: false,
  estimated_price: null,
  average_price: null,
  variants: [],
})

const barcodeSelect = ref(null)
const unitSelect = ref(null)
const categorySelect = ref(null)
const subCategorySelect = ref(null)

// Computed
const filteredSubCategories = computed(() => {
  if (!form.value.category_id) return []
  return subCategories.value.filter(cat => cat.main_category_id === Number(form.value.category_id))
})

// Methods
const loadInitialData = async () => {
  try {
    const [mainCategoriesRes, subCategoriesRes, unitsRes] = await Promise.all([
      axios.get('/api/main-categories'),
      axios.get('/api/sub-categories'),
      axios.get('/api/unit-of-measures'),
    ])
    mainCategories.value = mainCategoriesRes.data.data || mainCategoriesRes.data || []
    subCategories.value = subCategoriesRes.data.data || subCategoriesRes.data || []
    units.value = unitsRes.data.data || unitsRes.data || []
  } catch (error) {
    showAlert('Error', 'Failed to load categories or units.', 'danger')
  }
}

const loadAttributes = async () => {
  isAttributesLoading.value = true
  try {
    const response = await axios.get('/api/attributes-values', { timeout: 5000 })
    availableAttributes.value = Array.isArray(response.data.data) ? response.data.data : Array.isArray(response.data) ? response.data : []
    if (!availableAttributes.value.length) {
      showAlert('Warning', 'No attributes data received from server.', 'warning')
    }
  } catch (error) {
    showAlert('Error', 'Failed to load attributes.', 'danger')
    availableAttributes.value = []
  } finally {
    isAttributesLoading.value = false
  }
}

const resetForm = () => {
  form.value = {
    id: null,
    item_code: '',
    name: '',
    khmer_name: '',
    description: '',
    barcode: '',
    category_id: '',
    sub_category_id: null,
    unit_id: '',
    manage_stock: true,
    image: null,
    is_active: true,
    has_variants: false,
    estimated_price: null,
    average_price: null,
    variants: [],
  }
  selectedAttributes.value = {}
  generatedVariants.value = [createEmptyVariant()]
  newAttribute.value = { name: '', values: '', ordinal: null }
  newValue.value = ''
  currentAttribute.value = null
}

const createEmptyVariant = () => ({
  description: '',
  item_code: '',
  estimated_price: null,
  average_price: null,
  image: null,
  is_active: 1,
  variant_value_ids: [],
})

const show = async (product = null) => {
  resetForm()
  await Promise.all([loadInitialData(), loadAttributes()])

  availableAttributes.value.forEach(attr => {
    if (attr.values?.length && !selectedAttributes.value[attr.id]) {
      selectedAttributes.value[attr.id] = []
    }
  })
  if (product) {
    skipCategoryWatcher.value = true
    form.value = {
      id: product.id || null,
      item_code: product.item_code || '',
      name: product.name || '',
      khmer_name: product.khmer_name || '',
      description: product.description || '',
      barcode: product.barcode || '',
      category_id: product.category_id || '',
      sub_category_id: product.sub_category_id || null,
      unit_id: product.unit_id || '',
      manage_stock: !!product.manage_stock,
      image: product.image || null,
      is_active: !!product.is_active,
      has_variants: !!product.has_variants,
      estimated_price: product.variants?.[0]?.estimated_price || null,
      average_price: product.variants?.[0]?.average_price || null,
      variants: Array.isArray(product.variants)
        ? product.variants.filter(v => v && typeof v === 'object' && Array.isArray(v.values)).map(v => ({
            id: v.id,
            description: v.description || '',
            item_code: v.item_code || '',
            estimated_price: v.estimated_price ?? null,
            average_price: v.average_price ?? null,
            image: v.image || null,
            is_active: Number(v.is_active ?? 1),
            values: v.values || [],
          }))
        : [],
    }
    selectedAttributes.value = getSelectedFromVariants(form.value.variants)
    generateVariants()
    await nextTick()
    skipCategoryWatcher.value = false
  }

  showModal.value = true
  await initSelect2Dropdowns()
}

const hideModal = () => {
  showModal.value = false
  ;[barcodeSelect, unitSelect, categorySelect, subCategorySelect].forEach(select => {
    destroySelect2(select.value)
  })
}

const openNewAttributeModal = () => {
  newAttribute.value = { name: '', values: '', ordinal: null }
  showNewAttributeModal.value = true
}

const openAddValueModal = (attr) => {
  currentAttribute.value = attr
  newValue.value = ''
  showAddValueModal.value = true
}

const createAttribute = async () => {
  if (isSubmittingAttribute.value) return
  isSubmittingAttribute.value = true

  try {
    const values = newAttribute.value.values
      .split(',')
      .map(v => v.trim())
      .filter(v => v)
      .map(value => ({ value, is_active: 1 }))

    await axios.post('/api/product-variant-attributes', {
      name: newAttribute.value.name,
      ordinal: newAttribute.value.ordinal,
      values: values.length ? values : undefined,
      is_active: 1,
    })

    await loadAttributes()
    showNewAttributeModal.value = false
    showAlert('Success', 'Attribute created successfully.', 'success')
  } catch (error) {
    const message = error.response?.status === 422
      ? Object.values(error.response.data.errors).flat().join(', ')
      : error.response?.data?.message || 'Failed to create attribute.'
    showAlert('Error', message, 'danger')
  } finally {
    isSubmittingAttribute.value = false
  }
}

const addValueToAttribute = async () => {
  if (isSubmittingAttribute.value || !currentAttribute.value) return
  isSubmittingAttribute.value = true

  try {
    await axios.post(`/api/product-variant-attributes/${currentAttribute.value.id}/values`, {
      value: newValue.value.trim(),
      is_active: 1,
    })

    await loadAttributes()
    showAddValueModal.value = false
    showAlert('Success', 'Attribute value added successfully.', 'success')
  } catch (error) {
    const message = error.response?.status === 422
      ? Object.values(error.response.data.errors).flat().join(', ')
      : error.response?.data?.message || 'Failed to add attribute value.'
    showAlert('Error', message, 'danger')
  } finally {
    isSubmittingAttribute.value = false
  }
}

const toggleAttribute = (attrId, valId) => {
  const values = selectedAttributes.value[attrId] || []
  const index = values.indexOf(valId)
  if (index === -1) values.push(valId)
  else values.splice(index, 1)
  selectedAttributes.value = { ...selectedAttributes.value, [attrId]: [...values] }
}

const getSelectedFromVariants = (variants) => {
  const map = {}
  variants.forEach(variant => {
    if (variant && Array.isArray(variant.values)) {
      variant.values.forEach(val => {
        const attrId = val.attribute?.id
        const valId = val.id
        if (attrId && valId) {
          if (!map[attrId]) map[attrId] = []
          if (!map[attrId].includes(valId)) map[attrId].push(valId)
        }
      })
    }
  })
  return map
}

const generateVariants = () => {
  const combinations = Object.entries(selectedAttributes.value)
    .filter(([_, values]) => values.length)
    .map(([attrId, values]) => values.map(valId => ({ attrId: Number(attrId), valId })))

  const allCombos = combinations.length
    ? combinations.reduce((acc, curr) =>
        acc.length ? acc.flatMap(a => curr.map(c => [...a, c])) : curr.map(c => [c]),
        []
      )
    : []

  const validVariants = Array.isArray(form.value.variants)
    ? form.value.variants.filter(v => v && typeof v === 'object' && Array.isArray(v.values))
    : []
  const existingVariantMap = new Map(
    validVariants.map(v => [
      v.values.map(val => val.id).sort((a, b) => a - b).join('-'),
      v,
    ])
  )

  const currentVariantMap = new Map(
    generatedVariants.value.map(v => [
      (v.variant_value_ids || []).sort((a, b) => a - b).join('-'),
      v,
    ])
  )

  const defaultVariant = validVariants.length === 1 && validVariants[0].values.length === 0
    ? validVariants[0]
    : null

  const lastUserInput = generatedVariants.value[generatedVariants.value.length - 1]

  generatedVariants.value = allCombos.length
    ? allCombos.map(combo => {
        const valIds = combo.map(({ valId }) => valId).sort((a, b) => a - b)
        const key = valIds.join('-')
        const userInput = currentVariantMap.get(key)
        const existing = existingVariantMap.get(key)
        const desc = combo
          .map(({ attrId, valId }) => {
            const attr = availableAttributes.value.find(a => a.id === attrId)
            const val = attr?.values.find(v => v.id === valId)
            return {
              ordinal: attr?.ordinal ?? 0,
              text: `${attr?.name || 'Unknown'}: ${val?.value || 'Unknown'}`
            }
          })
          .sort((a, b) => a.ordinal - b.ordinal)
          .map(item => item.text)
          .join(', ')

        const isNewCombo = !userInput && !existing
        return {
          id: existing?.id,
          description: desc,
          item_code: userInput?.item_code ?? existing?.item_code ?? '',
          estimated_price: isNewCombo
            ? null
            : (userInput?.estimated_price ?? existing?.estimated_price ?? form.value.estimated_price ?? null),
          average_price: isNewCombo
            ? null
            : (userInput?.average_price ?? existing?.average_price ?? form.value.average_price ?? null),
          image: isNewCombo ? null : (userInput?.image ?? existing?.image ?? defaultVariant?.image ?? lastUserInput?.image ?? null),
          is_active: Number(userInput?.is_active ?? existing?.is_active ?? defaultVariant?.is_active ?? lastUserInput?.is_active ?? 1),
          variant_value_ids: valIds,
        }
      })
    : (form.value.has_variants
        ? [createEmptyVariant()]
        : [{
            id: defaultVariant?.id,
            description: form.value.description || '',
            item_code: form.value.item_code || '',
            estimated_price: form.value.estimated_price ?? defaultVariant?.estimated_price ?? null,
            average_price: form.value.average_price ?? defaultVariant?.average_price ?? null,
            image: form.value.image || defaultVariant?.image || null,
            is_active: Number(form.value.is_active ?? defaultVariant?.is_active ?? 1),
            variant_value_ids: [],
          }])
}

const removeVariant = (index) => {
  if (generatedVariants.value.length > 1) {
    generatedVariants.value.splice(index, 1)
  }
}

const onProductImageChange = (event) => {
  form.value.image = event.target.files[0] || null
}

const onVariantImageChange = (event, index) => {
  generatedVariants.value[index].image = event.target.files[0] || null
}

const initSelect2Dropdowns = async () => {
  await nextTick()
  const $modal = window.$('#productModal')
  const selectOptions = { width: '100%', allowClear: true, dropdownParent: $modal }

  const selects = [
    { ref: barcodeSelect, placeholder: 'Select Barcode', bind: v => (form.value.barcode = v) },
    { ref: unitSelect, placeholder: 'Select Unit', bind: v => (form.value.unit_id = v) },
    { ref: categorySelect, placeholder: 'Select Main Category', bind: v => (form.value.category_id = v) },
    { ref: subCategorySelect, placeholder: 'Select Sub-Category', bind: v => (form.value.sub_category_id = v) },
  ]

  selects.forEach(({ ref, placeholder, bind }) => {
    destroySelect2(ref.value)
    initSelect2(ref.value, { ...selectOptions, placeholder }, bind)
  })

  await nextTick()
  window.$(categorySelect.value).val(form.value.category_id || '').trigger('change')
  await nextTick()
  const exists = filteredSubCategories.value.some(cat => cat.id == form.value.sub_category_id)
  window.$(subCategorySelect.value).val(exists ? form.value.sub_category_id : '').trigger('change')
}

const submitForm = async () => {
  if (isSubmitting.value) return
  isSubmitting.value = true

  try {
    // Validate prices for non-variant products
    // if (!form.value.has_variants && (form.value.estimated_price === null || form.value.average_price === null)) {
    //   showAlert('Error', 'Estimated Price and Average Price are required for non-variant products.', 'danger')
    //   isSubmitting.value = false
    //   return
    // }

    // Validate variant prices
    if (form.value.has_variants) {
      for (const [index, variant] of generatedVariants.value.entries()) {
        if (variant.estimated_price === null || variant.average_price === null) {
          showAlert('Error', `Estimated Price and Average Price are required for variant ${index + 1}.`, 'danger')
          isSubmitting.value = false
          return
        }
      }
    }

    const url = props.isEditing ? `/api/products/${form.value.id}` : '/api/products'
    const formData = new FormData()

    // Append main form fields
    Object.entries(form.value).forEach(([key, value]) => {
      if (key === 'variants') return
      if (key === 'image' && value instanceof File) {
        formData.append('image', value)
      } else if (typeof value === 'boolean') {
        formData.append(key, value ? '1' : '0')
      } else if (value !== null && value !== undefined) {
        formData.append(key, value)
      }
    })

    // Append variants
    generatedVariants.value.forEach((variant, idx) => {
      Object.entries(variant).forEach(([key, value]) => {
        if (key === 'image' && value instanceof File) {
          formData.append(`variants[${idx}][image]`, value)
        } else if (key === 'variant_value_ids' && Array.isArray(value)) {
          value.forEach((id, i) => formData.append(`variants[${idx}][variant_value_ids][${i}]`, id))
        } else if (key === 'is_active') {
          formData.append(`variants[${idx}][${key}]`, Number(value))
        } else if (value !== null && value !== undefined) {
          formData.append(`variants[${idx}][${key}]`, value)
        }
      })
    })

    if (props.isEditing) formData.append('_method', 'PUT')

    await axios.post(url, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    emit('submitted')
    hideModal()
    showAlert('Success', `Product ${props.isEditing ? 'updated' : 'created'} successfully.`, 'success')
  } catch (error) {
    const message = error.response?.status === 422
      ? Object.values(error.response.data.errors).flat().join(', ')
      : error.response?.data?.message || 'Failed to save product.'
    showAlert('Error', message, 'danger')
  } finally {
    isSubmitting.value = false
  }
}

// Watchers
watch(() => form.value.has_variants, (hasVariants) => {
  if (!hasVariants) {
    // Ensure a single variant with root-level prices for non-variant products
    generatedVariants.value = [{
      description: form.value.description || '',
      item_code: form.value.item_code || '',
      estimated_price: form.value.estimated_price ?? null,
      average_price: form.value.average_price ?? null,
      image: form.value.image || null,
      is_active: Number(form.value.is_active ?? 1),
      variant_value_ids: [],
    }]
  } else {
    generateVariants()
  }
})

watch(
  () => selectedAttributes.value,
  () => {
    if (form.value.has_variants) generateVariants()
  },
  { deep: true }
)

watch(
  () => form.value.category_id,
  async (newVal, oldVal) => {
    if (skipCategoryWatcher.value) return

    const validSubCats = subCategories.value.filter(cat => cat.main_category_id === Number(newVal)).map(cat => cat.id)
    if (!validSubCats.includes(form.value.sub_category_id)) {
      form.value.sub_category_id = null
    }

    await nextTick()
    destroySelect2(subCategorySelect.value)
    initSelect2(
      subCategorySelect.value,
      { placeholder: 'Select Sub-Category', width: '100%', allowClear: true, dropdownParent: window.$('#productModal') },
      v => (form.value.sub_category_id = v)
    )
    const exists = filteredSubCategories.value.some(cat => cat.id == form.value.sub_category_id)
    window.$(subCategorySelect.value).val(exists ? form.value.sub_category_id : '').trigger('change')
  }
)

watch(showModal, async (isVisible) => {
  if (isVisible) await initSelect2Dropdowns()
  else hideModal()
})

// Lifecycle
onMounted(() => {
  resetForm()
})

defineExpose({ show })
</script>

<style scoped>
.table th,
.table td {
  min-width: 70px;
}
</style>