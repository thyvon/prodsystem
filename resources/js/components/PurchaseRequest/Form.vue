<template>
  <div class="container-fluid overflow-hidden">
    <form @submit.prevent="submitForm" enctype="multipart/form-data">
      <div class="card mb-0">

        <!-- ═══════════════════════════════════════════════════════════════ -->
        <!-- HEADER -->
        <!-- ═══════════════════════════════════════════════════════════════ -->
        <FormHeader :is-edit-mode="isEditMode" @back="navigateToList" />

        <!-- ═══════════════════════════════════════════════════════════════ -->
        <!-- BODY -->
        <!-- ═══════════════════════════════════════════════════════════════ -->
        <div class="card-body">

          <!-- ROW 1: Requester + PR Info -->
          <div class="row mb-3">

            <!-- Requester Info -->
            <RequesterInfoCard :requester="requesterData" />

            <!-- PR Info -->
            <PrInfoCard :form="form" />
          </div>

          <!-- ROW 2: Import + Items Table -->
          <ItemsSection
            ref="itemsSectionRef"
            :form="form"
            :total-amount="totalAmount"
            :is-importing="isImporting"
            :is-loading-products="isLoadingProducts"
            :format-item-value-usd="formatItemValueUsd"
            @import="importItems"
            @open-products="openProductsModal"
            @remove-item="removeItem"
          />

          <!-- ROW 3: Attachments -->
          <AttachmentsSection
            ref="attachmentsSectionRef"
            :file-label="fileLabel"
            :existing-file-urls="existingFileUrls"
            :new-files="newFiles"
            @file-change="onFileChange"
            @open-viewer="openFileViewer"
            @remove-file="removeFile"
            @remove-new-file="removeNewFile"
          />

          <!-- ROW 4: Approvals -->
          <ApprovalsSection
            :form="form"
            @add-approval="addApproval"
            @remove-approval="removeApproval"
            @add-user="addUser"
            @remove-user="removeUser"
          />

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
import FormHeader from './Partials/Form/FormHeader.vue';
import RequesterInfoCard from './Partials/Form/RequesterInfoCard.vue';
import PrInfoCard from './Partials/Form/PrInfoCard.vue';
import ItemsSection from './Partials/Form/ItemsSection.vue';
import AttachmentsSection from './Partials/Form/AttachmentsSection.vue';
import ApprovalsSection from './Partials/Form/ApprovalsSection.vue';

// ═══════════════════════════════════════════════════════════════════════════
// PROPS & EMITS
// ═══════════════════════════════════════════════════════════════════════════

const props = defineProps({
  purchaseRequestId: Number,
  requester: Object,
  purchaseRequest: Object,
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
const viewerRef = ref(null);
const itemsSectionRef = ref(null);
const attachmentsSectionRef = ref(null);

// Requester Data (from props in create mode, from API in edit mode)
const requesterData = ref(props.requester || null);
const userDefaultDepartmentData = ref(props.userDefaultDepartment || null);
const userDefaultCampusData = ref(props.userDefaultCampus || null);

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

const usd2 = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const usd4 = new Intl.NumberFormat('en-US', { minimumFractionDigits: 4, maximumFractionDigits: 4 });

const formatItemValueUsd = (item) => {
  const qty = Number(item?.quantity || 0);
  const price = Number(item?.unit_price || 0);
  const rate = Number(item?.exchange_rate || 1);
  const divisor = item?.currency === 'KHR' ? rate : 1;
  const value = divisor ? (qty * price) / divisor : 0;
  return usd4.format(value);
};

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
    parts.push(`KHR = ${usd2.format(totalKHR)}`);
  }
  if (totalUSD) {
    parts.push(`USD = ${usd2.format(totalUSD)}`);
  }
  if (totalKHRinUSD || totalUSD) {
    parts.push(`Total as USD = ${usd2.format(totalUSD + totalKHRinUSD)}`);
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
  if (window.history.length > 1) {
    window.history.back()
  } else {
    window.location.href = '/purchase-requests'
  }
}

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

    // Load requester data from API (edit mode)
    if (pr.requester) {
      requesterData.value = pr.requester;
    }
    if (pr.userDefaultDepartment) {
      userDefaultDepartmentData.value = pr.userDefaultDepartment;
    }
    if (pr.userDefaultCampus) {
      userDefaultCampusData.value = pr.userDefaultCampus;
    }

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
      campus_ids: item.campus_ids || [userDefaultCampusData.value?.id],
      department_ids: item.department_ids || [userDefaultDepartmentData.value?.id],
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

  const importFileInput = itemsSectionRef.value?.importFileInput;
  if (!importFileInput?.files?.length) {
    return showAlert('Error', 'Please select a file.', 'danger');
  }

  isImporting.value = true;
  const formData = new FormData();
  formData.append('file', importFileInput.files[0]);

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

      importFileInput.value = '';
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
  form.value.items.splice(index, 1);
  // DataTable watch and drawCallback handle table updates automatically
};

const initItemSelects = () => {
  nextTick(() => {
    itemsSectionRef.value?.initializeSelect2ForItems(budgetCodes.value, campuses.value, departments.value);
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
  await nextTick(() => initApprovalSelects(form.value.approvals.length - 1));
};

const removeApproval = async (index) => {
  form.value.approvals.splice(index, 1);
  await nextTick(() => initApprovalSelects(index));
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

const initApprovalSelects = (startIndex = 0) => {
  for (let index = startIndex; index < form.value.approvals.length; index++) {
    initApprovalSelect(index);
  }
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
