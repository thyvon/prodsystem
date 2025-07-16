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
          <i class="fal fa-plus"></i> Create Division
        </button>
      </template>
      <template #cell-status="{ value }">
        <span :class="value ? 'badge badge-success' : 'badge badge-secondary'">
          {{ value ? 'Active' : 'Inactive' }}
        </span>
      </template>
    </datatable>

    <!-- Division Modal -->
    <DivisionModal
      ref="divisionModal"
      :isEditing="isEditing"
      @submitted="reloadDatatable"
    />
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import axios from 'axios'
import DivisionModal from './DivisionModal.vue'
import { confirmAction, showAlert } from '@/Utils/bootbox'

// Refs and state
const datatableRef = ref(null)
const divisionModal = ref(null)
const isEditing = ref(false)
const pageLength = ref(10)

// Datatable configuration
const datatableParams = reactive({
  sortColumn: 'created_at',
  sortDirection: 'desc',
  // Optionally add: page: 1, limit: 10, search: ''
})

const datatableHeaders = [
  { text: 'Name', value: 'name', width: '35%', sortable: true },
  { text: 'Short Name', value: 'short_name', width: '25%', sortable: true },
  { text: 'Active', value: 'is_active', width: '15%', sortable: false },
  { text: 'Created', value: 'created_at', width: '25%', sortable: true }
  // Removed Updated column
]

const datatableFetchUrl = '/api/divisions'
const datatableActions = ['edit', 'delete']
const datatableOptions = {
  responsive: true,
  pageLength: pageLength.value,
  lengthMenu: [[10, 20, 50, 100, 1000], [10, 20, 50, 100, 1000]],
}

// Modal handling
const openCreateModal = () => {
  isEditing.value = false
  divisionModal.value.show({ isEditing: false })
}

const openEditModal = async (division) => {
  try {
    const response = await axios.get(`/api/divisions/${division.id}/edit`)
    const fullDivision = response.data.data || {}
    isEditing.value = true
    divisionModal.value.show({
      isEditing: true,
      ...fullDivision
    })
  } catch (error) {
    console.error('Failed to fetch division data for editing:', error)
    showAlert('Error', 'Failed to load division data for editing.', 'danger')
  }
}

// Action handlers
const handleEdit = openEditModal

const handleDelete = async (division) => {
  const confirmed = await confirmAction(
    `Delete "${division.name}"?`,
    '<strong>Warning:</strong> This action cannot be undone!'
  )
  if (!confirmed) return

  try {
    await axios.delete(`/api/divisions/${division.id}`)
    showAlert('Deleted', `"${division.name}" was deleted successfully.`, 'success')
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