<template>
  <div class="container-fluid">
    <form @submit.prevent="submitForm" enctype="multipart/form-data">
      <div class="card mb-0">

        <!-- ═══════════════════════════════════════════════════════════════ -->
        <!-- HEADER -->
        <!-- ═══════════════════════════════════════════════════════════════ -->
        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
          <h4 class="mb-0 font-weight-bold">
            {{ isEditMode ? '✏️ Edit' : '➕ Create' }} Purchase Request
          </h4>
          <button type="button" class="btn btn-outline-primary btn-sm" @click="navigateToList">
            <i class="fal fa-backward"></i> Back
          </button>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════ -->
        <!-- BODY -->
        <!-- ═══════════════════════════════════════════════════════════════ -->
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
                    <label class="font-weight-bold">
                      Deadline <span class="text-danger">*</span>
                    </label>
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

            <!-- Import & Add Product -->
            <div class="form-row mb-3">

              <!-- Import -->
              <div class="form-group col-md-4">
                <label class="font-weight-bold">📥 Import Items</label>
                <div class="input-group">
                  <input
                    type="file"
                    class="d-none"
                    ref="fileInput"
                    accept=".xlsx,.xls,.csv"
                  />
                  <button
                    type="button"
                    class="btn btn-outline-secondary flex-fill"
                    @click="$refs.fileInput.click()"
                  >
                    Choose file
                  </button>
                  <button
                    type="button"
                    class="btn btn-primary ml-2"
                    @click="importItems"
                    :disabled="isImporting || !fileInput?.files?.length"
                  >
                    <span v-if="isImporting" class="spinner-border spinner-border-sm mr-1"></span>
                    Import
                  </button>
                  <a
                    class="btn btn-success ml-2"
                    href="/sampleExcel/purchase_request_item_sample.xlsx"
                    download
                  >
                    <i class="fal fa-file-excel"></i>
                  </a>
                </div>
              </div>

              <!-- Add Product -->
              <div class="form-group col-md-8">
                <label class="font-weight-bold">➕ Add Product</label>
                <button
                  type="button"
                  class="btn btn-primary btn-block"
                  @click="openProductsModal"
                  :disabled="isLoadingProducts"
                >
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
                    <th style="min-width: 120px;">Item Code</th>
                    <th class="d-none d-md-table-cell" style="min-width: 200px;">Description</th>
                    <th style="min-width: 60px;">UoM</th>
                    <th class="d-none d-md-table-cell" style="min-width: 140px;">Remarks</th>
                    <th style="min-width: 70px;">Currency</th>
                    <th class="d-none d-md-table-cell" style="min-width: 80px;">Ex. Rate</th>
                    <th style="min-width: 60px;">Qty</th>
                    <th style="min-width: 70px;">Price</th>
                    <th class="d-none d-lg-table-cell" style="min-width: 80px;">Value USD</th>
                    <th class="d-none d-lg-table-cell" style="min-width: 120px;">Budget</th>
                    <th class="d-none d-lg-table-cell" style="min-width: 100px;">Campus</th>
                    <th class="d-none d-lg-table-cell" style="min-width: 100px;">Dept</th>
                    <th style="min-width: 60px;">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, index) in form.items" :key="index">
                    <td>{{ item.product_code }}</td>
                    <td class="d-none d-md-table-cell">{{ item.product_description }}</td>
                    <td>{{ item.unit_name }}</td>
                    <td class="d-none d-md-table-cell">
                      <textarea
                        :name="`items[${index}][description]`"
                        v-model="item.description"
                        class="form-control form-control-sm"
                      ></textarea>
                    </td>
                    <td>
                      <select
                        :name="`items[${index}][currency]`"
                        v-model="item.currency"
                        class="form-control form-control-sm"
                      >
                        <option value="">Select</option>
                        <option value="USD">USD</option>
                        <option value="KHR">KHR</option>
                      </select>
                    </td>
                    <td class="d-none d-md-table-cell">
                      <input
                        type="number"
                        :name="`items[${index}][exchange_rate]`"
                        v-model.number="item.exchange_rate"
                        class="form-control form-control-sm"
                      />
                    </td>
                    <td>
                      <input
                        type="number"
                        :name="`items[${index}][quantity]`"
                        v-model.number="item.quantity"
                        class="form-control form-control-sm"
                      />
                    </td>
                    <td>
                      <input
                        type="number"
                        :name="`items[${index}][unit_price]`"
                        v-model.number="item.unit_price"
                        class="form-control form-control-sm"
                      />
                    </td>
                    <td class="d-none d-lg-table-cell">
                      <input
                        type="text"
                        class="form-control form-control-sm"
                        :value="(item.quantity * item.unit_price / (item.currency === 'KHR' ? (item.exchange_rate || 1) : 1)).toLocaleString('en-US', { minimumFractionDigits: 4, maximumFractionDigits: 4 })"
                        readonly
                      />
                    </td>
                    <td class="d-none d-lg-table-cell">
                      <select
                        class="form-control budget-select"
                        :data-index="index"
                      ></select>
                    </td>
                    <td class="d-none d-lg-table-cell">
                      <select
                        multiple
                        class="form-control campus-select"
                        :data-index="index"
                      ></select>
                    </td>
                    <td class="d-none d-lg-table-cell">
                      <select
                        multiple
                        class="form-control department-select"
                        :data-index="index"
                      ></select>
                    </td>
                    <td class="text-center">
                      <button
                        @click.prevent="removeItem(index)"
                        class="btn btn-danger btn-sm"
                      >
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
                <button
                  type="button"
                  class="btn btn-outline-secondary flex-fill"
                  @click="$refs.attachmentInput.click()"
                >
                  <i class="fal fa-file-upload"></i> {{ fileLabel }}
                </button>
              </div>

              <!-- Existing Files -->
              <div v-if="existingFileUrls.length">
                <small class="text-muted">Existing Files:</small>
                <div
                  v-for="(file, i) in existingFileUrls"
                  :key="file.id"
                  class="d-flex align-items-center mb-1"
                >
                  <button
                    type="button"
                    class="btn btn-sm btn-outline-info mr-1"
                    @click="openFileViewer(file.url, file.name)"
                  >
                    📄 {{ file.name }}
                  </button>
                  <button
                    type="button"
                    class="btn btn-sm btn-danger"
                    @click="removeFile(i, true)"
                  >
                    <i class="fal fa-trash"></i>
                  </button>
                </div>
              </div>

              <!-- New Files -->
              <div v-if="newFiles.length">
                <small class="text-muted">New Files:</small>
                <div
                  v-for="(f, i) in newFiles"
                  :key="i"
                  class="d-flex align-items-center mb-1"
                >
                  <span class="mr-2">📄 {{ f.name }}</span>
                  <button
                    type="button"
                    class="btn btn-sm btn-danger"
                    @click="removeNewFile(i)"
                  >
                    <i class="fal fa-trash"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- ROW 4: Approvals -->
          <div class="border rounded p-3 mb-4">
            <h5 class="font-weight-bold mb-3 text-primary">
              ✅ Approvals ({{ form.approvals.length }})
            </h5>

            <div class="row">
              <!-- Approval Cards -->
              <div
                v-for="(approval, aIndex) in form.approvals"
                :key="aIndex"
                class="col-12 col-md-6 col-lg-4 mb-3"
              >
                <div class="card h-100">

                  <!-- Card Header -->
                  <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="form-group mb-0" style="flex: 1;">
                      <label class="small mb-1">Request Type</label>
                      <select
                        class="form-control approval-type-select"
                        :data-index="aIndex"
                        :name="`approvals[${aIndex}][request_type]`"
                      ></select>
                    </div>

                    <button
                      @click.prevent="removeApproval(aIndex)"
                      class="btn btn-danger btn-sm ml-2"
                      style="align-self: flex-end; margin-top: auto;"
                    >
                      <i class="fal fa-trash"></i>
                    </button>
                  </div>

                  <!-- Card Body -->
                  <div class="card-body">
                    <!-- Users -->
                    <div
                      v-for="(user, uIndex) in approval.users"
                      :key="user._uid"
                      class="form-group mb-2 d-flex align-items-center"
                    >
                      <select
                        class="form-control user-select mr-2"
                        :data-aindex="aIndex"
                        :data-uindex="uIndex"
                        :name="`approvals[${aIndex}][users][${uIndex}]`"
                        :disabled="!approval.request_type"
                      ></select>

                      <button
                        @click.prevent="removeUser(aIndex, uIndex)"
                        class="btn btn-danger btn-sm"
                      >
                        <i class="fal fa-trash"></i>
                      </button>
                    </div>

                    <button
                      @click.prevent="addUser(aIndex)"
                      class="btn btn-outline-primary btn-sm mt-2"
                    >
                      <i class="fal fa-plus"></i> Add User
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Add Approval Button -->
            <div class="text-right mt-2">
              <button
                @click.prevent="addApproval"
                class="btn btn-outline-primary btn-sm"
              >
                <i class="fal fa-plus"></i> Add Approval
              </button>
            </div>
          </div>

          <!-- SUBMIT -->
          <div class="text-right">
            <button
              type="button"
              @click="navigateToList"
              class="btn btn-secondary mr-2"
            >
              <i class="fal fa-times"></i> Cancel
            </button>
            <button
              type="submit"
              :disabled="!isFormValid || isSubmitting"
              class="btn btn-primary"
            >
              <span v-if="isSubmitting" class="spinner-border spinner-border-sm mr-2"></span>
              {{ isEditMode ? 'Update' : 'Create' }} PR
            </button>
          </div>
        </div>
      </div>
    </form>

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- PRODUCT MODAL -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <BaseModal
      id="productModal"
      title="Select Product"
      size="xl"
      v-model="showProductModal"
      :loading="isLoadingProducts"
    >
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
              <th style="width: 15%;">Avg Price</th>
              <th style="width: 10%;">Stock</th>
              <th style="width: 5%;">Select</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </template>

      <template #footer>
        <button
          type="button"
          class="btn btn-secondary"
          @click="showProductModal = false"
        >
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

// ═══════════════════════════════════════════════════════════════════════════
// PROPS & EMITS
// ═══════════════════════════════════════════════════════════════════════════

const props = defineProps({
  purchaseRequestId: Number,
  requester: Object,
  userDefaultDepartment: Object,
  userDefaultCampus: Object
});

const emit = defineEmits(['submitted']);

// ═══════════════════════════════════════════════════════════════════════════
// STATE MANAGEMENT
// ═══════════════════════════════════════════════════════════════════════════

// UI State
const isSubmitting = ref(false);
const isImporting = ref(false);
const showProductModal = ref(false);
const isLoadingProducts = ref(false);
const isEditMode = ref(!!props.purchaseRequestId);

// Data Collections
const campuses = ref([]);
const departments = ref([]);
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

const approvalUsers = ref({
  initial: [],
  check: [],
  verify: [],
  approve: []
});

// File Handling State
const fileLabel = ref('Choose file(s)...');
const existingFileUrls = ref([]);
const existingFileIds = ref([]);
const newFiles = ref([]);
const fileInput = ref(null);
const viewerRef = ref(null);

// Form Data
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

// DataTable Reference
let productsTable = null;

// ═══════════════════════════════════════════════════════════════════════════
// COMPUTED PROPERTIES
// ═══════════════════════════════════════════════════════════════════════════

const totalAmount = computed(() => {
  let totalKHR = 0;
  let totalUSD = 0;
  let totalKHRinUSD = 0;

  form.value.items.forEach(item => {
    const amount = item.quantity * item.unit_price;

    if (item.currency === 'KHR') {
      totalKHR += amount;
      totalKHRinUSD += amount / (item.exchange_rate || 1);
    } else if (item.currency === 'USD') {
      totalUSD += amount;
    }
  });

  const parts = [];
  if (totalKHR) {
    parts.push(`KHR = ${totalKHR.toLocaleString('en-US', { minimumFractionDigits: 2 })}`);
  }
  if (totalUSD) {
    parts.push(`USD = ${totalUSD.toLocaleString('en-US', { minimumFractionDigits: 2 })}`);
  }
  if (totalKHRinUSD || totalUSD) {
    parts.push(`Total as USD = ${(totalUSD + totalKHRinUSD).toLocaleString('en-US', { minimumFractionDigits: 2 })}`);
  }

  return parts.length ? parts.join(' | ') : null;
});

const isFormValid = computed(() =>
  form.value.purpose &&
  form.value.items.length &&
  !form.value.items.some(item => !item.product_id)
);

// ═══════════════════════════════════════════════════════════════════════════
// HELPER FUNCTIONS
// ═══════════════════════════════════════════════════════════════════════════

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

const navigateToList = () => {
  window.location.href = '/purchase-requests';
};

// ═══════════════════════════════════════════════════════════════════════════
// FILE HANDLING
// ═══════════════════════════════════════════════════════════════════════════

const onFileChange = (e) => {
  const files = Array.from(e.target.files);
  newFiles.value.push(...files);

  fileLabel.value = newFiles.value.length > 1
    ? `${newFiles.value.length} files selected`
    : files[0]?.name || 'Choose file(s)...';

  e.target.value = null; // Allow re-selecting same file
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

const removeNewFile = (index) => {
  removeFile(index, false);
};

const openFileViewer = (url, name) => {
  if (viewerRef.value) {
    viewerRef.value.openModal(url, name);
  }
};

// ═══════════════════════════════════════════════════════════════════════════
// DATA LOADING
// ═══════════════════════════════════════════════════════════════════════════

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
    console.error('Failed to fetch approval users:', error);
    approvalUsers.value = {
      initial: [],
      check: [],
      verify: [],
      approve: []
    };
  }
};

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
    existingFileIds.value = (pr.files || []).map(f => f.id);

    // Items
    form.value.items = (pr.items || []).map(item => createItem({
      ...item,
      campus_ids: item.campus_ids || [props.userDefaultCampus?.id],
      department_ids: item.department_ids || [props.userDefaultDepartment?.id],
    }));

    // Approvals - Map from backend structure to frontend structure
    if (pr.approvals && pr.approvals.length > 0) {
      form.value.approvals = pr.approvals.map(app => ({
        request_type: app.request_type || '',
        users: (app.users || []).map(u => ({
          id: u.user_id || null,
          _uid: crypto.randomUUID()
        }))
      }));
    }

    // Initialize UI components after data is loaded
    await nextTick(() => {
      initDatepicker();
      initItemSelects();
      initApprovalSelects();
    });
  } catch (error) {
    console.error('Failed to load purchase request:', error);
    showAlert('Error', 'Failed to load purchase request for edit mode.', 'danger');
  }
};

// ═══════════════════════════════════════════════════════════════════════════
// IMPORT FUNCTIONALITY
// ═══════════════════════════════════════════════════════════════════════════

const importItems = async () => {
  if (isImporting.value) return;

  if (!fileInput.value?.files?.length) {
    return showAlert('Error', 'Please select a file.', 'danger');
  }

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
  } finally {
    isImporting.value = false;
  }
};

// ═══════════════════════════════════════════════════════════════════════════
// ITEM MANAGEMENT
// ═══════════════════════════════════════════════════════════════════════════

const removeItem = (index) => {
  ['campus', 'department', 'budget'].forEach(type => {
    const el = document.querySelector(`.${type}-select[data-index="${index}"]`);
    destroySelect2(el);
  });

  form.value.items.splice(index, 1);
  nextTick(initItemSelects);
};

// ═══════════════════════════════════════════════════════════════════════════
// SELECT2 INITIALIZATION - ITEMS
// ═══════════════════════════════════════════════════════════════════════════

const initSelect = (index, type) => {
  const el = document.querySelector(`.${type}-select[data-index="${index}"]`);
  if (!el) return;

  destroySelect2(el);

  const dataList = type === 'campus' ? campuses.value : departments.value;

  // Populate options
  el.innerHTML = (dataList || [])
    .map(d => `<option value="${d.id}">${d.text}</option>`)
    .join('');

  // Pre-select values
  const selectedIds = form.value.items[index][`${type}_ids`] || [];

  initSelect2(
    el,
    {
      multiple: true,
      allowClear: true,
      width: '100%',
      placeholder: `Select ${type}`,
      value: selectedIds.map(String)
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

  el.innerHTML = budgetCodes.value
    .map(b => `<option value="${b.id}">${b.code}</option>`)
    .join('');

  initSelect2(
    el,
    {
      width: '100%',
      allowClear: false,
      placeholder: 'Select Budget',
      value: String(form.value.items[index].budget_code_id)
    },
    val => {
      form.value.items[index].budget_code_id = val ? Number(val) : null;
    }
  );
};

const initItemSelects = () => {
  form.value.items.forEach((_, index) => {
    ['campus', 'department'].forEach(type => initSelect(index, type));
    initBudgetSelect(index);
  });
};

// ═══════════════════════════════════════════════════════════════════════════
// APPROVAL MANAGEMENT
// ═══════════════════════════════════════════════════════════════════════════

const initDefaultApprovals = () => {
  const defaultTypes = ['check', 'approve'];
  form.value.approvals = defaultTypes.map(type => ({
    request_type: type,
    users: [{ id: null, _uid: crypto.randomUUID() }]
  }));
};

const addApproval = async () => {
  form.value.approvals.push({
    request_type: '',
    users: [{ id: null, _uid: crypto.randomUUID() }]
  });
  await nextTick(initApprovalSelects);
};

const removeApproval = async (index) => {
  form.value.approvals.splice(index, 1);
  await nextTick(initApprovalSelects);
};

const addUser = async (aIndex) => {
  const approval = form.value.approvals[aIndex];
  approval.users.push({ id: null, _uid: crypto.randomUUID() });

  await nextTick(() => {
    const uIndex = approval.users.length - 1;
    const userEl = document.querySelector(`.user-select[data-aindex="${aIndex}"][data-uindex="${uIndex}"]`);
    if (userEl) {
      populateUserSelect(approval, aIndex, uIndex, userEl);
    }
  });
};

const removeUser = async (aIndex, uIndex) => {
  form.value.approvals[aIndex].users.splice(uIndex, 1);
  await nextTick(() => {
    initApprovalSelect(aIndex);
  });
};

// ═══════════════════════════════════════════════════════════════════════════
// SELECT2 INITIALIZATION - APPROVALS
// ═══════════════════════════════════════════════════════════════════════════

const populateUserSelect = (approval, aIndex, uIndex, userEl) => {
  if (!userEl) return;

  let users = approvalUsers.value[approval.request_type] || [];

  // Keep current selected user if missing from API list
  const currentUserId = approval.users[uIndex]?.id;
  if (currentUserId && !users.find(u => u.id === currentUserId)) {
    users = [{ id: currentUserId, name: 'Unknown User' }, ...users];
  }

  destroySelect2(userEl);

  // Populate options with empty first option
  userEl.innerHTML = '<option value=""></option>' +
    users.map(u => `<option value="${u.id}">${u.name}</option>`).join('');

  // Set value in DOM before initializing Select2
  if (currentUserId) {
    userEl.value = String(currentUserId);
  }

  // Initialize Select2
  initSelect2(
    userEl,
    {
      width: '100%',
      allowClear: true,
      placeholder: 'Select User',
      value: currentUserId ? String(currentUserId) : null
    },
    val => {
      approval.users[uIndex].id = val ? Number(val) : null;
    }
  );
};

const initApprovalSelect = async (aIndex) => {
  const approval = form.value.approvals[aIndex];
  if (!approval) return;

  // Initialize type select
  const typeEl = document.querySelector(`.approval-type-select[data-index="${aIndex}"]`);
  if (typeEl) {
    destroySelect2(typeEl);

    // Add empty option first, then the actual options
    typeEl.innerHTML = '<option value=""></option>' +
      approvalTypes
        .map(t => `<option value="${t.id}">${t.text}</option>`)
        .join('');

    // Set value in DOM before initializing Select2
    if (approval.request_type) {
      typeEl.value = approval.request_type;
    }

    initSelect2(
      typeEl,
      {
        width: '100%',
        allowClear: true,
        placeholder: 'Select Type',
        value: approval.request_type || null
      },
      async val => {
        approval.request_type = val || '';

        // Re-initialize all user selects when type changes
        await nextTick(() => {
          approval.users.forEach((_, uIndex) => {
            const userEl = document.querySelector(`.user-select[data-aindex="${aIndex}"][data-uindex="${uIndex}"]`);
            if (userEl) {
              populateUserSelect(approval, aIndex, uIndex, userEl);
            }
          });
        });
      }
    );
  }

  // Initialize all user selects
  await nextTick(() => {
    approval.users.forEach((_, uIndex) => {
      const userEl = document.querySelector(`.user-select[data-aindex="${aIndex}"][data-uindex="${uIndex}"]`);
      if (userEl) {
        populateUserSelect(approval, aIndex, uIndex, userEl);
      }
    });
  });
};

const initApprovalSelects = () => {
  form.value.approvals.forEach((_, index) => initApprovalSelect(index));
};

// ═══════════════════════════════════════════════════════════════════════════
// PRODUCT MODAL & DATATABLE
// ═══════════════════════════════════════════════════════════════════════════

const openProductsModal = async () => {
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
        {
          data: null,
          render: (d, t, r) => r.description || ''
        },
        { data: 'unit_name' },
        {
          data: 'average_price',
          className: 'text-right',
          render: d => d != null ? d : '-',
          orderable: false
        },
        {
          data: 'stock_on_hand',
          className: 'text-right',
          render: d => d != null ? d : '-',
          orderable: false
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
  });

  showProductModal.value = true;
};

// ═══════════════════════════════════════════════════════════════════════════
// DATEPICKER
// ═══════════════════════════════════════════════════════════════════════════

const initDatepicker = () => {
  $('#deadline_date').datepicker({
    format: 'yyyy-mm-dd',
    autoclose: true,
    todayHighlight: true,
    orientation: 'bottom left'
  }).on('changeDate', e => {
    form.value.deadline_date = e.format();
  });
};

// ═══════════════════════════════════════════════════════════════════════════
// FORM SUBMISSION
// ═══════════════════════════════════════════════════════════════════════════

const submitForm = async () => {
  if (!isFormValid.value) {
    return showAlert('Error', 'Form is incomplete', 'danger');
  }

  isSubmitting.value = true;

  try {
    const fd = new FormData();

    // Basic fields
    fd.append('deadline_date', form.value.deadline_date || '');
    fd.append('purpose', form.value.purpose || '');
    fd.append('is_urgent', form.value.is_urgent ? 1 : 0);

    if (isEditMode.value) {
      fd.append('_method', 'PUT');
    }

    // Files
    newFiles.value.forEach(f => fd.append('file[]', f));
    existingFileIds.value.forEach((id, idx) => {
      fd.append(`existing_file_ids[${idx}]`, id);
    });

    // Items
    form.value.items.forEach((item, index) => {
      if (item.id) fd.append(`items[${index}][id]`, item.id);
      fd.append(`items[${index}][product_id]`, item.product_id);
      fd.append(`items[${index}][quantity]`, item.quantity);
      fd.append(`items[${index}][unit_price]`, item.unit_price);
      fd.append(`items[${index}][description]`, item.description || '');
      fd.append(`items[${index}][currency]`, item.currency || '');
      fd.append(`items[${index}][exchange_rate]`, item.exchange_rate || '');
      fd.append(`items[${index}][budget_code_id]`, item.budget_code_id || '');

      item.campus_ids.forEach((campusId, ci) => {
        fd.append(`items[${index}][campus_ids][${ci}]`, campusId);
      });

      item.department_ids.forEach((deptId, di) => {
        fd.append(`items[${index}][department_ids][${di}]`, deptId);
      });
    });

    // Approvals - Flatten users array to match backend expectation
    let approvalIndex = 0;
    form.value.approvals.forEach(approval => {
      approval.users.forEach(user => {
        if (user.id) { // Only send if user is selected
          fd.append(`approvals[${approvalIndex}][user_id]`, user.id);
          fd.append(`approvals[${approvalIndex}][request_type]`, approval.request_type);
          approvalIndex++;
        }
      });
    });

    const url = isEditMode.value
      ? `/api/purchase-requests/${props.purchaseRequestId}`
      : '/api/purchase-requests';

    const res = await axios.post(url, fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });

    await showAlert(
      'Success',
      isEditMode.value ? 'Updated successfully.' : 'Created successfully.',
      'success'
    );

    emit('submitted', res.data.data);
    window.location.href = `/purchase-requests/${res.data.data.id}/show`;

  } catch (err) {
    const errors = err.response?.data?.errors;

    if (errors) {
      showAlert('Validation Error', Object.values(errors).flat().join('<br>'), 'danger');
    } else {
      showAlert('Error', err.response?.data?.message || err.message, 'danger');
    }
  } finally {
    isSubmitting.value = false;
  }
};

// ═══════════════════════════════════════════════════════════════════════════
// LIFECYCLE HOOKS
// ═══════════════════════════════════════════════════════════════════════════

onMounted(async () => {
  try {
    // Fetch master data
    const [campusRes, deptRes] = await Promise.all([
      axios.get('/api/main-value-lists/get-campuses'),
      axios.get('/api/main-value-lists/get-departments'),
    ]);

    campuses.value = campusRes.data || [];
    departments.value = deptRes.data || [];
  } catch (err) {
    campuses.value = [];
    departments.value = [];
    console.error('Failed to fetch campuses/departments', err);
  }

  // Fetch approval users BEFORE loading data
  await fetchApprovalUsers();

  // Initialize datepicker
  initDatepicker();

  if (isEditMode.value) {
    // Edit mode: load existing data
    await loadPurchaseRequest();
    // Select2 initialization is handled inside loadPurchaseRequest
  } else {
    // Create mode: initialize with defaults
    initDefaultApprovals();

    // Wait for Vue to render DOM
    await nextTick();

    // Initialize Select2
    initItemSelects();
    initApprovalSelects();
  }
});
</script>
