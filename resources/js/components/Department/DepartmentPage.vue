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
          <i class="fal fa-plus"></i> Create Department
        </button>
      </template>
      <template #cell-status="{ value }">
        <span :class="value ? 'badge badge-success' : 'badge badge-secondary'">
          {{ value ? 'Active' : 'Inactive' }}
        </span>
      </template>
    </datatable>

    <!-- Department Modal -->
    <DepartmentModal
      ref="departmentModal"
      :isEditing="isEditing"
      @submitted="reloadDatatable"
    />
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import axios from 'axios'
import DepartmentModal from './DepartmentModal.vue'
import { confirmAction, showAlert } from '@/utils/bootbox'

// Refs and state
const datatableRef = ref(null)
const departmentModal = ref(null)
const isEditing = ref(false)
const pageLength = ref(10)

// Datatable configuration
const datatableParams = reactive({
  sortColumn: 'created_at',
  sortDirection: 'desc',
  // Optionally add: page: 1, limit: 10, search: ''
})

const datatableHeaders = [
  { text: 'Name', value: 'name', width: '20%', sortable: true },
  { text: 'Short Name', value: 'short_name', width: '15%', sortable: true },
  { text: 'Division', value: 'division_name', width: '25%', sortable: false },
  { text: 'Active', value: 'is_active', width: '10%', sortable: false },
  { text: 'Created', value: 'created_at', width: '10%', sortable: true }
  // Removed Updated column
]

const datatableFetchUrl = '/api/departments'
const datatableActions = ['edit', 'delete']
const datatableOptions = {
  responsive: true,
  pageLength: pageLength.value,
  lengthMenu: [[10, 20, 50, 100, 1000], [10, 20, 50, 100, 1000]],
}

// Modal handling
const openCreateModal = () => {
  isEditing.value = false
  departmentModal.value.show({ isEditing: false })
}

const openEditModal = async (department) => {
  try {
    const response = await axios.get(`/api/departments/${department.id}/edit`)
    const fullDepartment = response.data.data || {}
    isEditing.value = true
    departmentModal.value.show({
      isEditing: true,
      ...fullDepartment
    })
  } catch (error) {
    console.error('Failed to fetch department data for editing:', error)
    showAlert('Error', 'Failed to load department data for editing.', 'danger')
  }
}

// Action handlers
const handleEdit = openEditModal

const handleDelete = async (department) => {
  const confirmed = await confirmAction(
    `Delete "${department.name}"?`,
    '<strong>Warning:</strong> This action cannot be undone!'
  )
  if (!confirmed) return

  try {
    await axios.delete(`/api/departments/${department.id}`)
    showAlert('Deleted', `"${department.name}" was deleted successfully.`, 'success')
    reloadDatatable()
  } catch (e) {
    console.error(e)
    showAlert('Failed to delete', e.response?.data?.message || 'Something went wrong.', 'danger')
  }
}

// Datatable event handlers
const handleSortChange = ({ column, direction }) => {
  datatableParams.sortColumn = column
  datatableParams.sortDirection = direction
}

const handlePageChange = (page) => {
  // Uncomment and implement if your datatable supports pagination
  // datatableParams.page = page
}

const handleLengthChange = (length) => {
  // Uncomment and implement if your datatable supports page length
  // datatableParams.limit = length
}

const handleSearchChange = (search) => {
  datatableParams.search = search
}

// Map actions to handlers
const datatableHandlers = {
  edit: handleEdit,
  delete: handleDelete
}

// Reload datatable data
const reloadDatatable = () => {
  datatableRef.value?.reload()
}
</script>