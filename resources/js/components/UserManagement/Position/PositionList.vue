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
          <i class="fal fa-plus"></i> Create Position
        </button>
      </template>
      <template #cell-status="{ value }">
        <span :class="value ? 'badge badge-success' : 'badge badge-secondary'">
          {{ value ? 'Active' : 'Inactive' }}
        </span>
      </template>
    </datatable>

    <!-- Position Modal -->
    <PositionModal
      ref="positionModal"
      :isEditing="isEditing"
      @submitted="reloadDatatable"
    />
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import axios from 'axios'
import PositionModal from './PositionModal.vue'
import { confirmAction, showAlert } from '@/Utils/bootbox'

// Refs and state
const datatableRef = ref(null)
const positionModal = ref(null)
const isEditing = ref(false)
const pageLength = ref(10)

// Datatable configuration
const datatableParams = reactive({
  sortColumn: 'created_at',
  sortDirection: 'desc',
  // Optional: page: 1, limit: 10, search: ''
})

const datatableHeaders = [
  { text: 'Title', value: 'title', width: '25%', sortable: true },
  { text: 'Short Title', value: 'short_title', width: '20%', sortable: true },
  { text: 'Department', value: 'department_name', width: '30%', sortable: false },
  { text: 'Active', value: 'is_active', width: '10%', sortable: false },
  { text: 'Created', value: 'created_at', width: '10%', sortable: true }
]

const datatableFetchUrl = '/api/positions'
const datatableActions = ['edit', 'delete']
const datatableOptions = {
  responsive: true,
  pageLength: pageLength.value,
  lengthMenu: [[10, 20, 50, 100, 1000], [10, 20, 50, 100, 1000]],
}

// Modal handling
const openCreateModal = () => {
  isEditing.value = false
  positionModal.value.show({ isEditing: false })
}

const openEditModal = async (position) => {
  try {
    const response = await axios.get(`/api/positions/${position.id}/edit`)
    const fullPosition = response.data.data || {}
    isEditing.value = true
    positionModal.value.show({
      isEditing: true,
      ...fullPosition
    })
  } catch (error) {
    console.error('Failed to fetch position data for editing:', error)
    showAlert('Error', 'Failed to load position data for editing.', 'danger')
  }
}

// Action handlers
const handleEdit = openEditModal

const handleDelete = async (position) => {
  const confirmed = await confirmAction(
    `Delete "${position.title}"?`,
    '<strong>Warning:</strong> This action cannot be undone!'
  )
  if (!confirmed) return

  try {
    await axios.delete(`/api/positions/${position.id}`)
    showAlert('Deleted', `"${position.title}" was deleted successfully.`, 'success')
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
  // Optional: implement if needed
  // datatableParams.page = page
}

const handleLengthChange = (length) => {
  // Optional: implement if needed
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
