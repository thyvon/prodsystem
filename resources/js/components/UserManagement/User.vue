<template>
  <div>
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
          <button class="btn btn-success" @click="createUser">
            <i class="fal fa-plus"></i> Create User
          </button>
        </div>
      </template>
    </datatable>

    <!-- Assign Role Modal -->
    <div v-if="showAssignRoleModal" class="modal-backdrop">
      <div class="modal" style="display:block;">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Assign Role to {{ selectedUser?.name }}</h5>
              <button type="button" class="close" @click="closeAssignRoleModal">&times;</button>
            </div>
            <div class="modal-body">
              <select
                ref="roleSelect"
                class="form-control select2"
                multiple="multiple"
                style="width:100%"
              >
                <option v-for="role in availableRoles" :key="role" :value="role">{{ role }}</option>
              </select>
            </div>
            <div class="modal-footer">
              <button class="btn btn-secondary" @click="closeAssignRoleModal">Cancel</button>
              <button class="btn btn-primary" @click="submitAssignRole" :disabled="!selectedRole.length">Assign</button>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- End Assign Role Modal -->
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch, nextTick } from 'vue';
import axios from 'axios';
import { confirmAction, showAlert } from '@/Utils/bootbox';
import { initSelect2, destroySelect2 } from '@/Utils/select2';

// --- State ---
const datatableRef = ref(null);
const showAssignRoleModal = ref(false);
const selectedUser = ref(null);
const selectedRole = ref([]);
const availableRoles = ref([]);
const roleSelect = ref(null);
const pageLength = ref(10);
const datatableParams = reactive({
  sortColumn: 'created_at',
  sortDirection: 'desc',
});

// --- Datatable config ---
const datatableHeaders = [
  { text: 'Profile', value: 'profile_url', width: '5%', sortable: false },
  { text: 'Name', value: 'name', width: '15%', sortable: true },
  { text: 'Department', value: 'default_department', width: '10%', sortable: true },
  { text: 'Position', value: 'default_position', width: '10%', sortable: true },
  { text: 'Campus', value: 'default_campus', width: '10%', sortable: true },
  { text: 'Email', value: 'email', width: '20%', sortable: true },
  { text: 'Phone', value: 'phone', width: '10%', sortable: true },
  { text: 'Role', value: 'role', width: '10%', sortable: true },
  { text: 'Active', value: 'is_active', width: '5%', sortable: true },
  { text: 'Created', value: 'created_at', width: '7%', sortable: true },
  { text: 'Updated', value: 'updated_at', width: '8%', sortable: false },
];
const datatableFetchUrl = '/api/users';
const datatableActions = ['edit', 'delete', 'assignRole'];
const datatableOptions = {
  responsive: true,
  pageLength: pageLength.value,
  lengthMenu: [[10, 20, 50, 100, 1000], [10, 20, 50, 100, 1000]],
};

// --- Lifecycle: Fetch roles from API ---
onMounted(async () => {
  try {
    const res = await axios.get('/api/roles-name');
    availableRoles.value = Array.isArray(res.data) ? res.data : res.data.data;
  } catch (e) {
    showAlert('Failed to load roles', e.response?.data?.message || 'Could not fetch roles.', 'danger');
  }
});

// --- Select2 integration for roles ---
watch(showAssignRoleModal, async (val) => {
  if (val) {
    await nextTick();
    initSelect2(
      roleSelect.value,
      {
        placeholder: 'Select roles',
        width: '100%',
        dropdownParent: $('.modal:visible'),
      },
      (val) => {
        selectedRole.value = val || [];
      }
    );
    $(roleSelect.value).val(selectedRole.value).trigger('change.select2');
  } else {
    destroySelect2(roleSelect.value);
  }
});

// --- Navigation handling ---
const createUser = () => {
  window.location.href = '/users/create';
};

const handleEdit = (user) => {
  window.location.href = `/users/${user.id}/edit`;
};

const openAssignRoleModal = (user) => {
  selectedUser.value = user;
  selectedRole.value = user.roles ? user.roles.map((r) => r.name) : [];
  showAssignRoleModal.value = true;
};

const closeAssignRoleModal = () => {
  showAssignRoleModal.value = false;
  selectedUser.value = null;
  selectedRole.value = [];
};

// --- Action handlers ---
const handleDelete = async (user) => {
  const confirmed = await confirmAction(
    `Delete "${user.name}"?`,
    '<strong>Warning:</strong> This action cannot be undone!'
  );
  if (!confirmed) return;

  try {
    await axios.delete(`/api/users/${user.id}`);
    showAlert('Deleted', `"${user.name}" was deleted successfully.`, 'success');
    datatableRef.value?.reload();
  } catch (e) {
    console.error('Delete error:', e);
    showAlert('Failed to delete', e.response?.data?.message || 'Something went wrong.', 'danger');
  }
};

const assignUserRole = async (userId, roles) => {
  try {
    await axios.post(`/api/users/${userId}/assign-role`, { roles });
    showAlert('Success', `Roles assigned successfully.`, 'success');
    datatableRef.value?.reload();
  } catch (error) {
    console.error('Failed to assign roles:', error);
    showAlert('Failed', error.response?.data?.message || 'Failed to assign roles.', 'danger');
  }
};

const submitAssignRole = async () => {
  if (!selectedUser.value || !selectedRole.value.length) return;
  await assignUserRole(selectedUser.value.id, selectedRole.value);
  closeAssignRoleModal();
};

const handleAssignRole = openAssignRoleModal;

const datatableHandlers = {
  edit: handleEdit,
  delete: handleDelete,
  assignRole: handleAssignRole,
};

// --- Datatable event handlers ---
const handleSortChange = ({ column, direction }) => {
  datatableParams.sortColumn = column;
  datatableParams.sortDirection = direction;
};

const handlePageChange = (page) => {
  // Optional: track current page if needed
  // datatableParams.page = page;
};

const handleLengthChange = (length) => {
  pageLength.value = length;
  // datatableParams.limit = length;
};

const handleSearchChange = (search) => {
  datatableParams.search = search;
};
</script>

<style scoped>
.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.3);
  z-index: 1050;
}
.modal {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  z-index: 1060;
}
</style>