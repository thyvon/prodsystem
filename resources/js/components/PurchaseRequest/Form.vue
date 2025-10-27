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
                  <button type="button" class="btn btn-primary ml-2" @click="importItems" :disabled="isImporting || !fileLabel">
                    <span v-if="isImporting" class="spinner-border spinner-border-sm mr-1"></span> Import
                  </button>
                  <a class="btn btn-success ml-2" href="/sampleExcel/purchase_request_item_sample.xlsx" download>
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
            <span v-if="totalAmount" class="badge badge-primary ml-2">
              {{ totalAmount }}
            </span>
            </h5>
            <div class="table-responsive" style="max-height: 700px;overflow-y: auto;">
              <table class="table table-bordered table-striped table-sm table-hover" style="width: 100%;">
                <thead style="position: sticky; top: 0; background: #1E90FF; z-index: 10;">
                  <tr>
                    <th style="min-width: 100px;">Code</th>
                    <th style="min-width: 300px;">Description</th>
                    <th style="min-width: 30px;">UoM</th>
                    <th style="min-width: 200px;">Remarks</th>
                    <th style="min-width: 100px;">Currency</th>
                    <th style="min-width: 100px;">Ex. Rate</th>
                    <th style="min-width: 100px;">Qty</th>
                    <th style="min-width: 100px;">Price</th>
                    <th style="min-width: 100px;">Value USD</th>
                    <th style="min-width: 100px;">Campus</th>
                    <th style="min-width: 100px;">Dept</th>
                    <th style="min-width: 100px;">Budget</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, index) in form.items" :key="index">
                    <td>{{ item.product_code }}</td>
                    <td>{{ item.product_description }}</td>
                    <td>{{ item.unit_name || 'N/A' }}</td>
                    <td><textarea v-model="item.description" class="form-control form-control-sm" rows="1"></textarea></td>
                    <td>
                      <select v-model="item.currency" class="form-control form-control-sm">
                        <option value="">Select</option>
                        <option value="USD">USD</option>
                        <option value="KHR">KHR</option>
                      </select>
                    </td>
                    <td><input v-model.number="item.exchange_rate" type="number" min="0" step="0.01" class="form-control form-control-sm" /></td>
                    <td><input v-model.number="item.unit_price" type="number" min="0" step="0.01" class="form-control form-control-sm" required /></td>
                    <td><input v-model.number="item.quantity" type="number" min="0.01" step="0.01" class="form-control form-control-sm" required /></td>
                    <td>
                      <input
                        type="text"
                        class="form-control form-control-sm"
                        :value="(item.quantity * item.unit_price / (item.currency === 'KHR' ? (item.exchange_rate || 1) : 1)).toLocaleString('en-US', { minimumFractionDigits: 4, maximumFractionDigits: 4 })"
                        readonly
                      />
                    </td>
                    <td><select multiple class="form-control campus-select" :data-index="index"></select></td>
                    <td><select multiple class="form-control department-select" :data-index="index"></select></td>
                    <td>
                      <select class="form-control budget-select" :data-index="index"></select>
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
                    <th style="width: 50%">Type</th>
                    <th style="width: 50%">User</th>
                    <th style="width: 10%">Action</th>
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

    <!-- 🪟 Product Modal -->
    <div class="modal fade" id="productModal" tabindex="-1">
      <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Select Product</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span>&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div v-if="isLoadingProducts" class="text-center py-4">
              <div class="spinner-border text-primary" role="status"></div>
              <p class="mt-2">Loading products...</p>
            </div>
            <table
              v-show="!isLoadingProducts"
              id="productTable"
              class="table table-bordered table-striped"
              style="width: 100%"
            >
              <thead>
                <tr>
                  <th>Item Code</th>
                  <th>Description</th>
                  <th>Unit</th>
                  <th>Estimated Price</th>
                  <th>Action</th>
                </tr>
              </thead>
            </table>
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

// -------------------- Props & Emits --------------------
const props = defineProps({
  purchaseRequestId: Number,
  requester: Object,
  userDefaultDepartment: Object,
  userDefaultCampus: Object
});
const emit = defineEmits(['submitted']);

// -------------------- Reactive State --------------------
const isSubmitting = ref(false);
const isImporting = ref(false);
const isLoadingProducts = ref(false);
const isEditMode = ref(!!props.purchaseRequestId);

const products = ref([]);
const campuses = ref([]);
const departments = ref([]);
const fileLabel = ref('Choose file(s)...');
const existingFileUrls = ref([]);
const fileInput = ref(null);

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

// -------------------- Computed Properties --------------------
const totalAmount = computed(() => {
  let totalKHR = 0, totalUSD = 0, totalKHRinUSD = 0;

  form.value.items.forEach(i => {
    const amount = i.quantity * i.unit_price;
    if (i.currency === 'KHR') {
      totalKHR += amount;
      totalKHRinUSD += amount / (i.exchange_rate || 1);
    } else if (i.currency === 'USD') totalUSD += amount;
  });

  const parts = [];
  if (totalKHR) parts.push(`KHR = ${totalKHR.toLocaleString('en-US', { minimumFractionDigits: 2 })}`);
  if (totalUSD) parts.push(`USD = ${totalUSD.toLocaleString('en-US', { minimumFractionDigits: 2 })}`);
  if (totalKHRinUSD || totalUSD) parts.push(`Total as USD = ${(totalUSD + totalKHRinUSD).toLocaleString('en-US', { minimumFractionDigits: 2 })}`);

  return parts.length ? parts.join(' | ') : null;
});

const isFormValid = computed(() =>
  form.value.purpose &&
  form.value.items.length &&
  !form.value.items.some(i => !i.product_id)
);

// -------------------- Helper Functions --------------------
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
  campus_ids: data.campus_ids?.length ? data.campus_ids : [props.userDefaultCampus?.id],
  department_ids: data.department_ids?.length ? data.department_ids : [props.userDefaultDepartment?.id],
  budget_code_id: data.budget_code_id || budgetCodes.value[0]?.id || ''
});

const navigateToList = () => window.location.href = '/purchase-requests';

const onFileChange = (e) => {
  const files = e.target.files;
  form.value.file = files;
  fileLabel.value = files?.length > 1 ? `${files.length} files selected` : files[0]?.name || 'Choose file(s)...';
};

// -------------------- Load Purchase Request --------------------
const loadPurchaseRequest = async () => {
  if (!isEditMode.value) return;

  try {
    const { data } = await axios.get(`/api/purchase-requests/${props.purchaseRequestId}/edit`);
    const pr = data.data;

    // Basic fields
    form.value.deadline_date = pr.deadline_date || '';
    form.value.purpose = pr.purpose || '';
    form.value.is_urgent = pr.is_urgent === 1;

    // Files
    existingFileUrls.value = pr.files || [];

    // Items
    form.value.items = (pr.items || []).map(item => createItem({
      product_id: item.product_id,
      product_code: item.product_code,
      product_description: item.product_description,
      unit_name: item.unit_name || 'N/A',
      quantity: item.quantity,
      unit_price: item.unit_price,
      currency: item.currency,
      exchange_rate: item.exchange_rate,
      description: item.description,
      campus_ids: item.campus_ids || [props.userDefaultCampus?.id],
      department_ids: item.department_ids || [props.userDefaultDepartment?.id],
      budget_code_id: item.budget_code_id
    }));

    // Approvals
    form.value.approvals = (pr.approvals || []).map(app => ({
      user_id: app.user_id || '',
      request_type: app.request_type || '',
      availableUsers: []
    }));

    await nextTick(() => {
      initDatepicker();
      initItemSelects();
      initApprovalSelects();
    });

  } catch (err) {
    showAlert('Error', 'Failed to load purchase request for edit mode.', 'danger');
  }
};

// -------------------- Import Items --------------------
const importItems = async () => {
  if (isImporting.value) return;

  if (!fileInput.value?.files?.length) {
    return showAlert('Error', 'Please select a file to import.', 'danger');
  }

  isImporting.value = true;
  const formData = new FormData();
  formData.append('file', fileInput.value.files[0]);

  try {
    const { data, status } = await axios.post('/api/purchase-requests/import-items', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });

    if (status === 200 && data.data?.items?.length) {
      form.value.items = data.data.items.map(item => createItem({
        ...item,
        unit_name: item.unit_name || 'N/A',
        quantity: item.quantity || 0,
        unit_price: item.unit_price || 0,
        exchange_rate: item.exchange_rate || 1,
        campus_ids: item.campus_ids?.length ? item.campus_ids : [props.userDefaultCampus?.id],
        department_ids: item.department_ids?.length ? item.department_ids : [props.userDefaultDepartment?.id],
        budget_code_id: item.budget_code_id || budgetCodes.value[0]?.id
      }));

      await nextTick(initItemSelects);
      showAlert('Success', 'Purchase request items imported successfully.', 'success');
      fileInput.value.value = '';
      fileLabel.value = 'Choose file(s)...';
    } else {
      const errors = data.errors || [data.message || 'Unknown error occurred'];
      showAlert('Import Errors', `Errors in Excel file:<br>${errors.join('<br>')}`, 'danger');
    }
  } catch (err) {
    const errors = err.response?.data?.errors || [err.response?.data?.message || 'Failed to import items.'];
    showAlert('Error', `Import failed:<br>${errors.join('<br>')}`, 'danger');
  } finally {
    isImporting.value = false;
  }
};

// -------------------- Items Management --------------------
const addItem = (productId) => {
  const product = products.value.find(p => p.id === Number(productId));
  if (!product) return showAlert('Error', 'Product not found', 'danger');

  form.value.items.push(createItem({
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
  ['campus', 'department', 'budget'].forEach(t => {
    const el = document.querySelector(`.${t}-select[data-index="${index}"]`);
    if (el) destroySelect2(el);
  });
  form.value.items.splice(index, 1);
  nextTick(initItemSelects);
};

// -------------------- Select2 Initialization --------------------
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

const initBudgetSelect = (index) => {
  const el = document.querySelector(`.budget-select[data-index="${index}"]`);
  if (!el) return;
  destroySelect2(el);
  $(el).empty();

  budgetCodes.value.forEach(b => $(el).append(`<option value="${b.id}">${b.code}</option>`));

  initSelect2(el, { width: '100%', allowClear: true, placeholder: 'Select Budget' }, val => {
    form.value.items[index].budget_code_id = val ? Number(val) : null;
  });

  const current = form.value.items[index].budget_code_id || '';
  $(el).val(current ? String(current) : '').trigger('change.select2');
};

const initItemSelects = async () =>
  form.value.items.forEach((_, i) => {
    ['campus', 'department'].forEach(t => initSelect(i, t));
    initBudgetSelect(i);
  });

// -------------------- Approvals Management --------------------
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

// -------------------- Product Table Modal --------------------
const initProductTable = () => {
  const tableEl = $('#productTable');
  if (!tableEl.length) return;

  const dt = tableEl.DataTable({
    processing: true,
    serverSide: true,
    responsive: true,
    destroy: true,
    ajax: {
      url: '/api/purchase-requests/get-products',
      type: 'GET',
      dataSrc: (json) => { products.value = json.data || []; return json.data; },
      error: () => showAlert('Error', 'Failed to load products', 'danger')
    },
    paging: false,
    lengthChange: false,
    ordering: false,
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

  return dt;
};

const showProductModal = async () => {
  isLoadingProducts.value = true;
  $('#productModal').modal('show');

  await nextTick(() => {
    const tableEl = $('#productTable');
    if ($.fn.DataTable.isDataTable(tableEl)) tableEl.DataTable().ajax.reload(null, false);
    else initProductTable();
    isLoadingProducts.value = false;
  });
};

// -------------------- Datepicker --------------------
const initDatepicker = () => {
  $('.datepicker').datepicker({
    format: 'yyyy-mm-dd',
    autoclose: true
  }).on('changeDate', function(e) {
    form.value.deadline_date = e.format('yyyy-mm-dd');
  });

  if (form.value.deadline_date) $('.datepicker').datepicker('update', form.value.deadline_date);
};

// -------------------- Form Submission --------------------
const submitForm = async () => {
  if (!isFormValid.value) return showAlert('Error', 'Form is incomplete', 'danger');

  isSubmitting.value = true;

  try {
    const fd = new FormData();
    fd.append('deadline_date', form.value.deadline_date);
    fd.append('purpose', form.value.purpose);
    fd.append('is_urgent', form.value.is_urgent ? 1 : 0);

    if (form.value.file) Array.from(form.value.file).forEach(file => fd.append('file[]', file));

    form.value.items.forEach((item, i) => {
      Object.entries(item).forEach(([key, value]) => {
        if (Array.isArray(value)) value.forEach((v, j) => fd.append(`items[${i}][${key}][${j}]`, v));
        else fd.append(`items[${i}][${key}]`, value);
      });
    });

    form.value.approvals.forEach((app, i) => {
      Object.entries(app).forEach(([key, value]) => fd.append(`approvals[${i}][${key}]`, value));
    });

    const url = isEditMode.value
      ? `/api/purchase-requests/${props.purchaseRequestId}`
      : '/api/purchase-requests';
    const method = isEditMode.value ? 'put' : 'post';

    const response = await axios({ url, method, data: fd, headers: { 'Content-Type': 'multipart/form-data' } });

    await showAlert(
      'Success',
      isEditMode.value
        ? 'Purchase Request updated successfully.'
        : 'Purchase Request created successfully.',
      'success'
    );

    emit('submitted', response.data.data);
    navigateToList();
  } catch (error) {
    showAlert('Error', error.response?.data?.message || error.message, 'danger');
  } finally {
    isSubmitting.value = false;
  }
};

// -------------------- Lifecycle --------------------
onMounted(async () => {
  try {
    const [campusRes, deptRes] = await Promise.all([axios.get('/api/campuses'), axios.get('/api/departments')]);
    campuses.value = campusRes.data.data || [];
    departments.value = deptRes.data.data || [];
  } catch {
    campuses.value = [];
    departments.value = [];
  }

  initDatepicker();
  await loadPurchaseRequest();
});
</script>

<style scoped>
.table td, .table th { vertical-align: middle; }
</style>
