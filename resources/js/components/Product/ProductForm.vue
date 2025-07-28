<template>
  <div class="container-fluid mt-3">
    <form @submit.prevent="submitForm">
      <div class="card border mb-0">
        <!-- Header -->
        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
          <h4 class="mb-0 font-weight-bold">{{ isEditMode ? 'Edit Product' : 'Create Product' }}</h4>
          <a :href="indexRoute" class="btn btn-secondary btn-sm">
            <i class="fal fa-arrow-left"></i> Back to List
          </a>
        </div>

        <div class="card-body">
          <!-- Section 1: Basic Information -->
          <div class="border rounded p-3 mb-4">
            <h5 class="font-weight-bold mb-3 text-primary">🏷️ Product Details</h5>
            <div class="form-row">
              <div class="form-group col-md-4">
                <label for="name" class="font-weight-bold">Name <span class="text-danger">*</span></label>
                <input v-model="form.name" type="text" class="form-control" id="name" required />
              </div>
              <div class="form-group col-md-4">
                <label for="khmer_name" class="font-weight-bold">Khmer Name</label>
                <input v-model="form.khmer_name" type="text" class="form-control" id="khmer_name" />
              </div>
              <div class="form-group col-md-4">
                <label for="item_code" class="font-weight-bold">Item Code</label>
                <input
                  v-model="form.item_code"
                  type="text"
                  class="form-control"
                  id="item_code"
                  :readonly="isEditMode"
                  :disabled="isEditMode"
                />
              </div>
            </div>
            <div class="form-row">
              <div class="form-group col-md-4">
                <label for="barcode" class="font-weight-bold">Barcode Type</label>
                <select
                  ref="barcodeSelect"
                  v-model="form.barcode"
                  class="form-control"
                  id="barcode"
                >
                  <option value="">Select Barcode</option>
                  <option value="EAN13">EAN-13</option>
                  <option value="CODE128">CODE128</option>
                </select>
              </div>
              <div class="form-group col-md-4">
                <label for="unit_id" class="font-weight-bold">Unit <span class="text-danger">*</span></label>
                <select
                  ref="unitSelect"
                  v-model="form.unit_id"
                  class="form-control"
                  id="unit_id"
                  required
                >
                  <option value="">Select Unit</option>
                  <option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.name }}</option>
                </select>
              </div>
              <div class="form-group col-md-4">
                <label for="category_id" class="font-weight-bold">Main Category <span class="text-danger">*</span></label>
                <select
                  ref="categorySelect"
                  v-model="form.category_id"
                  class="form-control"
                  id="category_id"
                  required
                >
                  <option value="">Select Main Category</option>
                  <option v-for="cat in mainCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                </select>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group col-md-4">
                <label for="sub_category_id" class="font-weight-bold">Sub-Category</label>
                <select
                  ref="subCategorySelect"
                  v-model="form.sub_category_id"
                  class="form-control"
                  id="sub_category_id"
                >
                  <option value="">Select Sub-Category</option>
                  <option v-for="cat in filteredSubCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                </select>
              </div>
              <div class="form-group col-md-4">
                <label for="description" class="font-weight-bold">Description</label>
                <textarea v-model="form.description" class="form-control" id="description" rows="2"></textarea>
              </div>
              <div class="form-group col-md-4">
                <label for="import_file" class="font-weight-bold">Choose Excel File</label>
                <div class="input-group">
                  <div class="custom-file">
                    <input
                      type="file"
                      class="custom-file-input"
                      id="import_file"
                      accept=".xlsx,.xls"
                      ref="fileInput"
                      @change="handleFileUpload"
                    />
                    <label class="custom-file-label" for="import_file">
                      {{ selectedFileName || 'Choose file...' }}
                    </label>
                  </div>
                  <div class="input-group-append">
                    <button
                      type="button"
                      class="btn btn-primary"
                      @click="importFile"
                      :disabled="isImporting"
                    >
                      <span v-if="isImporting" class="spinner-border spinner-border-sm mr-1"></span>
                      <i class="fal fa-upload"></i> Import
                    </button>
                    <a
                      class="btn btn-secondary"
                      href="/sampleExcel/Product_Sample.xlsx"
                      download="Product_Sample.xlsx"
                    >
                      <i class="fal fa-file-excel"></i> Download Sample
                    </a>
                  </div>
                </div>
                <small class="form-text text-muted mt-1">
                  Supported formats: .xlsx, .xls<br />
                  Columns: <code>item_code, name, khmer_name, description, barcode, category, sub_category, unit, manage_stock, is_active, has_variants, variant_item_code, variant_estimated_price, variant_average_price, variant_description, variant_is_active, variant_attributes</code>
                </small>
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
                <label for="productImageInput" class="font-weight-bold">Image</label>
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
          </div>

          <!-- Section 2: Attribute Management -->
          <div v-if="form.has_variants" class="border rounded p-3 mb-4">
            <h5 class="font-weight-bold mb-3 text-primary">🔧 Attribute Management</h5>
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h6 class="mb-0">Attributes</h6>
              <button
                type="button"
                class="btn btn-primary btn-sm"
                @click="openNewAttributeModal"
                :disabled="isEditMode"
              >
                <i class="fal fa-plus"></i> Add New Attribute
              </button>
            </div>
            <div v-if="isAttributesLoading" class="text-center">Loading attributes...</div>
            <div v-else-if="!availableAttributes.length" class="text-center">No attributes available.</div>
            <div v-else class="row">
              <div v-for="attr in filteredAvailableAttributes" :key="attr.id" class="col-6 col-md-4 col-lg-3 mb-3">
                <div class="card border h-100">
                  <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 font-weight-bold">{{ attr.name || 'Unknown Attribute' }}</h6>
                    <button
                      type="button"
                      class="btn btn-outline-primary btn-sm"
                      @click="openAddValueModal(attr)"
                    >
                      <i class="fal fa-plus"></i> Add Value
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

          <!-- Section 3: Variants Table -->
          <div class="border rounded p-3 mb-4">
            <h5 class="font-weight-bold mb-3 text-primary">📦 Product Variants</h5>
            <div class="table-responsive">
              <table class="table table-bordered table-sm">
                <thead class="thead-light">
                  <tr>
                    <th style="min-width: 200px;">Item Code</th>
                    <th style="min-width: 120px;">Estimated Price</th>
                    <th style="min-width: 120px;">Average Price</th>
                    <th style="min-width: 200px;">Description</th>
                    <th style="min-width: 120px;">Image</th>
                    <th style="min-width: 100px;">Active</th>
                    <th style="min-width: 100px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(variant, index) in form.variants" :key="index">
                    <td>
                      <input
                        v-model="variant.item_code"
                        type="text"
                        class="form-control"
                        :id="'variant_item_code_' + index"
                        placeholder="Item Code"
                        :readonly="isEditMode"
                        :disabled="isEditMode"
                      />
                    </td>
                    <td>
                      <input
                        v-model.number="variant.estimated_price"
                        type="number"
                        class="form-control"
                        min="0"
                        step="0.01"
                        required
                      />
                    </td>
                    <td>
                      <input
                        v-model.number="variant.average_price"
                        type="number"
                        class="form-control"
                        min="0"
                        step="0.01"
                        required
                      />
                    </td>
                    <td>
                      <textarea
                        v-model="variant.description"
                        class="form-control"
                        rows="2"
                        maxlength="1000"
                      ></textarea>
                    </td>
                    <td>
                      <div class="custom-file">
                        <input
                          type="file"
                          class="custom-file-input"
                          :id="'variantImageInput_' + index"
                          @change="onVariantImageChange($event, index)"
                        />
                        <label class="custom-file-label" :for="'variantImageInput_' + index">
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
                          :id="'variant_active_' + index"
                          :true-value="1"
                          :false-value="0"
                        />
                        <label class="custom-control-label" :for="'variant_active_' + index"></label>
                      </div>
                    </td>
                    <td>
                      <button
                        type="button"
                        class="btn btn-danger btn-sm"
                        @click="removeVariant(index)"
                        :disabled="form.variants.length <= 1"
                      >
                        <i class="fal fa-trash-alt"></i> Remove
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <button
              type="button"
              class="btn btn-success btn-sm mt-2"
              @click="addVariant"
              v-if="form.has_variants"
            >
              <i class="fal fa-plus"></i> Add Variant
            </button>
          </div>

          <!-- Section 4: Submit/Cancel -->
          <div class="text-right">
            <button
              type="submit"
              class="btn btn-primary btn-sm mr-2"
              :disabled="isSubmitting || isLoading"
            >
              <span v-if="isSubmitting" class="spinner-border spinner-border-sm mr-1"></span>
              {{ isEditMode ? 'Update' : 'Create' }}
            </button>
            <a :href="indexRoute" class="btn btn-secondary btn-sm">Cancel</a>
          </div>
        </div>
      </div>
    </form>

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
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios';
import BaseModal from '@/components/Reusable/BaseModal.vue';
import { showAlert } from '@/Utils/bootbox';
import { initSelect2, destroySelect2 } from '@/Utils/select2';

// Props
const props = defineProps({
  initialData: {
    type: Object,
    default: () => ({}),
  },
});

// State
const isSubmitting = ref(false);
const isAttributesLoading = ref(false);
const isSubmittingAttribute = ref(false);
const isImporting = ref(false);
const isLoading = ref(false);
const skipCategoryWatcher = ref(false);
const mainCategories = ref([]);
const subCategories = ref([]);
const units = ref([]);
const availableAttributes = ref([]);
const selectedAttributes = ref({});
const showNewAttributeModal = ref(false);
const showAddValueModal = ref(false);
const newAttribute = ref({ name: '', values: '', ordinal: null });
const newValue = ref('');
const currentAttribute = ref(null);
const fileInput = ref(null);
const selectedFileName = ref('');
const isEditMode = ref(false);
const productId = ref(null);
const indexRoute = ref(window.route('products.index'));

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
  variants: [
    {
      item_code: '',
      estimated_price: null,
      average_price: null,
      description: '',
      image: null,
      is_active: 1,
      variant_value_ids: [],
    },
  ],
});

const barcodeSelect = ref(null);
const unitSelect = ref(null);
const categorySelect = ref(null);
const subCategorySelect = ref(null);

// Computed
const filteredSubCategories = computed(() => {
  if (!form.value.category_id) return [];
  return subCategories.value.filter(cat => cat.main_category_id === Number(form.value.category_id));
});

const filteredAvailableAttributes = computed(() => {
  if (!isEditMode.value) return availableAttributes.value;
  const existingAttributeIds = new Set();
  form.value.variants.forEach(variant => {
    if (variant.values) {
      variant.values.forEach(val => {
        if (val.attribute?.id) existingAttributeIds.add(val.attribute.id);
      });
    }
  });
  return availableAttributes.value.filter(attr => existingAttributeIds.has(attr.id));
});

// Methods
const fetchInitialData = async () => {
  try {
    const [mainCategoriesRes, subCategoriesRes, unitsRes] = await Promise.all([
      axios.get(window.route('api.main-categories.index')),
      axios.get(window.route('api.sub-categories.index')),
      axios.get(window.route('api.unit-of-measures.index')),
    ]);
    mainCategories.value = Array.isArray(mainCategoriesRes.data.data)
      ? mainCategoriesRes.data.data
      : mainCategoriesRes.data;
    subCategories.value = Array.isArray(subCategoriesRes.data.data)
      ? subCategoriesRes.data.data
      : subCategoriesRes.data;
    units.value = Array.isArray(unitsRes.data.data)
      ? unitsRes.data.data
      : unitsRes.data;
  } catch (err) {
    console.error('Fetch Initial Data Error:', {
      status: err.response?.status,
      data: err.response?.data,
      message: err.message,
    });
    showAlert('Error', 'Failed to load categories or units.', 'danger');
  }
};

const fetchAttributes = async () => {
  isAttributesLoading.value = true;
  try {
    const response = await axios.get(window.route('api.attributes-values.index'), { timeout: 5000 });
    availableAttributes.value = Array.isArray(response.data.data)
      ? response.data.data
      : response.data;
    if (!availableAttributes.value.length) {
      showAlert('Warning', 'No attributes data received from server.', 'warning');
    }
  } catch (err) {
    console.error('Fetch Attributes Error:', {
      status: err.response?.status,
      data: err.response?.data,
      message: err.message,
    });
    showAlert('Error', 'Failed to load attributes.', 'danger');
    availableAttributes.value = [];
  } finally {
    isAttributesLoading.value = false;
  }
};

const loadProduct = async () => {
  if (!isEditMode.value || !productId.value) {
    console.log('Skipping loadProduct: isEditMode or productId missing', {
      isEditMode: isEditMode.value,
      productId: productId.value,
    });
    return;
  }

  try {
    const url = window.route('api.products.edit', { product_id: productId.value });
    console.log('Loading product from:', url);
    const response = await axios.get(url, {
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      },
    });

    console.log('Load Product Response:', {
      status: response.status,
      data: response.data,
    });

    if (!response.data.success) {
      throw new Error(response.data.message || 'Unexpected response: success flag is false');
    }

    const product = response.data.data || response.data;
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
      variants: Array.isArray(product.variants) && product.variants.length
        ? product.variants.map(v => ({
            id: v.id || null,
            item_code: v.item_code || '',
            estimated_price: parseFloat(v.estimated_price) || null,
            average_price: parseFloat(v.average_price) || null,
            description: v.description || '',
            image: v.image || null,
            is_active: Number(v.is_active ?? 1),
            variant_value_ids: v.values ? v.values.map(val => val.id) : [],
            values: v.values || [],
          }))
        : [
            {
              item_code: '',
              estimated_price: null,
              average_price: null,
              description: '',
              image: null,
              is_active: 1,
              variant_value_ids: [],
            },
          ],
    };

    selectedAttributes.value = getSelectedFromVariants(form.value.variants);
    generateVariants();
    console.log('Product loaded successfully:', form.value);

    await initSelect2Dropdowns();
  } catch (err) {
    console.error('Load Product Error:', {
      status: err.response?.status,
      data: err.response?.data,
      message: err.message,
      stack: err.stack,
    });
    showAlert('Error', err.response?.data?.message || 'Failed to load product data.', 'danger');
  }
};

const handleFileUpload = (event) => {
  const file = event.target.files[0];
  const validMimeTypes = [
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-excel',
  ];
  if (file) {
    selectedFileName.value = file.name;
    if (!validMimeTypes.includes(file.type)) {
      showAlert('Error', 'Please upload a valid Excel file (.xlsx or .xls).', 'danger');
      fileInput.value.value = '';
      selectedFileName.value = '';
    }
  } else {
    selectedFileName.value = '';
  }
};

const importFile = async () => {
  if (!fileInput.value.files[0]) {
    showAlert('Error', 'Please select a file to import.', 'danger');
    return;
  }

  if (isImporting.value) return;
  isImporting.value = true;

  try {
    const formData = new FormData();
    formData.append('file', fileInput.value.files[0]);
    const response = await axios.post(window.route('api.products.import'), formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      },
    });

    if (!response.data.success) {
      throw new Error(response.data.message || 'Import failed');
    }

    const products = response.data.data || [];
    if (products.length === 0) {
      showAlert('Warning', 'No valid products found in the imported file.', 'warning');
      fileInput.value.value = '';
      selectedFileName.value = '';
      return;
    }

    const product = products[0];
    skipCategoryWatcher.value = true;
    form.value = {
      id: null,
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
      variants: Array.isArray(product.variants) && product.variants.length
        ? product.variants.map(v => ({
            id: v.id || null,
            item_code: v.item_code || '',
            estimated_price: parseFloat(v.estimated_price) || null,
            average_price: parseFloat(v.average_price) || null,
            description: v.description || '',
            image: v.image || null,
            is_active: Number(v.is_active ?? 1),
            variant_value_ids: v.variant_value_ids || [],
            values: v.variant_value_ids
              ? v.variant_value_ids.map(id => ({
                  id,
                  attribute: {
                    id: Object.keys(product.selected_attributes || {}).find(attrId =>
                      product.selected_attributes[attrId].includes(id)
                    ),
                  },
                }))
              : [],
          }))
        : [
            {
              item_code: '',
              estimated_price: null,
              average_price: null,
              description: '',
              image: null,
              is_active: 1,
              variant_value_ids: [],
            },
          ],
    };
    selectedAttributes.value = product.selected_attributes || {};
    generateVariants();

    await initSelect2Dropdowns();
    skipCategoryWatcher.value = false;
    fileInput.value.value = '';
    selectedFileName.value = '';
    showAlert('Success', 'Product data loaded into the form.', 'success');
  } catch (err) {
    console.error('Import File Error:', {
      status: err.response?.status,
      data: err.response?.data,
      message: err.message,
    });
    const errors = err.response?.data?.errors || [err.response?.data?.message || 'Failed to import product.'];
    const errorList = errors.map((error, index) => `${index + 1}. ${error}`).join('<br>');
    showAlert('Error', `Import failed:<br><br>${errorList}`, 'danger');
  } finally {
    isImporting.value = false;
  }
};

const openNewAttributeModal = () => {
  newAttribute.value = { name: '', values: '', ordinal: null };
  showNewAttributeModal.value = true;
};

const openAddValueModal = (attr) => {
  currentAttribute.value = attr;
  newValue.value = '';
  showAddValueModal.value = true;
};

const createAttribute = async () => {
  if (isSubmittingAttribute.value) return;
  isSubmittingAttribute.value = true;

  try {
    const values = newAttribute.value.values
      .split(',')
      .map(v => v.trim())
      .filter(v => v)
      .map(value => ({ value, is_active: 1 }));

    await axios.post(
      window.route('api.product-variant-attributes.store'),
      {
        name: newAttribute.value.name,
        ordinal: newAttribute.value.ordinal,
        values: values.length ? values : undefined,
        is_active: 1,
      },
      {
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
      }
    );

    await fetchAttributes();
    showNewAttributeModal.value = false;
    showAlert('Success', 'Attribute created successfully.', 'success');
  } catch (err) {
    console.error('Create Attribute Error:', {
      status: err.response?.status,
      data: err.response?.data,
      message: err.message,
    });
    const errors = err.response?.data?.errors || [err.response?.data?.message || 'Failed to create attribute.'];
    const errorList = errors.map((error, index) => `${index + 1}. ${error}`).join('<br>');
    showAlert('Error', `Failed to create attribute:<br><br>${errorList}`, 'danger');
  } finally {
    isSubmittingAttribute.value = false;
  }
};

const addValueToAttribute = async () => {
  if (isSubmittingAttribute.value || !currentAttribute.value) return;
  isSubmittingAttribute.value = true;

  try {
    await axios.post(
      window.route('api.product-variant-attributes.add-values', {
        product_variant_attribute: currentAttribute.value.id,
      }),
      {
        value: newValue.value.trim(),
        is_active: 1,
      },
      {
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
      }
    );

    await fetchAttributes();
    showAddValueModal.value = false;
    showAlert('Success', 'Attribute value added successfully.', 'success');
  } catch (err) {
    console.error('Add Value Error:', {
      status: err.response?.status,
      data: err.response?.data,
      message: err.message,
    });
    const errors = err.response?.data?.errors || [err.response?.data?.message || 'Failed to add attribute value.'];
    const errorList = errors.map((error, index) => `${index + 1}. ${error}`).join('<br>');
    showAlert('Error', `Failed to add attribute value:<br><br>${errorList}`, 'danger');
  } finally {
    isSubmittingAttribute.value = false;
  }
};

const toggleAttribute = (attrId, valId) => {
  const values = selectedAttributes.value[attrId] || [];
  const index = values.indexOf(valId);
  if (index === -1) values.push(valId);
  else values.splice(index, 1);
  selectedAttributes.value = { ...selectedAttributes.value, [attrId]: [...values] };
};

const getSelectedFromVariants = (variants) => {
  const map = {};
  variants.forEach(variant => {
    if (variant && Array.isArray(variant.values)) {
      variant.values.forEach(val => {
        const attrId = val.attribute?.id;
        const valId = val.id;
        if (attrId && valId) {
          if (!map[attrId]) map[attrId] = [];
          if (!map[attrId].includes(valId)) map[attrId].push(valId);
        }
      });
    }
  });
  return map;
};

const generateVariants = () => {
  const combinations = Object.entries(selectedAttributes.value)
    .filter(([_, values]) => values.length)
    .map(([attrId, values]) => values.map(valId => ({ attrId: Number(attrId), valId })));

  const allCombos = combinations.length
    ? combinations.reduce(
        (acc, curr) => (acc.length ? acc.flatMap(a => curr.map(c => [...a, c])) : curr.map(c => [c])),
        []
      )
    : [];

  const validVariants = Array.isArray(form.value.variants)
    ? form.value.variants.filter(v => v && typeof v === 'object')
    : [];
  const existingVariantMap = new Map(
    validVariants.map(v => [
      (v.variant_value_ids || []).sort((a, b) => a - b).join('-'),
      v,
    ])
  );

  const currentVariantMap = new Map(
    form.value.variants.map(v => [
      (v.variant_value_ids || []).sort((a, b) => a - b).join('-'),
      v,
    ])
  );

  const defaultVariant = validVariants.length === 1 && validVariants[0].variant_value_ids?.length === 0 ? validVariants[0] : null;

  form.value.variants = allCombos.length
    ? allCombos.map(combo => {
        const valIds = combo.map(({ valId }) => valId).sort((a, b) => a - b);
        const key = valIds.join('-');
        const userInput = currentVariantMap.get(key);
        const existing = existingVariantMap.get(key);
        const desc = combo
          .map(({ attrId, valId }) => {
            const attr = availableAttributes.value.find(a => a.id === attrId);
            const val = attr?.values.find(v => v.id === valId);
            return {
              ordinal: attr?.ordinal ?? 0,
              text: `${attr?.name || 'Unknown'}: ${val?.value || 'Unknown'}`,
            };
          })
          .sort((a, b) => a.ordinal - b.ordinal)
          .map(item => item.text)
          .join(', ');

        const isNewCombo = !userInput && !existing;
        return {
          id: existing?.id || null,
          item_code: userInput?.item_code ?? existing?.item_code ?? '',
          estimated_price: isNewCombo ? null : userInput?.estimated_price ?? existing?.estimated_price ?? null,
          average_price: isNewCombo ? null : userInput?.average_price ?? existing?.average_price ?? null,
          description: desc,
          image: isNewCombo
            ? form.value.image
            : userInput?.image ?? existing?.image ?? form.value.image ?? defaultVariant?.image ?? null,
          is_active: Number(
            userInput?.is_active ?? existing?.is_active ?? defaultVariant?.is_active ?? 1
          ),
          variant_value_ids: valIds,
        };
      })
    : form.value.has_variants
    ? [
        {
          item_code: '',
          estimated_price: null,
          average_price: null,
          description: '',
          image: form.value.image || null,
          is_active: 1,
          variant_value_ids: [],
        },
      ]
    : [
        {
          id: defaultVariant?.id,
          item_code: form.value.item_code || '',
          estimated_price: defaultVariant?.estimated_price ?? null,
          average_price: defaultVariant?.average_price ?? null,
          description: form.value.description || '',
          image: form.value.image || defaultVariant?.image || null,
          is_active: Number(form.value.is_active ?? defaultVariant?.is_active ?? 1),
          variant_value_ids: [],
        },
      ];

  form.value.variants.forEach((variant, index) => {
    const select = document.getElementById(`variant_item_code_${index}`);
    if (select) {
      initSelect2(select, {
        placeholder: 'Enter Item Code',
        width: '100%',
        allowClear: true,
      }, (v) => (form.value.variants[index].item_code = v));
      if (variant.item_code) {
        $(select).val(variant.item_code).trigger('change');
      }
    }
  });
};

const addVariant = async () => {
  form.value.variants.push({
    item_code: '',
    estimated_price: null,
    average_price: null,
    description: '',
    image: null,
    is_active: 1,
    variant_value_ids: [],
  });

  await nextTick();
  const newIndex = form.value.variants.length - 1;
  const select = document.getElementById(`variant_item_code_${newIndex}`);
  if (select) {
    initSelect2(select, {
      placeholder: 'Enter Item Code',
      width: '100%',
      allowClear: true,
    }, (v) => (form.value.variants[newIndex].item_code = v));
  }
};

const removeVariant = (index) => {
  if (form.value.variants.length > 1) {
    const select = document.getElementById(`variant_item_code_${index}`);
    if (select) destroySelect2(select);
    form.value.variants.splice(index, 1);
  } else {
    showAlert('Error', 'At least one variant is required.', 'danger');
  }
};

const onProductImageChange = (event) => {
  form.value.image = event.target.files[0] || null;
};

const onVariantImageChange = (event, index) => {
  form.value.variants[index].image = event.target.files[0] || null;
};

const initSelect2Dropdowns = async () => {
  await nextTick();
  const selectOptions = { width: '100%', allowClear: true };

  const selects = [
    { ref: barcodeSelect, placeholder: 'Select Barcode', bind: v => (form.value.barcode = v) },
    { ref: unitSelect, placeholder: 'Select Unit', bind: v => (form.value.unit_id = v) },
    { ref: categorySelect, placeholder: 'Select Main Category', bind: v => (form.value.category_id = v) },
    { ref: subCategorySelect, placeholder: 'Select Sub-Category', bind: v => (form.value.sub_category_id = v) },
  ];

  selects.forEach(({ ref, placeholder, bind }) => {
    if (ref.value) {
      destroySelect2(ref.value);
      initSelect2(ref.value, { ...selectOptions, placeholder }, bind);
      $(ref.value).val(form.value[ref.value.id] || '').trigger('change');
    }
  });
};

const submitForm = async () => {
  if (isSubmitting.value) return;
  isSubmitting.value = true;

  try {
    for (const [index, variant] of form.value.variants.entries()) {
      if (variant.estimated_price === null || variant.average_price === null) {
        showAlert('Error', `Estimated Price and Average Price are required for variant ${index + 1}.`, 'danger');
        isSubmitting.value = false;
        return;
      }
    }

    const formData = new FormData();
    Object.entries(form.value).forEach(([key, value]) => {
      if (key === 'variants') return;
      if (key === 'image') {
        if (value instanceof File) {
          formData.append('image', value);
        }
      } else if (typeof value === 'boolean') {
        formData.append(key, value ? '1' : '0');
      } else if (value !== null && value !== undefined) {
        formData.append(key, value);
      }
    });

    form.value.variants.forEach((variant, idx) => {
      Object.entries(variant).forEach(([key, value]) => {
        if (key === 'image' && value instanceof File) {
          formData.append(`variants[${idx}][image]`, value);
        } else if (key === 'variant_value_ids' && Array.isArray(value)) {
          value.forEach((id, i) => formData.append(`variants[${idx}][variant_value_ids][${i}]`, id));
        } else if (key === 'is_active') {
          formData.append(`variants[${idx}][${key}]`, Number(value));
        } else if (key !== 'values' && value !== null && value !== undefined) {
          formData.append(`variants[${idx}][${key}]`, value);
        }
      });
    });

    const url = isEditMode.value
      ? window.route('api.products.update', { product_id: form.value.id })
      : window.route('api.products.store');
    const method = isEditMode.value ? 'put' : 'post';

    const response = await axios[method](url, formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      },
    });

    console.log('Submit Response:', {
      status: response.status,
      data: response.data,
    });

    if (!response.data.success) {
      throw new Error(response.data.message || 'Unexpected response: success flag is false');
    }

    showAlert('Success', `Product ${isEditMode.value ? 'updated' : 'created'} successfully.`, 'success');
    window.location.href = indexRoute.value;
  } catch (err) {
    console.error('Submit Error:', {
      status: err.response?.status,
      data: err.response?.data,
      message: err.message,
      stack: err.stack,
    });
    const errors = err.response?.data?.errors || [err.response?.data?.message || 'Failed to save product.'];
    const errorList = errors.map((error, index) => `${index + 1}. ${error}`).join('<br>');
    showAlert('Error', `Failed to save product:<br><br>${errorList}`, 'danger');
  } finally {
    isSubmitting.value = false;
  }
};

// Watchers
watch(
  () => form.value.has_variants,
  (hasVariants) => {
    if (!hasVariants) {
      form.value.variants = [
        {
          item_code: form.value.item_code || '',
          estimated_price: null,
          average_price: null,
          description: form.value.description || '',
          image: form.value.image || null,
          is_active: Number(form.value.is_active ?? 1),
          variant_value_ids: [],
        },
      ];
    } else {
      generateVariants();
    }
  }
);

watch(
  () => selectedAttributes.value,
  () => {
    if (form.value.has_variants) generateVariants();
  },
  { deep: true }
);

watch(
  () => form.value.category_id,
  async (newVal) => {
    if (skipCategoryWatcher.value) return;

    const validSubCats = subCategories.value.filter(cat => cat.main_category_id === Number(newVal)).map(cat => cat.id);
    if (!validSubCats.includes(form.value.sub_category_id)) {
      form.value.sub_category_id = null;
    }

    await nextTick();
    if (subCategorySelect.value) {
      destroySelect2(subCategorySelect.value);
      initSelect2(
        subCategorySelect.value,
        { placeholder: 'Select Sub-Category', width: '100%', allowClear: true },
        v => (form.value.sub_category_id = v)
      );
      const exists = filteredSubCategories.value.some(cat => cat.id === form.value.sub_category_id);
      $(subCategorySelect.value).val(exists ? form.value.sub_category_id : '').trigger('change');
    }
  }
);

// Lifecycle
onMounted(async () => {
  isLoading.value = true;
  const currentRoute = window.route().current();
  productId.value = window.route().params.product_id || parseInt(window.location.pathname.split('/').pop());
  isEditMode.value = currentRoute === 'products.edit' && !!productId.value;

  if (isEditMode.value && props.initialData?.id) {
    form.value = {
      id: props.initialData.id || null,
      item_code: props.initialData.item_code || '',
      name: props.initialData.name || '',
      khmer_name: props.initialData.khmer_name || '',
      description: props.initialData.description || '',
      barcode: props.initialData.barcode || '',
      category_id: props.initialData.category_id || '',
      sub_category_id: props.initialData.sub_category_id || null,
      unit_id: props.initialData.unit_id || '',
      manage_stock: !!props.initialData.manage_stock,
      image: props.initialData.image || null,
      is_active: !!props.initialData.is_active,
      has_variants: !!props.initialData.has_variants,
      variants: Array.isArray(props.initialData.variants) && props.initialData.variants.length
        ? props.initialData.variants.map(v => ({
            id: v.id || null,
            item_code: v.item_code || '',
            estimated_price: parseFloat(v.estimated_price) || null,
            average_price: parseFloat(v.average_price) || null,
            description: v.description || '',
            image: v.image || null,
            is_active: Number(v.is_active ?? 1),
            variant_value_ids: v.values ? v.values.map(val => val.id) : [],
            values: v.values || [],
          }))
        : [
            {
              item_code: '',
              estimated_price: null,
              average_price: null,
              description: '',
              image: null,
              is_active: 1,
              variant_value_ids: [],
            },
          ],
    };
    selectedAttributes.value = getSelectedFromVariants(form.value.variants);
  } else if (isEditMode.value) {
    console.warn('Initial data missing in edit mode, attempting to fetch from API');
    await loadProduct();
  }

  await Promise.all([fetchInitialData(), fetchAttributes()]);

  availableAttributes.value.forEach(attr => {
    if (attr.values?.length && !selectedAttributes.value[attr.id]) {
      selectedAttributes.value[attr.id] = [];
    }
  });

  if (form.value.has_variants) generateVariants();

  await initSelect2Dropdowns();
  isLoading.value = false;
});

onUnmounted(() => {
  [barcodeSelect, unitSelect, categorySelect, subCategorySelect].forEach(select => {
    if (select.value) destroySelect2(select.value);
  });
  form.value.variants.forEach((_, index) => {
    const select = document.getElementById(`variant_item_code_${index}`);
    if (select) destroySelect2(select);
  });
});
</script>

<style scoped>
.table th,
.table td {
  min-width: 100px;
}
</style>