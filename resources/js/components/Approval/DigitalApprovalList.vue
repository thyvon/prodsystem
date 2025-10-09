<template>
  <div>
    <!-- Digital Approvals Datatable -->
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
          <button class="btn btn-success" @click="createDigitalApproval">
            <i class="fal fa-plus"></i> New Digital Approval
          </button>
        </div>
      </template>
    </datatable>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import axios from 'axios';
import { confirmAction, showAlert } from '@/Utils/bootbox';

// --- Datatable state ---
const datatableRef = ref(null);
const pageLength = ref(10);
const datatableParams = reactive({
  search: '',
  sortColumn: 'created_at',
  sortDirection: 'desc',
});
const datatableHeaders = [
  { text: 'Date', value: 'created_at', width: '6%' },
  { text: 'Reference No', value: 'reference_no', width: '8%' },
  { text: 'Document Type', value: 'document_type', width: '10%' },
  { text: 'Description', value: 'description', width: '25%' },
  { text: 'Created By', value: 'created_by', width: '10%' },
  { text: 'Approvals', value: 'approvals', width: '25%', slot: 'approvals', sortable: false },
  { text: 'File', value: 'sharepoint_file_ui_url', width: '8%' },
  { text: 'Status', value: 'approval_status', width: '8%' },
];
const datatableFetchUrl = '/api/digital-docs-approvals';
const datatableActions = ['edit', 'delete', 'preview'];
const datatableOptions = {
  responsive: true,
  pageLength: pageLength.value,
  lengthMenu: [
    [10, 20, 50, 100, 1000],
    [10, 20, 50, 100, 1000],
  ],
};

// --- Datatable actions ---
const createDigitalApproval = () => {
  window.location.href = '/digital-docs-approvals/create';
};
const handleEdit = (approval) => {
  window.location.href = `/digital-docs-approvals/${approval.id}/edit`;
};
const handlePreview = (approval) => {
  window.location.href = `/digital-docs-approvals/${approval.id}/show`;
};
const handleDelete = async (approval) => {
  const confirmed = await confirmAction(
    `Delete "${approval.reference_no}"?`,
    '<strong>Warning:</strong> Cannot undo!'
  );
  if (!confirmed) return;
  try {
    await axios.delete(`/api/digital-docs-approvals/${approval.id}`);
    showAlert(
      'Deleted',
      `"${approval.reference_no}" deleted successfully`,
      'success'
    );
    datatableRef.value?.reload();
  } catch (e) {
    showAlert('Failed', e.response?.data?.message || 'Error', 'danger');
  }
};

// --- Datatable handlers ---
const datatableHandlers = {
  edit: handleEdit,
  delete: handleDelete,
  preview: handlePreview,
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
