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
            <!-- Requester Info -->
            <div class="col-md-6">
              <div class="border rounded p-3 mb-4" style="max-height: 300px; overflow-y: auto;">
                <h5 class="font-weight-bold mb-3 text-primary">👤 Requester Info</h5>
                <div v-for="(value, label) in requester" :key="label" class="row mb-2">
                  <div class="col-4 font-weight-bold text-muted">{{ label }}:</div>
                  <div class="col-8 border-bottom py-1">{{ value || 'N/A' }}</div>
                </div>
              </div>
            </div>

            <!-- PR Info -->
            <div class="col-md-6">
              <div class="border rounded p-3 mb-4">
                <h5 class="font-weight-bold mb-3 text-primary">📋 PR Information</h5>
                <div class="form-row">
                  <div class="form-group col-md-6">
                    <label class="font-weight-bold">📅 Deadline</label>
                    <input v-model="form.deadline_date" class="form-control datepicker" />
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
                  <textarea v-model="form.purpose" class="form-control" rows="2" placeholder="Enter purpose..." required></textarea>
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
                    <i class="fal fa-file-upload"></i> {{ fileLabel }}
                  </button>
                  <button type="button" class="btn btn-primary ml-2" @click="importFile" :disabled="isImporting || !fileLabel">
                    <span v-if="isImporting" class="spinner-border spinner-border-sm mr-1"></span> Import
                  </button>
                  <a class="btn btn-success ml-2" href="/sampleExcel/purchase_request_items_sample.xlsx" download>
                    <i class="fal fa-file-excel"></i>
                  </a>
                </div>
              </div>
              <div class="form-group col-md-8">
                <label class="font-weight-bold">➕ Add Product</label>
                <button type="button" class="btn btn-primary btn-block" @click="showProductModal" :disabled="isLoadingProducts">
                  <span v-if="isLoadingProducts" class="spinner-border spinner-border-sm mr-2"></span>
                  <i class="fal fa-plus"></i> Select Product
                </button>
              </div>
            </div>

            <!-- Items Table -->
            <h5 class="font-weight-bold mb-3 text-primary">
              📦 Items ({{ form.items.length }}) 
              <span v-if="totalAmount" class="badge badge-primary ml-2">${{ totalAmount.toLocaleString('en-US', { minimumFractionDigits: 2 }) }}</span>
            </h5>
            <div class="table-responsive" style="max-height: 400px;">
              <table class="table table-bordered table-sm table-hover">
                <thead class="thead-light">
                  <tr>
                    <th>Code</th>
                    <th>Description</th>
                    <th>UoM</th>
                    <th>Remarks</th>
                    <th>Qty</th>
                    <th>Price</th>
                    <th>Currency</th>
                    <th>Ex. Rate</th>
                    <th>Campus</th>
                    <th>Dept</th>
                    <th>Budget</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, index) in form.items" :key="index">
                    <td>{{ item.product_code }}</td>
                    <td>{{ item.product_description }}</td>
                    <td>{{ item.unit_name || 'N/A' }}</td>
                    <td><textarea v-model="item.description" class="form-control form-control-sm" rows="1"></textarea></td>
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
                    <td><select multiple class="form-control campus-select" :data-index="index"></select></td>
                    <td><select multiple class="form-control department-select" :data-index="index"></select></td>
                    <td>
                      <select v-model="item.budget_code_id" class="form-control form-control-sm">
                        <option v-for="budget in budgetCodes" :key="budget.id" :value="budget.id">{{ budget.code }}</option>
                      </select>
                    </td>
                    <td class="text-center">
                      <button @click.prevent="removeItem(index)" class="btn btn-danger btn-sm"><i class="fal fa-trash"></i></button>
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
                    <th>Type</th>
                    <th>User</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(approval, index) in form.approvals" :key="index">
                    <td>
                      <select class="form-control approval-type-select" :data-index="index" v-model="approval.request_type"></select>
                    </td>
                    <td>
                      <select class="form-control user-select" :data-index="index" v-model="approval.user_id" :disabled="!approval.request_type"></select>
                    </td>
                    <td class="text-center">
                      <button @click.prevent="removeApproval(index)" class="btn btn-danger btn-sm"><i class="fal fa-trash"></i></button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <button @click.prevent="addApproval" class="btn btn-outline-primary btn-sm mt-2"><i class="fal fa-plus"></i> Add Approval</button>
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
            <h5 class="modal-title"><i class="fal fa-box"></i> Products List</h5>
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
                  <th>Code</th>
                  <th>Description</th>
                  <th>UoM</th>
                  <th>Est. Price</th>
                  <th>Action</th>
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
import { ref, computed, onMounted, nextTick } from 'vue';
import axios from 'axios';
import { initSelect2, destroySelect2 } from '@/Utils/select2';
import { showAlert } from '@/Utils/bootbox';

const props = defineProps({
  purchaseRequestId: Number,
  requester: Object,
  userDefaultDepartment: Object,
  userDefaultCampus: Object
});
const emit = defineEmits(['submitted']);

const isSubmitting = ref(false);
const isImporting = ref(false);
const isLoadingProducts = ref(true);
const isEditMode = ref(!!props.purchaseRequestId);
const products = ref([]);
const campuses = ref([]);
const departments = ref([]);
const fileLabel = ref('Choose file(s)...');
const existingFileUrls = ref([]);

const form = ref({
  deadline_date: '',
  purpose: '',
  is_urgent: false,
  file: null,
  items: [],
  created_by: props.requester?.id || '',
  position_id: props.requester?.current_position_id || '',
  approvals: []
});

// --- Temporary Budget Codes ---
const budgetCodes = ref([
  { id: 1, code: 'BUD-001', name: 'Office Supplies' },
  { id: 2, code: 'BUD-002', name: 'IT Equipment' },
  { id: 3, code: 'BUD-003', name: 'Maintenance' }
]);

const approvalTypes = [
  { id: 'initial', text: 'Initial' },
  { id: 'approve', text: 'Approve' },
  { id: 'check', text: 'Check' },
  { id: 'verify', text: 'Verify' }
];

const usersForApproval = ref({ initial: [], approve: [], check: [], verify: [] });

const totalAmount = computed(() =>
  form.value.items.reduce((sum, i) => sum + i.quantity * i.unit_price * (i.exchange_rate || 1), 0)
);

const isFormValid = computed(() =>
  form.value.purpose &&
  form.value.items.length &&
  !form.value.items.some(i => !i.product_id)
);

// --- Helper Functions ---
const createItem = (data = {}) => ({
  product_id: data.product_id || '',
  product_code: data.product_code || '',
  product_description: data.product_description || '',
  unit_name: data.unit_name || '',
  quantity: data.quantity || 0,
  unit_price: data.unit_price || 0,
  currency: data.currency || '',
  exchange_rate: data.exchange_rate || null,
  description: data.description || '',
  campus_ids: data.campus_ids || [props.userDefaultCampus?.id],
  department_ids: data.department_ids || [props.userDefaultDepartment?.id],
  budget_code_id: data.budget_code_id || budgetCodes.value[0]?.id || ''
});

const navigateToList = () => window.location.href = '/purchase-requests';

const onFileChange = (e) => {
  const files = e.target.files;
  form.value.file = files;
  fileLabel.value = files?.length > 1 ? `${files.length} files selected` : files[0]?.name || 'Choose file(s)...';
};

// --- Items Functions ---
const addItem = (productId) => {
  const product = products.value.find(p => p.id === Number(productId));
  if (!product) return showAlert('Error', 'Product not found', 'danger');

  const exist = form.value.items.find(i => i.product_id === Number(productId));
  if (exist) exist.quantity += 1;
  else form.value.items.push(createItem({
    product_id: product.id,
    product_code: product.item_code,
    product_description: product.description,
    unit_name: product.unit_name,
    quantity: 1,
    unit_price: 0
  }));

  $('#productModal').modal('hide');
  nextTick(initItemSelects);
};

const removeItem = (index) => {
  ['campus', 'department'].forEach(t => {
    const el = document.querySelector(`.${t}-select[data-index="${index}"]`);
    if (el) destroySelect2(el);
  });
  form.value.items.splice(index, 1);
  nextTick(initItemSelects);
};

// --- Select2 Initialization ---
const initSelect = (index, type) => {
  const el = document.querySelector(`.${type}-select[data-index="${index}"]`);
  if (!el) return;
  destroySelect2(el);
  $(el).empty();
  const dataList = type === 'campus' ? campuses.value : departments.value;
  dataList.forEach(d => $(el).append(`<option value="${d.id}">${d.short_name || d.name}</option>`));

  initSelect2(el, { multiple: true, allowClear: true, width: '100%', placeholder: `Select ${type}` }, val => {
    form.value.items[index][`${type}_ids`] = val ? val.map(Number) : [];
  });

  const current = form.value.items[index][`${type}_ids`] || [];
  $(el).val(current.map(String)).trigger('change.select2');
};

const initItemSelects = () =>
  form.value.items.forEach((_, i) => ['campus', 'department'].forEach(t => initSelect(i, t)));

// --- Approval Functions ---
const fetchUsersForApproval = async (type) => {
  if (!type || usersForApproval.value[type]?.length) return;
  try {
    const { data } = await axios.get('/api/purchase-requests/get-approval-users', { params: { request_type: type } });
    usersForApproval.value[type] = data.data || [];
  } catch { usersForApproval.value[type] = []; }
};

const initApprovalSelect = async (index) => {
  const approval = form.value.approvals[index];
  if (!approval) return;

  const typeEl = document.querySelector(`.approval-type-select[data-index="${index}"]`);
  const userEl = document.querySelector(`.user-select[data-index="${index}"]`);

  if (typeEl) {
    destroySelect2(typeEl);
    typeEl.innerHTML = '<option value="">Select Type</option>';
    approvalTypes.forEach(t => $(typeEl).append(`<option value="${t.id}">${t.text}</option>`));

    initSelect2(typeEl, { width: '100%', allowClear: true }, async val => {
      approval.request_type = val || '';
      approval.availableUsers = [];

      if (approval.request_type) {
        await fetchUsersForApproval(approval.request_type);
        approval.availableUsers = usersForApproval.value[approval.request_type] || [];
      }

      // populate user select after users are available
      if (userEl) {
        destroySelect2(userEl);
        userEl.innerHTML = '<option value="">Select User</option>';
        (approval.availableUsers || []).forEach(u => $(userEl).append(`<option value="${u.id}">${u.name}</option>`));
        initSelect2(userEl, { width: '100%', allowClear: true }, val => approval.user_id = val ? Number(val) : '');
        $(userEl).val(approval.user_id ? String(approval.user_id) : '').trigger('change.select2');
      }
    });

    $(typeEl).val(approval.request_type || '').trigger('change.select2');
  }

  // if type already set and users preloaded, ensure user select initialized
  if (userEl && (!approval.availableUsers || !approval.availableUsers.length)) {
    destroySelect2(userEl);
    userEl.innerHTML = '<option value="">Select User</option>';
    (approval.availableUsers || []).forEach(u => $(userEl).append(`<option value="${u.id}">${u.name}</option>`));
    initSelect2(userEl, { width: '100%', allowClear: true }, val => approval.user_id = val ? Number(val) : '');
    $(userEl).val(approval.user_id ? String(approval.user_id) : '').trigger('change.select2');
  }
};

const initApprovalSelects = async () =>
  form.value.approvals.forEach((_, i) => initApprovalSelect(i));

const addApproval = async () => {
  form.value.approvals.push({ user_id: '', request_type: '', availableUsers: [] });
  await nextTick(initApprovalSelects);
};

const removeApproval = async (i) => {
  form.value.approvals.splice(i, 1);
  await nextTick(initApprovalSelects);
};

// --- Product DataTable ---
const initProductTable = () => {
  const tableEl = $('#productTable');
  if (!tableEl.length) return;

  tableEl.DataTable({
    processing: true,
    serverSide: true,
    ajax: '/api/purchase-requests/get-products',
    paging: false,          // Disable pagination
    lengthChange: false,    // Hide page length selector
    columns: [
      { data: 'item_code' },
      { data: 'description' },
      { data: 'unit_name' },
      { data: 'estimated_price', render: d => d ? parseFloat(d).toLocaleString() : '-' },
      { data: null, orderable: false, render: (_, __, row) => `<button class="btn btn-primary btn-sm select-product-btn" data-id="${row.id}">Select</button>` }
    ]
  });

  tableEl.off('click.select-product').on('click.select-product', '.select-product-btn', e =>
    addItem($(e.currentTarget).data('id'))
  );
};

// Add this function to open/reload the modal
const showProductModal = () => {
  // ensure DataTable exists and is reloaded, or initialize it
  const tableEl = $('#productTable');
  if (tableEl.length && $.fn.DataTable.isDataTable(tableEl)) {
    tableEl.DataTable().ajax.reload(null, false);
  } else {
    nextTick(initProductTable);
  }
  $('#productModal').modal('show');
};

// --- Datepicker ---
const initDatepicker = () => {
  $('.datepicker').datepicker({ format: 'yyyy-mm-dd', autoclose: true });
};

// --- Lifecycle ---
onMounted(async () => {
  try {
    const [productRes, campusRes, deptRes] = await Promise.all([
      // axios.get('/api/purchase-requests/get-products'),
      axios.get('/api/purchase-requests/get-campuses'),
      axios.get('/api/purchase-requests/get-departments'),
    ]);
    products.value = productRes.data.data || [];
    campuses.value = campusRes.data.data || [];
    departments.value = deptRes.data.data || [];

    await nextTick(initProductTable);
    await nextTick(initDatepicker);
    await nextTick(initItemSelects);
    await nextTick(initApprovalSelects);

    // mark products loaded so button is enabled
    isLoadingProducts.value = false;
  } catch (err) {
    showAlert('Error', `Failed to load initial data: ${err.message}`, 'danger');
    isLoadingProducts.value = false;
  }
});

// --- Submit Form ---
const submitForm = async () => {
  if (!isFormValid.value) return showAlert('Error', 'Form is incomplete', 'danger');
  isSubmitting.value = true;
  try {
    const fd = new FormData();
    Object.keys(form.value).forEach(key => {
      if (key === 'file' && form.value.file) Array.from(form.value.file).forEach(f => fd.append('file[]', f));
      else fd.append(key, JSON.stringify(form.value[key]));
    });

    const url = isEditMode.value ? `/api/purchase-requests/${props.purchaseRequestId}` : '/api/purchase-requests';
    const method = isEditMode.value ? 'put' : 'post';
    const res = await axios({ url, method, data: fd });
    showAlert('Success', res.data.message, 'success');
    emit('submitted', res.data.data);
  } catch (err) {
    showAlert('Error', err.response?.data?.message || err.message, 'danger');
  } finally { isSubmitting.value = false; }
};
</script>


<style scoped>
.table td, .table th { vertical-align: middle; }
</style>
