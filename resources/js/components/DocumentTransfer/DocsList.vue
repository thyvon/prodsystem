<template>
  <div>
    <!-- Users Datatable -->
    <datatable
      ref="datatableRef"
      :headers="datatableHeaders"
      :fetch-url="datatableFetchUrl"
      :fetch-params="datatableParams"
      :actions="datatableActions"
      :handlers="datatableHandlers"
      :options="datatableOptions"
      @sort-change="handleSortChange"
      @page-change="handlePageChange"
      @length-change="handleLengthChange"
      @search-change="handleSearchChange"
    >
      <template #additional-header>
        <div class="btn-group" role="group">
          <button class="btn btn-success" @click="createDocumentTransfer">
            <i class="fal fa-plus"></i> New Document Transfer
          </button>
        </div>
      </template>
    </datatable>

    <!-- Reassign Receivers Modal -->
    <div class="modal fade" tabindex="-1" role="dialog" ref="reassignModal">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">
              Reassign Receivers - {{ selectedTransfer?.reference_no }}
            </h5>
            <button
              type="button"
              class="close"
              data-dismiss="modal"
              aria-label="Close"
              @click="closeReassignModal"
            >
              <span aria-hidden="true">&times;</span>
            </button>
          </div>

          <div class="modal-body">
            <div class="mb-2">
              <button class="btn btn-sm btn-success" @click="addReceiverRow">
                <i class="fal fa-plus"></i> Add Receiver
              </button>
            </div>

            <div class="table-responsive">
              <table class="table table-bordered table-sm">
                <thead class="thead-light">
                  <tr>
                    <th style="width: 70%">Assigned User</th>
                    <th style="width: 20%">Status</th>
                    <th style="width: 10%">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, index) in receiverRows" :key="row.uid">
                    <td>
                      <select
                        :ref="el => receiverSelectRefs[index] = el"
                        class="form-control select2"
                        style="width: 100%"
                        :disabled="row.disabled"
                      >
                        <option></option>
                        <option
                          v-for="user in filteredUsers(row.user_id)"
                          :key="user.id"
                          :value="user.id"
                        >
                          {{ user.name }}
                        </option>
                      </select>
                    </td>
                    <td>
                      <span v-if="row.disabled" class="badge badge-secondary">
                        {{ row.status }}
                      </span>
                      <span v-else class="badge badge-info">Pending</span>
                    </td>
                    <td>
                      <button
                        class="btn btn-sm btn-danger"
                        @click="removeReceiverRow(index)"
                        :disabled="row.disabled"
                      >
                        <i class="fal fa-trash"></i>
                      </button>
                    </td>
                  </tr>
                  <tr v-if="!receiverRows.length">
                    <td colspan="3" class="text-center text-muted">
                      No receivers assigned
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="modal-footer">
            <button
              class="btn btn-secondary"
              data-dismiss="modal"
              @click="closeReassignModal"
            >
              Cancel
            </button>
            <button
              class="btn btn-primary"
              @click="submitReassign"
              :disabled="!receiverRows.length"
            >
              Submit
            </button>
          </div>
        </div>
      </div>
    </div>
    <!-- End Reassign Modal -->
  </div>
</template>

<script setup>
import { ref, reactive, nextTick } from 'vue';
import axios from 'axios';
import { confirmAction, showAlert } from '@/Utils/bootbox';
import { initSelect2, destroySelect2 } from '@/Utils/select2';

// --- Datatable state ---
const datatableRef = ref(null);
const pageLength = ref(10);
const datatableParams = reactive({
  search: '',
  sortColumn: 'created_at',
  sortDirection: 'desc',
});
const datatableHeaders = [
  { text: 'Date', value: 'created_at', width: '8%' },
  { text: 'Reference No', value: 'reference_no', width: '10%' },
  { text: 'Document Type', value: 'document_type', width: '10%' },
  { text: 'Project Name', value: 'project_name', width: '10%' },
  { text: 'Description', value: 'description', width: '20%' },
  { text: 'Requested By', value: 'created_by', width: '10%' },
  {
    text: 'Receivers',
    value: 'receivers',
    width: '30%',
    slot: 'receivers',
    sortable: false,
  },
  { text: 'Status', value: 'status', width: '10%', slot: 'status' },
];
const datatableFetchUrl = '/api/document-transfers';
const datatableActions = ['edit', 'delete', 'preview', 'receive_reassign'];
const datatableOptions = {
  responsive: true,
  pageLength: pageLength.value,
  lengthMenu: [
    [10, 20, 50, 100, 1000],
    [10, 20, 50, 100, 1000],
  ],
};

// --- Reassign modal state ---
const selectedTransfer = reactive({
  id: null,
  reference_no: '',
});
const allUsers = ref([]);
const receiverRows = ref([]);
const receiverSelectRefs = reactive({});
const reassignModal = ref(null);
const oldReceiverIds = ref([]); // Track DB IDs

// --- Helpers ---
const filteredUsers = (currentId) => {
  const selectedIds = receiverRows.value.map((r) => r.user_id).filter(Boolean);
  return allUsers.value.filter(
    (u) => u.id === currentId || !selectedIds.includes(u.id)
  );
};

// --- Datatable actions ---
const createDocumentTransfer = () => {
  window.location.href = '/document-transfers/create';
};
const handleEdit = (transfer) => {
  window.location.href = `/document-transfers/${transfer.id}/edit`;
};
const handlePreview = (transfer) => {
  window.location.href = `/document-transfers/${transfer.id}/show`;
};
const handleDelete = async (transfer) => {
  const confirmed = await confirmAction(
    `Delete "${transfer.reference_no}"?`,
    '<strong>Warning:</strong> Cannot undo!'
  );
  if (!confirmed) return;
  try {
    await axios.delete(`/api/document-transfers/${transfer.id}`);
    showAlert(
      'Deleted',
      `"${transfer.reference_no}" deleted successfully`,
      'success'
    );
    datatableRef.value?.reload();
  } catch (e) {
    showAlert('Failed', e.response?.data?.message || 'Error', 'danger');
  }
};

// --- Receiver rows ---
const addReceiverRow = () => {
  receiverRows.value.push({
    uid: Date.now() + Math.random(),
    user_id: null,
    status: 'Pending',
    disabled: false,
  });
  nextTick(() => initReceiverSelects());
};

const removeReceiverRow = (index) => {
  receiverRows.value.splice(index, 1);
};

const initReceiverSelects = () => {
  receiverRows.value.forEach((row, index) => {
    const el = receiverSelectRefs[index];
    if (!el) return;
    destroySelect2(el);

    initSelect2(
      el,
      {
        placeholder: 'Select a user',
        width: '100%',
        dropdownParent: $(reassignModal.value),
      },
      (selectedIds) => {
        row.user_id = selectedIds[0] || null;
      }
    );

    if (row.user_id) {
      $(el).val(row.user_id).trigger('change.select2');
    }
  });
};

// --- Reassign modal functions ---
const openReassignModal = async (transfer) => {
  selectedTransfer.id = transfer.id;
  selectedTransfer.reference_no = transfer.reference_no;

  oldReceiverIds.value = [];
  receiverRows.value = transfer.receivers.map((r) => {
    oldReceiverIds.value.push(r.receiver_id);
    return {
      uid: Date.now() + Math.random(),
      user_id: r.receiver_id,
      status: r.status,
      disabled: r.status !== 'Pending',
    };
  });

  const { data } = await axios.get('/api/document-transfers/get-receivers');
  allUsers.value = data;

  await nextTick();
  initReceiverSelects();
  $(reassignModal.value).modal('show');
};

const closeReassignModal = () => {
  $(reassignModal.value).modal('hide');
  selectedTransfer.id = null;
  selectedTransfer.reference_no = '';
  receiverRows.value = [];
  Object.values(receiverSelectRefs).forEach((el) => destroySelect2(el));
  oldReceiverIds.value = [];
};

const submitReassign = async () => {
  if (!selectedTransfer.id) return;

  // Remove duplicates and keep only valid user_ids
  const uniqueReceivers = Array.from(
    new Set(receiverRows.value.map(r => r.user_id).filter(Boolean))
  );

  const payload = uniqueReceivers.map(user_id => {
    const row = receiverRows.value.find(r => r.user_id === user_id);
    return {
      receiver_id: row.user_id,
      status: row.disabled ? row.status : 'Pending',
    };
  });

  try {
    await axios.put(
      `/api/document-transfers/${selectedTransfer.id}/update-or-reassign`,
      { receivers: payload }
    );
    showAlert('Success', 'Receivers updated successfully', 'success');
    datatableRef.value?.reload();
    closeReassignModal();
  } catch (e) {
    showAlert(
      'Failed',
      e.response?.data?.message || 'Error updating receivers',
      'danger'
    );
  }
};

// --- Datatable handlers ---
const datatableHandlers = {
  edit: handleEdit,
  delete: handleDelete,
  preview: handlePreview,
  receive_reassign: openReassignModal,
};

// --- Datatable events ---
const handleSortChange = ({ column, direction }) => {
  datatableParams.sortColumn = column;
  datatableParams.sortDirection = direction;
};
const handlePageChange = (page) => {
  datatableParams.page = page;
};
const handleLengthChange = (length) => {
  datatableParams.limit = length;
};
const handleSearchChange = (search) => {
  datatableParams.search = search;
};
</script>
