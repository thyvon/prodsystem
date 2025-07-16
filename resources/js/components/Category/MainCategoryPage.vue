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
          <i class="fal fa-plus"></i> Create Main Category
        </button>
      </template>
      <template #cell-status="{ value }">
        <span :class="value ? 'badge badge-success' : 'badge badge-secondary'">
          {{ value ? 'Active' : 'Inactive' }}
        </span>
      </template>
    </datatable>

    <!-- Main Category Modal -->
    <MainCategoryModal
      ref="mainCategoryModal"
      :isEditing="isEditing"
      @submitted="reloadDatatable"
    />
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import axios from 'axios'
import MainCategoryModal from './MainCategoryModal.vue'
import { confirmAction, showAlert } from '@/Utils/bootbox'

// Refs and state
const datatableRef = ref(null)
const mainCategoryModal = ref(null)
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
  { text: 'Khmer Name', value: 'khmer_name', width: '35%', sortable: true },
  { text: 'Short Name', value: 'short_name', width: '10%', sortable: true },
  { text: 'Active', value: 'is_active', width: '10%', sortable: false },
  { text: 'Created', value: 'created_at', width: '10%', sortable: true }
  // Removed Updated column
]

const datatableFetchUrl = '/api/main-categories'
const datatableActions = ['edit', 'delete']
const datatableOptions = {
  responsive: true,
  pageLength: pageLength.value,
  lengthMenu: [[10, 20, 50, 100, 1000], [10, 20, 50, 100, 1000]],
}

// Modal handling
const openCreateModal = () => {
  isEditing.value = false
  mainCategoryModal.value.show({ isEditing: false })
}

const openEditModal = async (mainCategory) => {
  try {
    const response = await axios.get(`/api/main-categories/${mainCategory.id}/edit`)
    const fullMainCategory = response.data.data || {}
    isEditing.value = true
    mainCategoryModal.value.show({
      isEditing: true,
      ...fullMainCategory
    })
  } catch (error) {
    console.error('Failed to fetch main category data for editing:', error)
    showAlert('Error', 'Failed to load main category data for editing.', 'danger')
  }
}

// Action handlers
const handleEdit = openEditModal

const handleDelete = async (mainCategory) => {
  const confirmed = await confirmAction(
    `Delete "${mainCategory.name}"?`,
    '<strong>Warning:</strong> This action cannot be undone!'
  )
  if (!confirmed) return

  try {
    await axios.delete(`/api/main-categories/${mainCategory.id}`)
    showAlert('Deleted', `"${mainCategory.name}" was deleted successfully.`, 'success')
    reloadDatatable()
  } catch (error) {
    console.error(error)
    showAlert('Failed to delete', error.response?.data?.message || 'Something went wrong.', 'danger')
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