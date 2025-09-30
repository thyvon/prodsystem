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
  initialData: {
    type: Object,
    default: () => ({}),
  },
});

const emit = defineEmits(['submitted']);

const isSubmitting = ref(false);
const users = ref([]);
const documentTypes = ref(['LOA', 'PAYMENT']);
const projectName = ref(['MJQR', 'MJQE']);
const isEditMode = ref(!!props.initialData.id);

const form = ref({
  document_type: props.initialData.document_type || '',
  project_name: props.initialData.project_name || '',
  description: props.initialData.description || '',
  receivers: props.initialData.receivers || [],
  is_send_back: props.initialData.is_send_back ?? false,
});

// Load users from API
const fetchReceivers = async () => {
  try {
    const response = await axios.get('/api/document-transfers/get-receivers');
    users.value = response.data;
  } catch (error) {
    console.error('Error fetching users:', error);
    showAlert('Error', 'Failed to fetch users list.', 'danger');
  }
};

onMounted(async () => {
  await fetchReceivers();

  await nextTick();

  try {
    // Init Document Type select2
    initSelect2(
      document.querySelector('.select2-doc-type'),
      {
        placeholder: 'Select Type',
        allowClear: true,
        width: '100%',
        data: documentTypes.value.map((d) => ({ id: d, text: d })),
      },
      (value) => {
        form.value.document_type = value || '';
      }
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
        data: projectName.value.map((p) => ({ id: p, text: p })),
      },
      (value) => {
        form.value.project_name = value || '';
      }
    );
    if (form.value.project_name) {
      $('.select2-project-name').val(form.value.project_name).trigger('change.select2');
    }
  } catch (err) {
    console.error('Error initializing Select2:', err);
    showAlert('Error', 'Failed to initialize Document Type or Project Name dropdown.', 'danger');
  }

  // Init receivers if edit mode
  form.value.receivers.forEach((r, index) => {
    initReceiverSelect(index, r.receiver_id);
  });
});

// Go back
const goToIndex = () => {
  window.location.href = '/document-transfers';
};

// Init Select2 for one receiver row
const initReceiverSelect = (index, selectedId = null) => {
  const receiverSelect = document.querySelector(`.user-select[data-row="${index}"]`);
  if (!receiverSelect) {
    showAlert('Error', 'Failed to initialize receiver dropdown.', 'danger');
    return;
  }

  initSelect2(
    receiverSelect,
    {
      placeholder: 'Select User',
      allowClear: true,
      width: '100%',
      data: users.value.map((u) => ({
        id: u.id,
        text: `${u.name} (Telegram ID: ${u.telegram_id || 'N/A'})`,
      })),
    },
    (value) => {
      form.value.receivers[index].receiver_id = value ? Number(value) : null;
    }
  );

  if (selectedId) {
    $(receiverSelect).val(selectedId).trigger('change.select2');
  }
};

// Add receiver row
const addReceiver = async () => {
  form.value.receivers.push({ id: null, receiver_id: null });
  await nextTick();
  const index = form.value.receivers.length - 1;
  initReceiverSelect(index);
};

// Remove receiver row
const removeReceiver = (index) => {
  const receiverSelect = document.querySelector(`.user-select[data-row="${index}"]`);
  if (receiverSelect) {
    destroySelect2(receiverSelect);
  }
  form.value.receivers.splice(index, 1);
};

const submitForm = async () => {
  if (isSubmitting.value) return;
  isSubmitting.value = true;
  try {
    const payload = {
      document_type: form.value.document_type,
      project_name: form.value.project_name,
      description: form.value.description?.toString().trim() || null,
      receivers: form.value.receivers.map(r => ({
            receiver_id: r.receiver_id
        })),
      is_send_back: form.value.is_send_back ? 1 : 0,
    };

    const url = isEditMode.value
      ? `/api/document-transfers/${props.initialData.id}`
      : `/api/document-transfers`;
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
    console.error('Submit error:', err.response?.data || err);
    await showAlert(
      'Error',
      err.response?.data?.message || err.message || 'Failed to save document transfer.',
      'danger'
    );
  } finally {
    isSubmitting.value = false;
  }
};

</script>
