<template>
  <div class="container-fluid">
    <form @submit.prevent="submitForm" enctype="multipart/form-data">
      <div class="card mb-0">
        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
          <h4 class="mb-0 font-weight-bold">
            {{ isEditMode ? '✏️ Edit' : '➕ Create' }} Purchase Request
          </h4>
          <button type="button" class="btn btn-outline-primary btn-sm" @click="navigateToList">
            <i class="fal fa-backward"></i> Back
          </button>
        </div>

        <div class="card-body">
          <!-- ROW 1: Requester + PR Info -->
          <div class="row">
            <div class="col-md-6">
              <div class="border rounded p-3 mb-4" style="max-height: 300px; overflow-y: auto;">
                <h5 class="font-weight-bold mb-3 text-primary">👤 Requester Info</h5>
                <div v-for="(value, label) in requester" :key="label" class="row mb-2">
                  <div class="col-4 font-weight-bold text-muted">{{ label }}:</div>
                  <div class="col-8 border-bottom py-1">{{ value || 'N/A' }}</div>
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <div class="border rounded p-3 mb-4">
                <h5 class="font-weight-bold mb-3 text-primary">📋 PR Information</h5>
                <div class="form-row">
                  <div class="form-group col-md-6">
                    <label class="font-weight-bold">📅 Deadline</label>
                    <input v-model="form.deadline_date" class="form-control datepicker" data-field="deadline_date" />
                  </div>
                  <div class="form-group col-md-6">
                    <label class="font-weight-bold">🚨 Urgent</label>
                    <div class="custom-control custom-switch">
                      <input type="checkbox" class="custom-control-input" id="isUrgent" v-model="form.is_urgent" />
                      <label class="custom-control-label" for="isUrgent">
                        <span :class="form.is_urgent ? 'text-danger' : 'text-muted'">
                          {{ form.is_urgent ? 'YES' : 'NO' }}
                        </span>
                      </label>
                    </div>
                  </div>
                </div>
                <div class="form-group">
                  <label class="font-weight-bold">🎯 Purpose <span class="text-danger">*</span></label>
                  <textarea v-model="form.purpose" class="form-control" rows="2" placeholder="Enter purpose..." required />
                </div>
                <div class="form-group">
                  <label class="font-weight-bold">📎 Attachment</label>
                  <div class="input-group">
                    <input type="file" class="d-none" ref="attachmentInput" @change="onFileChange" multiple accept=".pdf,.doc,.docx,.jpg,.png" />
                    <button type="button" class="btn btn-outline-secondary flex-fill" @click="$refs.attachmentInput.click()">
                      <i class="fal fa-file-upload"></i> {{ fileLabel }}
                    </button>
                  </div>
                  <div v-if="existingFileUrls.length" class="mt-2">
                    <small class="text-muted">Existing Files:</small>
                    <a v-for="(file, i) in existingFileUrls" :key="i" :href="file" target="_blank" class="btn btn-sm btn-outline-info mr-1 mb-1">
                      📄 File {{ i + 1 }}
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- ROW 2: Import + Items -->
          <div class="border rounded p-3 mb-4">
            <div class="form-row mb-4">
              <div class="form-group col-md-4">
                <label class="font-weight-bold">📥 Import Items</label>
                <div class="input-group">
                  <input type="file" class="d-none" ref="fileInput" @change="onImportFile" accept=".xlsx,.xls,.csv" />
                  <button type="button" class="btn btn-outline-secondary flex-fill" @click="$refs.fileInput.click()">
                    <i class="fal fa-file-upload"></i> {{ selectedFileName || 'Choose Excel/CSV...' }}
                  </button>
                  <button type="button" class="btn btn-primary ml-2" @click="importFile" :disabled="isImporting || !selectedFileName">
                    <span v-if="isImporting" class="spinner-border spinner-border-sm mr-1"></span> Import
                  </button>
                  <a class="btn btn-success ml-2" href="/sampleExcel/purchase_request_items_sample.xlsx" download>
                    <i class="fal fa-file-excel"></i>
                  </a>
                </div>
              </div>
              <div class="form-group col-md-8">
                <label class="font-weight-bold">➕ Add Product</label>
                <button type="button" class="btn btn-primary btn-block" data-toggle="modal" data-target="#productModal" :disabled="isLoadingProducts">
                  <span v-if="isLoadingProducts" class="spinner-border spinner-border-sm mr-2"></span>
                  <i class="fal fa-plus"></i> Select Product ({{ products.length }})
                </button>
              </div>
            </div>
            <h5 class="font-weight-bold mb-3 text-primary">
              📦 Items ({{ form.items.length }}) <span v-if="totalAmount" class="badge badge-primary ml-2">${{ totalAmount.toLocaleString('en-US', { minimumFractionDigits: 2 }) }}</span>
            </h5>
            <div class="table-responsive" style="max-height: 400px;">
              <table class="table table-bordered table-sm table-hover">
                <thead class="thead-light">
                  <tr>
                    <th style="min-width: 80px;">Code</th>
                    <th style="min-width: 200px;">Description</th>
                    <th style="min-width: 70px;">Qty</th>
                    <th style="min-width: 90px;">Price</th>
                    <th style="min-width: 80px;">Currency</th>
                    <th style="min-width: 80px;">Ex. Rate</th>
                    <th style="min-width: 120px;">Remarks</th>
                    <th style="min-width: 100px;">Campus</th>
                    <th style="min-width: 90px;">Dept</th>
                    <th style="min-width: 90px;">Division</th>
                    <th style="min-width: 80px;">Budget</th>
                    <th style="min-width: 60px;">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, index) in form.items" :key="index">
                    <td>{{ item.product_code }}</td>
                    <td>{{ item.product_description }}</td>
                    <td><input v-model.number="item.quantity" type="number" min="0.01" step="0.01" class="form-control form-control-sm" required /></td>
                    <td><input v-model.number="item.unit_price" type="number" min="0" step="0.01" class="form-control form-control-sm" required /></td>
                    <td>
                      <select v-model="item.currency" class="form-control form-control-sm">
                        <option value="">Select</option>
                        <option value="USD">USD</option>
                        <option value="KHR">KHR</option>
                      </select>
                    </td>
                    <td><input v-model.number="item.exchange_rate" type="number" min="0" step="0.01" class="form-control form-control-sm" /></td>
                    <td><textarea v-model="item.description" class="form-control form-control-sm" rows="1" /></td>
                    <td>
                      <select v-model="item.campus_id" class="form-control form-control-sm">
                        <option v-for="campus in campuses" :key="campus.id" :value="campus.id">{{ campus.name }}</option>
                      </select>
                    </td>
                    <td>
                      <select v-model="item.department_id" class="form-control form-control-sm">
                        <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                      </select>
                    </td>
                    <td>
                      <select v-model="item.division_id" class="form-control form-control-sm">
                        <option v-for="division in divisions" :key="division.id" :value="division.id">{{ division.name }}</option>
                      </select>
                    </td>
                    <td>
                      <select v-model="item.budget_code_id" class="form-control form-control-sm">
                        <option v-for="budget in budgetCodes" :key="budget.id" :value="budget.id">{{ budget.code }}</option>
                      </select>
                    </td>
                    <td class="text-center">
                      <button @click="removeItem(index)" class="btn btn-danger btn-sm"><i class="fal fa-trash"></i></button>
                    </td>
                  </tr>
                  <tr v-if="!form.items.length"><td colspan="12" class="text-center text-muted py-4">No items added</td></tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- ROW 3: Approvals -->
          <div class="border rounded p-3 mb-4">
            <h5 class="font-weight-bold mb-3 text-primary">✅ Approvals ({{ form.approvals.length }})</h5>
            <div class="table-responsive">
              <table class="table table-bordered table-sm">
                <thead class="thead-light">
                  <tr>
                    <th style="min-width: 100px;">Type</th>
                    <th style="min-width: 200px;">User</th>
                    <th style="min-width: 80px;">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(approval, index) in form.approvals" :key="index">
                    <td>
                      <select class="form-control approval-type-select" :data-index="index" v-model="approval.request_type" @change="initUserSelect(index)">
                        <option value="">Select</option>
                        <option value="initial">Initial</option>
                        <option value="approve">Approve</option>
                        <option value="check">Check</option>
                        <option value="verify">Verify</option>
                      </select>
                    </td>
                    <td>
                      <select class="form-control user-select" :data-index="index" v-model="approval.user_id" :disabled="!approval.request_type || isLoadingUsers[index]"></select>
                    </td>
                    <td class="text-center">
                      <button @click="removeApproval(index)" class="btn btn-danger btn-sm"><i class="fal fa-trash"></i></button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <button @click="addApproval" class="btn btn-outline-primary btn-sm mt-2"><i class="fal fa-plus"></i> Add Approval</button>
          </div>

          <!-- SUBMIT BUTTONS -->
          <div class="text-right">
            <button type="button" @click="navigateToList" class="btn btn-secondary mr-2"><i class="fal fa-times"></i> Cancel</button>
            <button type="submit" :disabled="!isFormValid" class="btn btn-primary">
              <span v-if="isSubmitting" class="spinner-border spinner-border-sm mr-2"></span>
              {{ isEditMode ? 'Update' : 'Create' }} PR
            </button>
          </div>
        </div>
      </div>
    </form>

    <!-- PRODUCT MODAL -->
    <div class="modal fade" id="productModal">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title"><i class="fal fa-box"></i> Select Product ({{ products.length }} found)</h5>
            <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
            <div v-if="isLoadingProducts" class="text-center py-4">
              <div class="spinner-border text-primary"></div>
              <p class="mt-2">Loading products...</p>
            </div>
            <div v-else-if="!products.length" class="text-center py-4">
              <i class="fal fa-inbox fa-3x text-muted mb-3"></i>
              <p class="text-muted">No products available</p>
            </div>
            <table v-else id="productTable" class="table table-bordered table-sm table-hover w-100">
              <thead class="thead-light">
                <tr>
                  <th style="width: 15%;">Code</th>
                  <th style="width: 40%;">Description</th>
                  <th style="width: 25%;">UoM</th>
                  <th style="width: 10%;">Action</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios';
import { initSelect2, destroySelect2 } from '@/Utils/select2';
import { showAlert } from '@/Utils/bootbox';

// Props & Emits
const props = defineProps({
  purchaseRequestId: Number,
  requester: Object
});
const emit = defineEmits(['submitted']);

// State
const isSubmitting = ref(false);
const isImporting = ref(false);
const isLoadingProducts = ref(true);
const isEditMode = ref(!!props.purchaseRequestId);
const products = ref([]);
const campuses = ref([]);
const departments = ref([]);
const divisions = ref([]);
const budgetCodes = ref([]);
const fileInput = ref(null);
const attachmentInput = ref(null);
const selectedFileName = ref('');
const productTable = ref(null);
const existingFileUrls = ref([]);
const fileLabel = ref('Choose file(s)...');
const isLoadingUsers = ref({});
const form = ref({
  deadline_date: '',
  purpose: '',
  is_urgent: false,
  file: null,
  items: [],
  created_by: '',
  position_id: '',
  approvals: []
});

// Set created_by and position_id from requester prop
form.value.created_by = props.requester?.id || '';
form.value.position_id = props.requester?.current_position_id || '';

// Computed
const totalAmount = computed(() =>
  form.value.items.reduce((sum, item) => sum + (item.quantity * item.unit_price * (item.exchange_rate || 1)), 0)
);
const isFormValid = computed(() => form.value.purpose && form.value.items.length && !form.value.items.some(item => !item.product_id));

// Navigation
const navigateToList = () => {
  window.location.href = '/purchase-requests';
};

// Data Loading
const fetchData = async () => {
  try {
    isLoadingProducts.value = true;
    const [productRes, campusRes, deptRes, divRes, budgetRes] = await Promise.all([
      axios.get('/api/purchase-requests/get-products'),
      axios.get('/api/campuses'),
      axios.get('/api/departments'),
      axios.get('/api/divisions'),
      // axios.get('/api/budget-codes')
    ]);

    products.value = productRes.data.data || [];
    campuses.value = campusRes.data.data || [];
    departments.value = deptRes.data.data || [];
    divisions.value = divRes.data.data || [];
    budgetCodes.value = budgetRes.data.data || [];

    if (isEditMode.value) await loadEditData();
  } catch (error) {
    showAlert('Error', `Failed to load data: ${error.message}`, 'danger');
  } finally {
    isLoadingProducts.value = false;
  }
};

const loadEditData = async () => {
  try {
    const { data } = await axios.get(`/api/purchase-requests/${props.purchaseRequestId}/edit`);
    const { deadline_date, purpose, is_urgent, items, approvals, file_urls } = data.data;
    form.value.deadline_date = deadline_date;
    form.value.purpose = purpose;
    form.value.is_urgent = is_urgent;
    form.value.items = items?.map(item => ({
      ...createItem(item),
      product_code: products.value.find(p => p.id === item.product_id)?.item_code || '',
      product_description: products.value.find(p => p.id === item.product_id)?.description || ''
    })) || [];
    form.value.approvals = approvals?.map(approval => ({
      id: approval.id || null,
      user_id: approval.responder?.id || null,
      request_type: approval.request_type || ''
    })) || [];
    existingFileUrls.value = file_urls || [];
  } catch (error) {
    showAlert('Error', `Failed to load edit data: ${error.message}`, 'danger');
  }
};

// Item Helpers
const createItem = (data) => ({
  id: data.id || null,
  product_id: data.product_id || '',
  product_code: data.product_code || '',
  product_description: data.product_description || '',
  quantity: data.quantity || 0,
  unit_price: data.unit_price || 0,
  currency: data.currency || '',
  exchange_rate: data.exchange_rate || null,
  description: data.description || '',
  campus_id: data.campus_id || '',
  department_id: data.department_id || '',
  division_id: data.division_id || '',
  budget_code_id: data.budget_code_id || ''
});

// File Handling
const onFileChange = (event) => {
  const files = event.target.files;
  form.value.file = files;
  if (files.length > 0) {
    const fileNames = Array.from(files).map(file => file.name).join(', ');
    fileLabel.value = files.length > 1 ? `${files.length} files selected` : fileNames;
  } else {
    fileLabel.value = 'Choose file(s)...';
  }
};

const onImportFile = (event) => {
  const file = event.target.files[0];
  const validTypes = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel', 'text/csv'];
  if (file && !validTypes.includes(file.type)) {
    showAlert('Error', 'Please upload Excel or CSV file.', 'danger');
    return;
  }
  selectedFileName.value = file?.name || '';
};

const importFile = async () => {
  if (!fileInput.value?.files[0]) return showAlert('Error', 'Please select a file.', 'danger');
  isImporting.value = true;
  const formData = new FormData();
  formData.append('file', fileInput.value.files[0]);
  try {
    const { data } = await axios.post('/api/purchase-requests/import', formData, { headers: { 'Content-Type': 'multipart/form-data' } });
    form.value.items = data.data.items.map(item => createItem(item));
    showAlert('Success', `${form.value.items.length} items imported!`, 'success');
  } catch (error) {
    showAlert('Error', error.response?.data?.message || 'Import failed', 'danger');
  } finally {
    isImporting.value = false;
    fileInput.value.value = '';
    selectedFileName.value = '';
  }
};

// Items
const addItem = (productId) => {
  const product = products.value.find(p => p.id === Number(productId));
  if (!product) return showAlert('Error', 'Product not found.', 'danger');
  const existingIndex = form.value.items.findIndex(item => item.product_id === Number(productId));
  if (existingIndex > -1) {
    form.value.items[existingIndex].quantity += 1;
    showAlert('Info', `Quantity increased for ${product.item_code}`, 'info');
  } else {
    form.value.items.push(createItem({
      product_id: Number(productId),
      product_code: product.item_code,
      product_description: product.description,
      quantity: 1,
      unit_price: 0
    }));
  }
  $('#productModal').modal('hide');
};

const removeItem = (index) => {
  form.value.items.splice(index, 1);
};

// Approvals
const initApprovalSelects = async () => {
  await nextTick();
  form.value.approvals.forEach((_, index) => {
    initApprovalTypeSelect(index);
    initUserSelect(index);
  });
};

const initApprovalTypeSelect = (index) => {
  const element = document.querySelector(`.approval-type-select[data-index="${index}"]`);
  if (!element) return;
  destroySelect2(element);
  initSelect2(element, { placeholder: 'Select Type', width: '100%', allowClear: true }, (value) => {
    form.value.approvals[index].request_type = value || '';
    initUserSelect(index); // Reinitialize user select when type changes
  });
  $(element).val(form.value.approvals[index].request_type || '').trigger('change.select2');
};

const initUserSelect = async (index) => {
  const element = document.querySelector(`.user-select[data-index="${index}"]`);
  if (!element) return;

  // Clean up existing Select2 instance
  destroySelect2(element);
  $(element).empty().append('<option value="">Select User</option>');

  const requestType = form.value.approvals[index].request_type;
  if (!['initial', 'approve', 'check', 'verify'].includes(requestType)) {
    initSelect2(element, { placeholder: 'Select User', width: '100%', allowClear: true }, (value) => {
      form.value.approvals[index].user_id = value ? Number(value) : '';
    });
    return;
  }

  try {
    // Set loading state for this specific index
    isLoadingUsers.value[index] = true;

    // Fetch users from the API, aligned with the controller
    const { data } = await axios.get('/api/purchase-requests/get-users-for-approval', {
      params: { request_type: requestType }
    });

    // Map users and ensure no duplicates
    const users = data.data || [];
    users.forEach(user => {
      $(element).append(`<option value="${user.id}">${user.name} (${user.card_number || 'N/A'})</option>`);
    });

    // Initialize Select2
    initSelect2(element, {
      placeholder: 'Select User',
      width: '100%',
      allowClear: true
    }, (value) => {
      form.value.approvals[index].user_id = value ? Number(value) : '';
    });

    // Set the current value
    $(element).val(form.value.approvals[index].user_id || '').trigger('change.select2');
  } catch (error) {
    let errorMessage = 'Failed to load users for approval.';
    if (error.response?.status === 403) {
      errorMessage = 'You are not authorized to view users for this approval type.';
    } else if (error.response?.status === 422) {
      errorMessage = 'Invalid approval type provided.';
    } else {
      errorMessage = error.response?.data?.message || errorMessage;
    }
    showAlert('Error', errorMessage, 'danger');
  } finally {
    isLoadingUsers.value[index] = false;
  }
};

const addApproval = async () => {
  form.value.approvals.push({ request_type: '', user_id: '', isDefault: false });
  await nextTick();
  initApprovalSelects();
};

const removeApproval = async (index) => {
  const typeElement = document.querySelector(`.approval-type-select[data-index="${index}"]`);
  const userElement = document.querySelector(`.user-select[data-index="${index}"]`);
  if (typeElement) destroySelect2(typeElement);
  if (userElement) destroySelect2(userElement);
  form.value.approvals.splice(index, 1);
  await nextTick();
  initApprovalSelects();
};

// Form Submission
const submitForm = async () => {
  if (!isFormValid.value) return showAlert('Error', 'Please complete all required fields', 'danger');
  isSubmitting.value = true;
  const formData = new FormData();
  formData.append('deadline_date', form.value.deadline_date || '');
  formData.append('purpose', form.value.purpose || '');
  formData.append('is_urgent', form.value.is_urgent ? '1' : '0');
  formData.append('created_by', form.value.created_by || '');
  formData.append('position_id', form.value.position_id || '');
  if (form.value.file) Array.from(form.value.file).forEach(file => formData.append('file[]', file));
  form.value.items.forEach((item, index) => {
    Object.entries(item).forEach(([key, value]) => {
      if (value !== null && value !== undefined) formData.append(`items[${index}][${key}]`, value);
    });
  });
  form.value.approvals.forEach((approval, index) => {
    formData.append(`approvals[${index}][user_id]`, approval.user_id || '');
    formData.append(`approvals[${index}][request_type]`, approval.request_type || '');
    if (approval.id) formData.append(`approvals[${index}][id]`, approval.id);
  });
  try {
    const method = isEditMode.value ? 'put' : 'post';
    const url = isEditMode.value ? `/api/purchase-requests/${props.purchaseRequestId}` : '/api/purchase-requests';
    await axios[method](url, formData);
    showAlert('Success', `Purchase request ${isEditMode.value ? 'updated' : 'created'} successfully!`, 'success');
    emit('submitted');
    navigateToList();
  } catch (error) {
    showAlert('Error', error.response?.data?.message || 'Save failed', 'danger');
  } finally {
    isSubmitting.value = false;
  }
};

// UI Initialization
const initDatepicker = () => {
  $('.datepicker').datepicker({
    format: 'yyyy-mm-dd',
    autoclose: true,
    todayHighlight: true
  }).on('changeDate', (event) => {
    const date = event.date;
    if (date) {
      form.value[event.target.dataset.field] = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    }
  });
};

const initProductTable = () => {
  if (!products.value.length || !document.getElementById('productTable')) return;
  if (productTable.value) productTable.value.destroy();
  $('#productTable tbody').empty();
  productTable.value = $('#productTable').DataTable({
    data: products.value,
    responsive: true,
    pageLength: 25,
    searching: true,
    ordering: true,
    lengthChange: false,
    columns: [
      { data: 'item_code', width: '15%' },
      { data: 'description', width: '40%' },
      { data: 'unit_name', width: '25%' },
      {
        data: null,
        orderable: false,
        searchable: false,
        width: '10%',
        render: (data, type, row) => `<button class="btn btn-primary btn-sm select-product-btn" data-id="${row.id}" title="Select ${row.description}"><i class="fal fa-check"></i> Select</button>`
      }
    ]
  });
  $('#productTable').off('click.select-product').on('click.select-product', '.select-product-btn', (event) => addItem($(event.currentTarget).data('id')));
};

// Lifecycle Hooks
onMounted(async () => {
  try {
    await fetchData();
    initDatepicker();
    initProductTable();
    await initApprovalSelects();
    $('#productModal').on('shown.bs.modal', initProductTable);
  } catch (error) {
    showAlert('Error', 'Failed to initialize form.', 'danger');
  }
});

onUnmounted(() => {
  if (productTable.value) productTable.value.destroy();
  $('#productModal').off('shown.bs.modal');
  $('.approval-type-select, .user-select').each(function () { destroySelect2(this); });
  $('.datepicker').datepicker('destroy');
});
</script>

<style scoped>
.table-sm th, .table-sm td { padding: 0.25rem; }
.btn-sm { padding: 0.25rem 0.5rem; font-size: 0.75rem; }
</style>