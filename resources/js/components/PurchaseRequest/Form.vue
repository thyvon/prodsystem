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
            <div class="table-responsive" style="max-height: 400px;">
              <table class="table table-bordered table-sm table-hover">
                <thead class="thead-light">
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

const props = defineProps({
  purchaseRequestId: Number,
  requester: Object,
  userDefaultDepartment: Object,
  userDefaultCampus: Object
});
const emit = defineEmits(['submitted']);

const isEditMode = ref(!!props.purchaseRequestId);

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

const products = ref([]);
const campuses = ref([]);
const departments = ref([]);
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

const fileLabel = ref('Choose file(s)...');
const existingFileUrls = ref([]);

const totalAmount = computed(() => {
  let totalKHR = 0, totalUSD = 0, totalKHRinUSD = 0;
  form.value.items.forEach(i => {
    const amount = Number(i.quantity) * Number(i.unit_price);
    if (i.currency === 'KHR') {
      totalKHR += amount;
      totalKHRinUSD += amount / (i.exchange_rate || 1);
    } else if (i.currency === 'USD') {
      totalUSD += amount;
    }
  });
  const parts = [];
  if (totalKHR) parts.push(`KHR = ${totalKHR.toLocaleString('en-US', { minimumFractionDigits: 2 })}`);
  if (totalUSD) parts.push(`USD = ${totalUSD.toLocaleString('en-US', { minimumFractionDigits: 2 })}`);
  if (totalKHRinUSD || totalUSD) parts.push(`Total as USD = ${(totalUSD + totalKHRinUSD).toLocaleString('en-US', { minimumFractionDigits: 2 })}`);
  return parts.join(' | ');
});

const isFormValid = computed(() =>
  form.value.purpose &&
  form.value.items.length &&
  !form.value.items.some(i => !i.product_id)
);

// --- Create Item Helper ---
const createItem = (data = {}) => ({
  product_id: data.product_id || '',
  product_code: data.product_code || '',
  product_description: data.product_description || '',
  unit_name: data.unit_name || '',
  quantity: data.quantity || 0,
  unit_price: data.unit_price || 0,
  currency: data.currency || '',
  exchange_rate: data.exchange_rate ? data.exchange_rate : null,
  description: data.description || '',
  campus_ids: data.campus_ids?.length ? data.campus_ids : [props.userDefaultCampus?.id],
  department_ids: data.department_ids?.length ? data.department_ids : [props.userDefaultDepartment?.id],
  budget_code_id: data.budget_code_id || budgetCodes.value[0]?.id || ''
});

// --- Load Purchase Request for Edit ---
const loadPurchaseRequest = async () => {
  if (!isEditMode.value) return;

  try {
    const { data } = await axios.get(`/api/purchase-requests/${props.purchaseRequestId}/edit`);
    const pr = data.data;

    form.value.deadline_date = pr.deadline_date || '';
    form.value.purpose = pr.purpose || '';
    form.value.is_urgent = pr.is_urgent === 1;
    existingFileUrls.value = pr.files || [];

    // Map items
    form.value.items = (pr.items || []).map(i => createItem(i));

    // Map approvals
    form.value.approvals = (pr.approvals || []).map(a => ({
      user_id: a.user_id || '',
      name: a.name || '',
      request_type: a.request_type || '',
      availableUsers: []
    }));

    await nextTick(() => {
      initDatepicker();
      initItemSelects();
      initApprovalSelects();
    });

  } catch (err) {
    showAlert('Error', 'Failed to load purchase request.', 'danger');
  }
};

// --- File Input ---
const onFileChange = e => {
  const files = e.target.files;
  form.value.file = files;
  fileLabel.value = files?.length > 1 ? `${files.length} files selected` : files[0]?.name || 'Choose file(s)...';
};

// --- Select2 Init ---
const initSelectWithData = (el, dataList, multiple = false, valKey = 'id', labelKey = 'name', onChange) => {
  if (!el) return;
  destroySelect2(el);
  $(el).empty();
  dataList.forEach(d => $(el).append(`<option value="${d[valKey]}">${d[labelKey]}</option>`));
  initSelect2(el, { multiple, allowClear: true, width: '100%', placeholder: 'Select' }, val => onChange(val ? (multiple ? val.map(Number) : Number(val)) : multiple ? [] : null));
  if (!multiple) $(el).val('').trigger('change.select2');
};

// --- Items ---
const initItemSelects = () => {
  form.value.items.forEach((item, i) => {
    initSelectWithData(document.querySelector(`.campus-select[data-index="${i}"]`), campuses.value, true, 'id', 'short_name', val => item.campus_ids = val);
    initSelectWithData(document.querySelector(`.department-select[data-index="${i}"]`), departments.value, true, 'id', 'short_name', val => item.department_ids = val);
    initSelectWithData(document.querySelector(`.budget-select[data-index="${i}"]`), budgetCodes.value, false, 'id', 'code', val => item.budget_code_id = val);
    // Set current values
    $('.campus-select[data-index="'+i+'"]').val(item.campus_ids.map(String)).trigger('change.select2');
    $('.department-select[data-index="'+i+'"]').val(item.department_ids.map(String)).trigger('change.select2');
    $('.budget-select[data-index="'+i+'"]').val(item.budget_code_id ? String(item.budget_code_id) : '').trigger('change.select2');
  });
};

// --- Approvals ---
const fetchUsersForApproval = async (type) => {
  if (!type || usersForApproval.value[type]?.length) return;
  try {
    const { data } = await axios.get('/api/purchase-requests/get-approval-users', { params: { request_type: type } });
    usersForApproval.value[type] = data.data || [];
  } catch {
    usersForApproval.value[type] = [];
  }
};

const initApprovalSelect = async (i) => {
  const approval = form.value.approvals[i];
  if (!approval) return;

  const typeEl = document.querySelector(`.approval-type-select[data-index="${i}"]`);
  const userEl = document.querySelector(`.user-select[data-index="${i}"]`);
  if (!typeEl || !userEl) return;

  // --- Type select ---
  destroySelect2(typeEl);
  typeEl.innerHTML = '<option value="">Select Type</option>';
  approvalTypes.forEach(t => $(typeEl).append(`<option value="${t.id}">${t.text}</option>`));
  $(typeEl).val(approval.request_type || '').trigger('change.select2');

  initSelect2(typeEl, { width: '100%', allowClear: true }, async val => {
    approval.request_type = val || '';
    await populateUserSelect(approval, userEl);
  });

  // Init user select if already has type
  if (approval.request_type) await populateUserSelect(approval, userEl);
};

const populateUserSelect = async (approval, userEl) => {
  await fetchUsersForApproval(approval.request_type);
  let users = usersForApproval.value[approval.request_type] || [];

  // Add old user if missing
  if (approval.user_id && !users.find(u => u.id === approval.user_id)) {
    users.unshift({ id: approval.user_id, name: approval.name || 'Unknown' });
  }
  approval.availableUsers = users;

  destroySelect2(userEl);
  userEl.innerHTML = '<option value="">Select User</option>';
  users.forEach(u => $(userEl).append(`<option value="${u.id}">${u.name}</option>`));

  $(userEl).val(approval.user_id ? String(approval.user_id) : '').trigger('change.select2');
  initSelect2(userEl, { width: '100%', allowClear: true }, val => approval.user_id = val ? Number(val) : '');
};


const initApprovalSelects = () => form.value.approvals.forEach((_, i) => initApprovalSelect(i));
const addApproval = async () => { form.value.approvals.push({ user_id: '', name: '', request_type: '', availableUsers: [] }); await nextTick(initApprovalSelects); };
const removeApproval = async i => { form.value.approvals.splice(i, 1); await nextTick(initApprovalSelects); };

// --- Datepicker ---
const initDatepicker = () => {
  $('.datepicker').datepicker({ format: 'yyyy-mm-dd', autoclose: true }).on('changeDate', e => form.value.deadline_date = e.format('yyyy-mm-dd'));
  if (form.value.deadline_date) $('.datepicker').datepicker('update', form.value.deadline_date);
};

// --- Mounted ---
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
