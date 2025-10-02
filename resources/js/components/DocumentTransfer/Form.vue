<template>
  <div class="container-fluid">
    <form @submit.prevent="submitForm">
      <div class="card border mb-0 shadow">
        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
          <h4 class="mb-0 font-weight-bold">
            {{ isEditMode ? 'Edit Document Transfer' : 'Create Document Transfer' }}
          </h4>
          <button type="button" class="btn btn-outline-primary btn-sm" @click="goToIndex">
            <i class="fal fa-backward"></i>
          </button>
        </div>

        <div class="card-body">
          <!-- Document Transfer Details -->
          <div class="border rounded p-3 mb-4">
            <h5 class="font-weight-bold mb-3 text-primary">🏷️ Document Transfer Details</h5>
            <div class="form-row">
              <div class="form-group col-md-6">
                <label class="font-weight-bold">
                  Description <span class="text-danger">*</span>
                </label>
                <textarea v-model="form.description" class="form-control" rows="2" required></textarea>
              </div>
              <div class="form-group col-md-2">
                <label class="font-weight-bold">
                  Document Type <span class="text-danger">*</span>
                </label>
                <select class="form-control select2-doc-type" required>
                  <option value="">Select Type</option>
                </select>
              </div>
              <div class="form-group col-md-2">
                <label class="font-weight-bold">
                  Project Name <span class="text-danger">*</span>
                </label>
                <select class="form-control select2-project-name" required>
                  <option value="">Select Project</option>
                </select>
              </div>
              <div class="form-group col-md-2">
                <div class="custom-control custom-checkbox mt-4">
                  <input
                    type="checkbox"
                    class="custom-control-input"
                    id="isSendBack"
                    v-model="form.is_send_back"
                  />
                  <label class="custom-control-label" for="isSendBack">Send Back ?</label>
                </div>
              </div>
            </div>
          </div>

          <!-- Receiver Assignments -->
          <div class="border rounded p-3 mb-4">
            <h5 class="font-weight-bold mb-3 text-primary">👥 Receivers Assignments</h5>
            <div class="table-responsive">
              <table class="table table-bordered table-sm table-hover">
                <thead class="thead-light">
                  <tr>
                    <th style="width: 70%">Assigned User</th>
                    <th style="width: 30%">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(receiver, index) in form.receivers" :key="index">
                    <td>
                      <select
                        class="form-control user-select"
                        :data-row="index"
                        required
                      >
                        <option value="">Select User</option>
                      </select>
                    </td>
                    <td>
                      <button
                        type="button"
                        class="btn btn-danger btn-sm"
                        @click="removeReceiver(index)"
                      >
                        <i class="fal fa-trash-alt"></i> Remove
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm mt-2" @click="addReceiver">
              <i class="fal fa-plus"></i> Add Receiver
            </button>
          </div>

          <!-- Buttons -->
          <div class="text-right">
            <button
              type="submit"
              class="btn btn-primary btn-sm mr-2"
              :disabled="isSubmitting || !form.receivers.length"
            >
              <span v-if="isSubmitting" class="spinner-border spinner-border-sm mr-1"></span>
              {{ isEditMode ? 'Update' : 'Create' }}
            </button>
            <button type="button" class="btn btn-secondary btn-sm" @click="goToIndex">Cancel</button>
          </div>
        </div>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue';
import axios from 'axios';
import { initSelect2, destroySelect2 } from '@/Utils/select2';
import { showAlert } from '@/Utils/bootbox';

const props = defineProps({
  documentTransferId: {
    type: Number,
    required: false,
  },
});

const emit = defineEmits(['submitted']);

const isEditMode = ref(!!props.documentTransferId);
const isSubmitting = ref(false);
const users = ref([]);
const documentTypes = ref(['LOA', 'PAYMENT']);
const projectNames = ref(['MJQR', 'MJQE']);

const form = ref({
  document_type: '',
  project_name: '',
  description: '',
  receivers: [],
  is_send_back: false,
});

// Fetch users list
const fetchUsers = async () => {
  try {
    const response = await axios.get('/api/document-transfers/get-receivers');
    users.value = response.data;
  } catch (err) {
    console.error(err);
    showAlert('Error', 'Failed to fetch users list.', 'danger');
  }
};

// Fetch document transfer in edit mode
const fetchDocumentTransfer = async () => {
  if (!isEditMode.value) return;
  try {
    // Updated to use your route
    const response = await axios.get(`/api/document-transfers/${props.documentTransferId}/edit`);
    const data = response.data.data;

    form.value.document_type = data.document_type || '';
    form.value.project_name = data.project_name || '';
    form.value.description = data.description || '';
    form.value.is_send_back = !!data.is_send_back;

    // Map receivers
    form.value.receivers = data.receivers.map(r => ({
      receiver_id: r.receiver_id,
    }));
  } catch (err) {
    console.error(err);
    showAlert('Error', 'Failed to fetch document transfer data', 'danger');
  }
};

// Go back
const goToIndex = () => {
  window.location.href = '/document-transfers';
};

// Initialize receiver select
const initReceiverSelect = (index) => {
  const receiverSelect = document.querySelector(`.user-select[data-row="${index}"]`);
  if (!receiverSelect) return;

  initSelect2(
    receiverSelect,
    {
      placeholder: 'Select User',
      allowClear: true,
      width: '100%',
      data: users.value.map(u => ({ id: u.id, text: u.name })),
    },
    (value) => {
      form.value.receivers[index].receiver_id = value ? Number(value) : null;
    }
  );

  const oldId = form.value.receivers[index].receiver_id;
  if (oldId) {
    $(receiverSelect).val(oldId).trigger('change.select2');
  }
};

// Add/remove receiver
const addReceiver = async () => {
  form.value.receivers.push({ receiver_id: null });
  await nextTick();
  initReceiverSelect(form.value.receivers.length - 1);
};

const removeReceiver = (index) => {
  const sel = document.querySelector(`.user-select[data-row="${index}"]`);
  if (sel) destroySelect2(sel);
  form.value.receivers.splice(index, 1);
};

// Submit form
const submitForm = async () => {
  if (isSubmitting.value) return;
  isSubmitting.value = true;

  try {
    const payload = {
      document_type: form.value.document_type,
      project_name: form.value.project_name,
      description: form.value.description?.trim() || null,
      receivers: form.value.receivers.map(r => ({ receiver_id: r.receiver_id })),
      is_send_back: form.value.is_send_back ? 1 : 0,
    };

    const url = isEditMode.value
      ? `/api/document-transfers/${props.documentTransferId}`
      : '/api/document-transfers';
    const method = isEditMode.value ? 'put' : 'post';

    await axios[method](url, payload);

    await showAlert(
      'Success',
      isEditMode.value
        ? 'Document transfer updated successfully.'
        : 'Document transfer created successfully.',
      'success'
    );

    emit('submitted');
    goToIndex();
  } catch (err) {
    console.error(err.response?.data || err);
    await showAlert(
      'Error',
      err.response?.data?.message || err.message || 'Failed to save document transfer.',
      'danger'
    );
  } finally {
    isSubmitting.value = false;
  }
};

// On mounted
onMounted(async () => {
  await fetchUsers();
  await fetchDocumentTransfer();
  await nextTick();

  // Init Document Type select2
  initSelect2(
    document.querySelector('.select2-doc-type'),
    {
      placeholder: 'Select Type',
      allowClear: true,
      width: '100%',
      data: documentTypes.value.map(d => ({ id: d, text: d })),
    },
    (val) => (form.value.document_type = val || '')
  );
  if (form.value.document_type) {
    $('.select2-doc-type').val(form.value.document_type).trigger('change.select2');
  }

  // Init Project Name select2
  initSelect2(
    document.querySelector('.select2-project-name'),
    {
      placeholder: 'Select Project',
      allowClear: true,
      width: '100%',
      data: projectNames.value.map(p => ({ id: p, text: p })),
    },
    (val) => (form.value.project_name = val || '')
  );
  if (form.value.project_name) {
    $('.select2-project-name').val(form.value.project_name).trigger('change.select2');
  }

  // Init receiver selects
  form.value.receivers.forEach((_, i) => initReceiverSelect(i));
});
</script>
