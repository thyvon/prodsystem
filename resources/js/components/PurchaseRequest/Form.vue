<template>
  <div class="container-fluid">
    <form @submit.prevent="submitForm" enctype="multipart/form-data">
      <div class="card mb-0">

        <!-- Header -->
        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
          <h4 class="mb-0 font-weight-bold">
            {{ isEditMode ? '✏️ Edit' : '➕ Create' }} Purchase Request
          </h4>
          <button type="button" class="btn btn-outline-primary btn-sm" @click="navigateToList">
            <i class="fal fa-backward"></i> Back
          </button>
        </div>

        <!-- Body -->
        <div class="card-body">

          <!-- ROW 1: Requester + PR Info -->
          <div class="row d-flex mb-3">
            <!-- Requester Info -->
            <div class="col-md-6 d-flex">
              <div class="border rounded p-3 flex-fill">
                <h5 class="text-primary font-weight-bold mb-3">👤 Requester Info</h5>
                <div v-for="(value, label) in requester" :key="label" class="row mb-2">
                  <div class="col-4 font-weight-bold text-muted">{{ label }}:</div>
                  <div class="col-8 border-bottom py-1">{{ value || 'N/A' }}</div>
                </div>
              </div>
            </div>

            <!-- PR Info -->
            <div class="col-md-6 d-flex">
              <div class="border rounded p-3 flex-fill">
                <h5 class="text-primary font-weight-bold mb-3">📋 PR Information</h5>

                <div class="form-row">
                  <!-- Deadline -->
                  <div class="form-group col-md-6">
                  <label class="font-weight-bold">Deadline <span class="text-danger">*</span></label>
                  <input
                    id="deadline_date"
                    v-model="form.deadline_date"
                    type="text"
                    class="form-control"
                    placeholder="yyyy-mm-dd"
                  />
                  </div>

                  <!-- Urgent -->
                  <div class="form-group col-md-6">
                    <label class="font-weight-bold">🚨 Urgent</label>
                    <div class="custom-control custom-switch">
                      <input 
                        type="checkbox" 
                        class="custom-control-input" 
                        id="isUrgent" 
                        name="is_urgent"
                        v-model="form.is_urgent"
                      />
                      <label class="custom-control-label" for="isUrgent">
                        <span :class="form.is_urgent ? 'text-danger' : 'text-muted'">
                          {{ form.is_urgent ? 'YES' : 'NO' }}
                        </span>
                      </label>
                    </div>
                  </div>

                  <!-- Purpose -->
                  <div class="form-group col-12 mt-2">
                    <label for="purpose" class="font-weight-bold">🎯 Purpose</label>
                    <textarea 
                      id="purpose"
                      name="purpose"
                      v-model="form.purpose" 
                      class="form-control" 
                      rows="2" 
                      required
                    ></textarea>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- ROW 2: Import + Items Table -->
          <div class="border rounded p-3 mb-4">
            <div class="form-row mb-3">
              <!-- Import -->
              <div class="form-group col-md-4">
                <label class="font-weight-bold">📥 Import Items</label>
                <div class="input-group">
                  <input type="file" class="d-none" ref="fileInput" @change="onImportFile" accept=".xlsx,.xls,.csv"/>
                  <button type="button" class="btn btn-outline-secondary flex-fill" @click="$refs.fileInput.click()">Choose file</button>
                  <button type="button" class="btn btn-primary ml-2" @click="importItems" :disabled="isImporting || !fileLabel">
                    <span v-if="isImporting" class="spinner-border spinner-border-sm mr-1"></span> Import
                  </button>
                  <a class="btn btn-success ml-2" href="/sampleExcel/purchase_request_item_sample.xlsx" download>
                    <i class="fal fa-file-excel"></i>
                  </a>
                </div>
              </div>

              <!-- Add Product -->
              <div class="form-group col-md-8">
                <label class="font-weight-bold">➕ Add Product</label>
                <button type="button" class="btn btn-primary btn-block" @click="openProductsModal" :disabled="isLoadingProducts">
                  <span v-if="isLoadingProducts" class="spinner-border spinner-border-sm mr-2"></span>
                  <i class="fal fa-plus"></i> Select Product
                </button>
              </div>
            </div>

            <!-- Items Table -->
            <h5 class="font-weight-bold mb-3 text-primary">
              📦 Items ({{ form.items.length }}) 
              <span v-if="totalAmount" class="badge badge-primary ml-2">{{ totalAmount }}</span>
            </h5>
            <div class="table-responsive" style="max-height: 700px; overflow-y: auto;">
              <table class="table table-bordered table-striped table-sm table-hover">
                <thead style="position: sticky; top: 0; background: #1E90FF; z-index: 10; text-align: center;">
                <tr>
                  <th style="min-width: 150px;">Item Code</th>
                  <th style="min-width: 300px;">Description</th>
                  <th style="min-width: 80px;">UoM</th>
                  <th style="min-width: 200px;">Remarks</th>
                  <th style="min-width: 80px;">Currency</th>
                  <th style="min-width: 100px;">Ex. Rate</th>
                  <th style="min-width: 100px;">Qty</th>
                  <th style="min-width: 100px;">Price</th>
                  <th style="min-width: 100px;">Value USD</th>
                  <th style="min-width: 160px;">Budget</th>
                  <th style="min-width: 120px;">Campus</th>
                  <th style="min-width: 120px;">Dept</th>
                  <th style="min-width: 80px;">Action</th>
                </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, index) in form.items" :key="index">
                    <td>{{ item.product_code }}</td>
                    <td>{{ item.product_description }}</td>
                    <td>{{ item.unit_name }}</td>
                    <td><textarea :name="`items[${index}][description]`" v-model="item.description" class="form-control form-control-sm"></textarea></td>
                    <td>
                      <select :name="`items[${index}][currency]`" v-model="item.currency" class="form-control form-control-sm">
                        <option value="">Select</option>
                        <option value="USD">USD</option>
                        <option value="KHR">KHR</option>
                      </select>
                    </td>
                    <td><input type="number" :name="`items[${index}][exchange_rate]`" v-model.number="item.exchange_rate" class="form-control form-control-sm" /></td>
                    <td><input type="number" :name="`items[${index}][quantity]`" v-model.number="item.quantity" class="form-control form-control-sm" /></td>
                    <td><input type="number" :name="`items[${index}][unit_price]`" v-model.number="item.unit_price" class="form-control form-control-sm" /></td>
                    <td><input type="text" class="form-control form-control-sm" 
                      :value="(item.quantity * item.unit_price / (item.currency === 'KHR' ? (item.exchange_rate || 1) : 1)).toLocaleString('en-US', { minimumFractionDigits: 4, maximumFractionDigits: 4 })"
                      readonly
                    /></td>
                    <td><select class="form-control budget-select" :data-index="index"></select></td>
                    <td>
                      <select multiple class="form-control campus-select" :data-index="index"></select>
                    </td>
                    <td>
                      <select multiple class="form-control department-select" :data-index="index"></select>
                    </td>
                    <td class="text-center">
                      <button @click.prevent="removeItem(index)" class="btn btn-danger btn-sm">
                        <i class="fal fa-trash"></i>
                      </button>
                    </td>
                  </tr>
                  <tr v-if="!form.items.length">
                    <td colspan="13" class="text-center text-muted py-4">No items added</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- ROW 3: Attachments -->
          <div class="border rounded p-3 mb-4">
                  <div class="form-group col-12">
                    <label class="font-weight-bold">📎 Attachment</label>
                    <div class="input-group mb-2">
                      <input 
                        type="file" 
                        class="d-none" 
                        ref="attachmentInput" 
                        multiple 
                        accept=".pdf,.doc,.docx,.jpg,.png"
                        @change="onFileChange"
                      />
                      <button type="button" class="btn btn-outline-secondary flex-fill" @click="$refs.attachmentInput.click()">
                        <i class="fal fa-file-upload"></i> {{ fileLabel }}
                      </button>
                    </div>

                    <!-- Existing Files -->
                    <div v-if="existingFileUrls.length">
                      <small class="text-muted">Existing Files:</small>
                      <div v-for="(file, i) in existingFileUrls" :key="file.id" class="d-flex align-items-center mb-1">
                        <button
                          type="button"
                          class="btn btn-sm btn-outline-info me-1"
                          @click="openFileViewer(file.url, file.name)"
                        >
                          📄 {{ file.name }}
                        </button>
                        <button type="button" class="btn btn-sm btn-danger" @click="removeFile(i, true)">
                          <i class="fal fa-trash"></i>
                        </button>
                      </div>
                    </div>

                    <!-- New Files -->
                    <div v-if="newFiles.length">
                      <small class="text-muted">New Files:</small>
                      <div v-for="(f, i) in newFiles" :key="i" class="d-flex align-items-center mb-1">
                        <span class="mr-2">📄 {{ f.name }}</span>
                        <button type="button" class="btn btn-sm btn-danger" @click="removeNewFile(i)">
                          <i class="fal fa-trash"></i>
                        </button>
                      </div>
                    </div>
                  </div>
          </div>

          <!-- ROW 4: Approvals -->
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
                    <td><select class="form-control approval-type-select" :data-index="index" v-model="approval.request_type" :name="`approvals[${index}][request_type]`"></select></td>
                    <td><select class="form-control user-select" :data-index="index" v-model="approval.user_id" :name="`approvals[${index}][user_id]`" :disabled="!approval.request_type"></select></td>
                    <td class="text-center"><button @click.prevent="removeApproval(index)" class="btn btn-danger btn-sm"><i class="fal fa-trash"></i></button></td>
                  </tr>
                </tbody>
              </table>
            </div>
            <button @click.prevent="addApproval" class="btn btn-outline-primary btn-sm mt-2"><i class="fal fa-plus"></i> Add Approval</button>
          </div>

          <!-- SUBMIT -->
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

    <!-- Product Modal -->
    <BaseModal
      id="productModal"
      title="Select Product"
      size="xl"
      v-model="showProductModal"
      :loading="isLoadingProducts"
    >
      <!-- BODY -->
      <template #body>
        <div v-if="isLoadingProducts" class="text-center py-4">
          <div class="spinner-border text-primary"></div>
        </div>

        <table
          v-show="!isLoadingProducts"
          id="productTable"
          class="table table-bordered table-striped table-sm mb-0"
          style="width: 100%"
        >
          <thead class="thead-light">
            <tr>
              <th style="width: 20%;">Code</th>
              <th style="width: 40%;">Description</th>
              <th style="width: 10%;">UoM</th>
              <th style="width: 20%;">Avg Price</th>
              <th style="width: 10%;">Stock Onhand</th>
              <th style="width: 10%;">Select</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </template>

      <!-- FOOTER -->
      <template #footer>
        <button type="button" class="btn btn-secondary" @click="showProductModal = false">
          Close
        </button>
      </template>
    </BaseModal>


    <!-- File Viewer Modal -->
  <FileViewerModal ref="viewerRef" />

  </div>
</template>


<script setup>
import { ref, computed, onMounted, nextTick } from 'vue';
import axios from 'axios';
import { initSelect2, destroySelect2 } from '@/Utils/select2';
import { showAlert } from '@/Utils/bootbox';
import FileViewerModal from '../Reusable/FileViewerModal.vue';
import BaseModal from '@/components/Reusable/BaseModal.vue';

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
const showProductModal = ref(false)
const isLoadingProducts = ref(false);
const isEditMode = ref(!!props.purchaseRequestId);

const products = ref([]);
const campuses = ref([]);
const departments = ref([]);
const fileLabel = ref('Choose file(s)...');
const existingFileUrls = ref([]);
const existingFileIds = ref([]);
const newFiles = ref([]);
const fileInput = ref(null);
const viewerRef = ref(null);

const form = ref({
  deadline_date: '',
  purpose: '',
  is_urgent: false,
  file: [],
  existing_file_ids: [],
  items: [],
  created_by: props.requester?.id || '',
  position_id: props.requester?.current_position_id || '',
  approvals: []
});


const openFileViewer = (url, name) => {
  if (viewerRef.value) viewerRef.value.openModal(url, name)
}

const budgetCodes = ref([
  { id: 1, code: '25-CEN-EAL-0005', name: 'Office Supplies' },
  { id: 2, code: 'BUD-002', name: 'IT Equipment' },
  { id: 3, code: 'BUD-003', name: 'Maintenance' }
]);

const approvalTypes = [
  { id: 'initial', text: 'Initial' },
  { id: 'approve', text: 'Approve' },
  { id: 'check', text: 'Check' },
  { id: 'verify', text: 'Verify' }
];

const approvalUsers = ref({ initial: [], check: [], verify: [], approve: [] });

// -------------------- Computed --------------------
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

// -------------------- Helpers --------------------
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

const navigateToList = () => (window.location.href = '/purchase-requests');

// -------------------- File Handling --------------------
const onFileChange = (e) => {
  const files = Array.from(e.target.files);
  newFiles.value.push(...files);
  fileLabel.value = newFiles.value.length > 1
    ? `${newFiles.value.length} files selected`
    : files[0]?.name || 'Choose file(s)...';
  e.target.value = null; // allow re-selecting same file
};

const removeNewFile = (index) => {
  newFiles.value.splice(index, 1);
  fileLabel.value = newFiles.value.length
    ? `${newFiles.value.length} file(s) selected`
    : 'Choose file(s)...';
};

const removeFile = (index, isExisting = false) => {
  if (isExisting) {
    existingFileUrls.value.splice(index, 1);
    existingFileIds.value.splice(index, 1);
  } else {
    newFiles.value.splice(index, 1);
    fileLabel.value = newFiles.value.length
      ? `${newFiles.value.length} file(s) selected`
      : 'Choose file(s)...';
  }
};

// -------------------- Load PR --------------------
const loadPurchaseRequest = async () => {
  if (!isEditMode.value) return;
  try {
    const { data } = await axios.get(`/api/purchase-requests/${props.purchaseRequestId}/edit`);
    const pr = data.data;

    form.value.deadline_date = pr.deadline_date || '';
    form.value.purpose = pr.purpose || '';
    form.value.is_urgent = pr.is_urgent === 1;
    existingFileUrls.value = pr.files || [];
    existingFileIds.value = (pr.files || []).map(f => f.id);

    form.value.items = (pr.items || []).map(item => createItem({
      ...item,
      campus_ids: item.campus_ids || [props.userDefaultCampus?.id],
      department_ids: item.department_ids || [props.userDefaultDepartment?.id],
    }));

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
  } catch {
    showAlert('Error', 'Failed to load purchase request for edit mode.', 'danger');
  }
};

// -------------------- Import Items --------------------
const importItems = async () => {
  if (isImporting.value) return;
  if (!fileInput.value?.files?.length) return showAlert('Error', 'Please select a file.', 'danger');

  isImporting.value = true;
  const formData = new FormData();
  formData.append('file', fileInput.value.files[0]);

  try {
    const { data } = await axios.post('/api/purchase-requests/import-items', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });
    if (data.data?.items?.length) {
      form.value.items = data.data.items.map(item => createItem({
        ...item,
        unit_name: item.unit_name || 'N/A',
        exchange_rate: item.exchange_rate || 1
      }));
      await nextTick(initItemSelects);
      showAlert('Success', 'Items imported successfully.', 'success');
      fileInput.value.value = '';
      fileLabel.value = 'Choose file(s)...';
    } else {
      const errors = data.errors || [data.message || 'Unknown error'];
      showAlert('Import Errors', errors.join('<br>'), 'danger');
    }
  } catch (err) {
    const errors = err.response?.data?.errors || [err.response?.data?.message || 'Import failed.'];
    showAlert('Error', errors.join('<br>'), 'danger');
  } finally { isImporting.value = false; }
};


const removeItem = (index) => {
  ['campus', 'department', 'budget'].forEach(type => {
    const el = document.querySelector(`.${type}-select[data-index="${index}"]`);
    destroySelect2(el);
  });
  form.value.items.splice(index, 1);
  nextTick(initItemSelects);
};

// -------------------- Select2 Init --------------------
const initSelect = (index, type) => {
  const el = document.querySelector(`.${type}-select[data-index="${index}"]`);
  if (!el) return;

  destroySelect2(el);

  // Use the data from your API
  const dataList = type === 'campus' ? campuses.value : departments.value;

  // Populate the select options
  el.innerHTML = (dataList || [])
    .map(d => `<option value="${d.id}">${d.text}</option>`)
    .join('');

  // Pre-select values from the form
  const selectedIds = form.value.items[index][`${type}_ids`] || [];
  initSelect2(
    el,
    {
      multiple: true,
      allowClear: true,
      width: '100%',
      placeholder: `Select ${type}`,
      value: selectedIds.map(String) // convert to string for Select2
    },
    val => {
      form.value.items[index][`${type}_ids`] = val ? val.map(Number) : [];
    }
  );
};


const initBudgetSelect = (index) => {
  const el = document.querySelector(`.budget-select[data-index="${index}"]`);
  if (!el) return;
  destroySelect2(el);
  el.innerHTML = budgetCodes.value.map(b => `<option value="${b.id}">${b.code}</option>`).join('');
  initSelect2(el, { width: '100%', allowClear: false, placeholder: 'Select Budget', value: String(form.value.items[index].budget_code_id) },
    val => form.value.items[index].budget_code_id = val ? Number(val) : null);
};

const initItemSelects = () => {
  form.value.items.forEach((_, i) => {
    ['campus', 'department'].forEach(t => initSelect(i, t));
    initBudgetSelect(i);
  });
};

// -------------------- Approvals --------------------
const fetchApprovalUsers = async () => {
  try {
    const { data } = await axios.get('/api/purchase-requests/get-approval-users');
    approvalUsers.value = {
      initial: data.initial || [],
      check: data.check || [],
      verify: data.verify || [],
      approve: data.approve || []
    };
  } catch (error) {
    console.error(error);
    approvalUsers.value = { initial: [], check: [], verify: [], approve: [] };
  }
};

const populateUserSelect = async (approval, userEl) => {
  let users = approvalUsers.value[approval.request_type] || [];

  // Ensure current selected user exists in the list
  if (approval.user_id && !users.find(u => u.id === approval.user_id)) {
    users.unshift({ id: approval.user_id, name: approval.name || 'Unknown' });
  }

  approval.availableUsers = users;

  destroySelect2(userEl);
  userEl.innerHTML = users.map(u => `<option value="${u.id}">${u.name}</option>`).join('');

  initSelect2(userEl, {
    width: '100%',
    allowClear: true,
    placeholder: 'Select User',
    value: String(approval.user_id)
  }, val => approval.user_id = val ? Number(val) : '');
};

const initApprovalSelect = async (i) => {
  const approval = form.value.approvals[i];
  if (!approval) return;

  const typeEl = document.querySelector(`.approval-type-select[data-index="${i}"]`);
  const userEl = document.querySelector(`.user-select[data-index="${i}"]`);
  if (!typeEl || !userEl) return;

  destroySelect2(typeEl);
  typeEl.innerHTML = approvalTypes.map(t => `<option value="${t.id}">${t.text}</option>`).join('');

  initSelect2(typeEl, { width: '100%', allowClear: true, placeholder: 'Select Type', value: approval.request_type }, 
    async val => {
      approval.request_type = val || '';
      await populateUserSelect(approval, userEl);
    }
  );

  // Pre-populate user select if type is already selected
  if (approval.request_type) await populateUserSelect(approval, userEl);
};

const initApprovalSelects = () => form.value.approvals.forEach((_, i) => initApprovalSelect(i));

const addApproval = async () => {
  form.value.approvals.push({ user_id: '', request_type: '', availableUsers: [] });
  await nextTick(initApprovalSelects);
};

const removeApproval = async (i) => {
  form.value.approvals.splice(i, 1);
  await nextTick(initApprovalSelects);
};


// -------------------- Product Table --------------------
let productsTable = null;

// -------------------- Product Modal --------------------
const openProductsModal = async () => {
  // Check warehouse and date
  // if (!form.value.warehouse_id || !form.value.transaction_date) {
  //   showAlert('Warning', 'Please select Warehouse and Count Date first.', 'warning');
  //   return;
  // }

  await nextTick();
  const table = $('#productModal').find('table');

  // Initialize DataTable only once
  if (!productsTable) {
    productsTable = table.DataTable({
      serverSide: true,
      processing: true,
      responsive: true,
      autoWidth: false,
      ajax: {
        url: '/api/purchase-requests/get-products',
        type: 'GET',
        data: function(d) {
          return $.extend({}, d, {
            warehouse_id: form.value.warehouse_id,
            cutoff_date: form.value.transaction_date
          });
        }
      },
      columns: [
        { data: 'item_code' },
        { data: null, render: (d, t, r) => r.description || '' },
        { data: 'unit_name' },
        {
          data: 'average_price',
          className: 'text-right',
          render: d => d != null ? d : '-',
          orderable: false  // disable sorting
        },
        {
          data: 'stock_on_hand',
          className: 'text-right',
          render: d => d != null ? d : '-',
          orderable: false  // disable sorting
        },
        {
          data: null,
          orderable: false,
          searchable: false,
          className: 'text-center',
          render: (data, type, row) => {
            return `<button class="btn btn-sm btn-primary select-product" data-id="${row.id}">Select</button>`;
          }
        }
      ]
    });
  } else {
    productsTable.ajax.reload();
  }

  // Handle Select button click
  $('#productModal').off('click', '.select-product').on('click', '.select-product', function() {
    const productId = $(this).data('id');
    const product = productsTable.rows().data().toArray().find(p => p.id === productId);

    if (!product) {
      return showAlert('Error', 'Product not found', 'danger');
    }

    // Check if product already added
    const existingIds = new Set(form.value.items.map(i => i.product_id));
    if (existingIds.has(product.id)) {
      showAlert('Warning', 'Product already added', 'warning');
      return;
    }

    // Add product to items
    form.value.items.push(createItem({
      product_id: product.id,
      product_code: product.item_code,
      product_description: product.description,
      unit_name: product.unit_name,
      currency: 'USD',
      exchange_rate: 4000,
      quantity: 1,
      unit_price: 0,
    }));

    nextTick(initItemSelects);
    showAlert('Success', 'Product added to Item List.', 'success');

    // $('#productModal').modal('hide'); // Close modal
  });

  showProductModal.value = true;
};



// -------------------- Datepicker --------------------
const initDatepicker = () => {
  $('#deadline_date').datepicker({
    format: 'yyyy-mm-dd',
    autoclose: true,
    todayHighlight: true,
    orientation: 'bottom left'
  }).on('changeDate', e => {
    form.value.deadline_date = e.format()
  })
}

// -------------------- Submit --------------------
const submitForm = async () => {
  if (!isFormValid.value) return showAlert('Error', 'Form is incomplete', 'danger');
  isSubmitting.value = true;
  try {
    const fd = new FormData();
    fd.append('deadline_date', form.value.deadline_date || '');
    fd.append('purpose', form.value.purpose || '');
    fd.append('is_urgent', form.value.is_urgent ? 1 : 0);
    if (isEditMode.value) fd.append('_method', 'PUT');

    newFiles.value.forEach(f => fd.append('file[]', f));
    existingFileIds.value.forEach((id, idx) => fd.append(`existing_file_ids[${idx}]`, id));

    form.value.items.forEach((i, index) => {
      if (i.id) fd.append(`items[${index}][id]`, i.id);
      fd.append(`items[${index}][product_id]`, i.product_id);
      fd.append(`items[${index}][quantity]`, i.quantity);
      fd.append(`items[${index}][unit_price]`, i.unit_price);
      fd.append(`items[${index}][description]`, i.description || '');
      fd.append(`items[${index}][currency]`, i.currency || '');
      fd.append(`items[${index}][exchange_rate]`, i.exchange_rate || '');
      fd.append(`items[${index}][budget_code_id]`, i.budget_code_id || '');
      i.campus_ids.forEach((c, ci) => fd.append(`items[${index}][campus_ids][${ci}]`, c));
      i.department_ids.forEach((d, di) => fd.append(`items[${index}][department_ids][${di}]`, d));
    });

    form.value.approvals.forEach((a, index) => { fd.append(`approvals[${index}][user_id]`, a.user_id); fd.append(`approvals[${index}][request_type]`, a.request_type); });

    const res = await axios.post(isEditMode.value ? `/api/purchase-requests/${props.purchaseRequestId}` : '/api/purchase-requests', fd, { headers: { 'Content-Type': 'multipart/form-data' } });

    await showAlert('Success', isEditMode.value ? 'Updated successfully.' : 'Created successfully.', 'success');
    emit('submitted', res.data.data);
    navigateToList();

  } catch (err) {
    const errors = err.response?.data?.errors;
    if (errors) showAlert('Validation Error', Object.values(errors).flat().join('<br>'), 'danger');
    else showAlert('Error', err.response?.data?.message || err.message, 'danger');
  } finally { isSubmitting.value = false; }
};

// -------------------- Lifecycle --------------------
onMounted(async () => {
  try {
    const [campusRes, deptRes] = await Promise.all([
      axios.get('/api/main-value-lists/get-campuses'),
      axios.get('/api/main-value-lists/get-departments'),
      // axios.get('/api/budgets/get-budget-items'),
    ]);

    campuses.value = campusRes.data || [];
    departments.value = deptRes.data || [];
  } catch (err) {
    campuses.value = [];
    departments.value = [];
    console.error('Failed to fetch campuses/departments', err);
  }

  await fetchApprovalUsers();  // ✅ fetch all approval users first
  initDatepicker();

  await loadPurchaseRequest(); // load items for edit mode

  await nextTick(() => {
    initItemSelects();
    initApprovalSelects();
  });
});

</script>

<!-- <style>
.select2-container--default .select2-selection--single {
  background-image: none !important;
  background-color: #fff;
  border: 1px solid #ced4da;
  height: calc(1.5em + .75rem + 2px);
  display: flex;
  align-items: center;
}

.select2-container--default .select2-selection--single .select2-selection__arrow {
  display: none !important;
}

/* ===== Select2 Multiple Styling ===== */

/* Hide arrow */
.select2-container--default .select2-selection--multiple .select2-selection__arrow {
  display: none !important;
}

/* Clean input style */
.select2-container--default .select2-selection--multiple {
  border: 1px solid #ced4da !important;
  min-height: 38px !important;
  padding: 4px !important;
  border-radius: 4px !important;
  display: flex;
  flex-wrap: wrap;
  cursor: text;
}

/* Tags */
.select2-container--default .select2-selection--multiple .select2-selection__choice {
  background-color: #0d6efd !important;
  color: #fff !important;
  border: none !important;
  padding: 2px 6px !important;
  margin-top: 2px !important;
  margin-bottom: 2px !important;
  border-radius: 4px !important;
  font-size: 12px !important;
}

/* Close icon */
.select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
  color: #fff !important;
  margin-right: 2px !important;
}

/* Fix search input spacing */
.select2-container--default .select2-selection--multiple .select2-search__field {
  margin-top: 0 !important;
}

</style> -->