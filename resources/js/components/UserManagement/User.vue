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
        <button class="btn btn-success" @click="openCreateModal">
          <i class="fal fa-plus"></i> Create User
        </button>
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
import { ref, reactive, onMounted, watch, nextTick } from 'vue'
import axios from 'axios'
import { confirmAction, showAlert } from '@/Utils/bootbox'
import { initSelect2, destroySelect2 } from '@/Utils/select2'

// --- State ---
const datatableRef = ref(null)
const userModal = ref(null)
const isEditing = ref(false)

const showAssignRoleModal = ref(false)
const selectedUser = ref(null)
const selectedRole = ref([]) // Array for multiple roles
const availableRoles = ref([])

const roleSelect = ref(null)

const pageLength = ref(10)
const datatableParams = reactive({
  sortColumn: 'created_at',
  sortDirection: 'desc',
})

// --- Datatable config ---
const datatableHeaders = [
  { text: 'Name', value: 'name', width: '25%', sortable: true },
  { text: 'Email', value: 'email', width: '25%', sortable: true },
  { text: 'Role', value: 'role', width: '15%', sortable: true },
  { text: 'Created', value: 'created_at', width: '20%', sortable: true },
  { text: 'Updated', value: 'updated_at', width: '15%', sortable: false }
]
const datatableFetchUrl = '/api/users'
const datatableActions = ['edit', 'delete', 'assignRole']
const datatableOptions = {
  responsive: true,
  pageLength: pageLength.value,
  lengthMenu: [[10, 20, 50, 100, 1000], [10, 20, 50, 100, 1000]],
}

// --- Lifecycle: Fetch roles from API ---
onMounted(async () => {
  try {
    const res = await axios.get('/api/roles-name')
    availableRoles.value = res.data
  } catch (e) {
    showAlert('Failed to load roles', e.response?.data?.message || 'Could not fetch roles.', 'danger')
  }
})

// --- Select2 integration for roles (Reusable) ---
watch(showAssignRoleModal, async (val) => {
  if (val) {
    await nextTick()
    initSelect2(
      roleSelect.value,
      {
        placeholder: 'Select roles',
        width: '100%',
        dropdownParent: $('.modal:visible')
      },
      (val) => { selectedRole.value = val || [] }
    )
    window.$(roleSelect.value).val(selectedRole.value).trigger('change')
  } else {
    destroySelect2(roleSelect.value)
  }
})

// --- Modal handling ---
const openCreateModal = () => {
  isEditing.value = false
  userModal.value?.show({ isEditing: false })
}

const openEditModal = async (user) => {
  try {
    const response = await axios.get(`/api/users/${user.id}/edit`)
    const fullUser = response.data.user
    isEditing.value = true
    userModal.value?.show({
      isEditing: true,
      ...fullUser
    })
  } catch (error) {
    console.error('Failed to fetch user data for editing:', error)
  }
}

const openAssignRoleModal = (user) => {
  selectedUser.value = user
  selectedRole.value = user.roles ? user.roles.map(r => r.name) : []
  showAssignRoleModal.value = true
}

const closeAssignRoleModal = () => {
  showAssignRoleModal.value = false
  selectedUser.value = null
  selectedRole.value = []
}

// --- Action handlers ---
const handleEdit = openEditModal

const handleDelete = async (user) => {
  const confirmed = await confirmAction(
    `Delete "${user.name}"?`,
    '<strong>Warning:</strong> This action cannot be undone!'
  )
  if (!confirmed) return

  try {
    await axios.delete(`/api/users/${user.id}`)
    showAlert('Deleted', `"${user.name}" was deleted successfully.`, 'success')
    reloadDatatable()
  } catch (e) {
    console.error(e)
    showAlert('Failed to delete', e.response?.data?.message || 'Something went wrong.', 'danger')
  }
}

const assignUserRole = async (userId, roles) => {
  try {
    await axios.post(`/api/users/${userId}/assign-role`, { roles })
    showAlert('Success', `Roles assigned successfully.`, 'success')
    reloadDatatable()
  } catch (error) {
    console.error('Failed to assign roles:', error)
    showAlert('Failed', error.response?.data?.message || 'Failed to assign roles.', 'danger')
  }
}

const submitAssignRole = async () => {
  if (!selectedUser.value || !selectedRole.value.length) return
  await assignUserRole(selectedUser.value.id, selectedRole.value)
  closeAssignRoleModal()
}

const handleAssignRole = openAssignRoleModal

const datatableHandlers = {
  edit: handleEdit,
  delete: handleDelete,
  assignRole: handleAssignRole,
}

// --- Datatable event handlers ---
const handleSortChange = ({ column, direction }) => {
  datatableParams.sortColumn = column
  datatableParams.sortDirection = direction
}

const handlePageChange = (page) => {
  // Optional: track current page if needed
}

const handleLengthChange = (length) => {
  // Optional: track current length if needed
}

const handleSearchChange = (search) => {
  datatableParams.search = search
}

// --- Utility ---
const reloadDatatable = () => {
  datatableRef.value?.reload()
}
</script>

<style scoped>
.modal-backdrop {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0,0,0,0.3);
  z-index: 1050;
}
.modal {
  position: fixed;
  top: 50%; left: 50%;
  transform: translate(-50%, -50%);
  z-index: 1060;
}
</style>