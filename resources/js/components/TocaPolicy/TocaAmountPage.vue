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
          <i class="fal fa-plus"></i> Create TOCA Amount
        </button>
      </template>
      <template #cell-status="{ value }">
        <span :class="value ? 'badge badge-success' : 'badge badge-secondary'">
          {{ value ? 'Active' : 'Inactive' }}
        </span>
      </template>
    </datatable>

    <!-- TOCA Amount Modal -->
    <TocaAmountModal
      ref="tocaAmountModal"
      :isEditing="isEditing"
      @submitted="reloadDatatable"
    />
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import axios from 'axios'
import TocaAmountModal from './TocaAmountModal.vue'
import { confirmAction, showAlert } from '@/Utils/bootbox'

// Refs and state
const datatableRef = ref(null)
const tocaAmountModal = ref(null)
const isEditing = ref(false)
const pageLength = ref(10)

// Datatable configuration
const datatableParams = reactive({
  sortColumn: 'created_at',
  sortDirection: 'desc',
  // Optionally add: page: 1, limit: 10, search: ''
})

const datatableHeaders = [
  { text: 'TOCA Name', value: 'toca_name', width: '50%', sortable: true },
  { text: 'Min Amount', value: 'min_amount', width: '15%', sortable: true },
  { text: 'Max Amount', value: 'max_amount', width: '15%', sortable: false },
  { text: 'Active', value: 'is_active', width: '10%', sortable: false },
  { text: 'Created', value: 'created_at', width: '10%', sortable: true }
  // Removed Updated column
]

const datatableFetchUrl = '/api/toca-amounts'
const datatableActions = ['edit', 'delete']
const datatableOptions = {
  responsive: true,
  pageLength: pageLength.value,
  lengthMenu: [[10, 20, 50, 100, 1000], [10, 20, 50, 100, 1000]],
}

// Modal handling
const openCreateModal = () => {
  isEditing.value = false
  tocaAmountModal.value.show({ isEditing: false })
}

const openEditModal = async (tocaAmount) => {
  try {
    const response = await axios.get(`/api/toca-amounts/${tocaAmount.id}/edit`)
    const fullTocaAmount = response.data.data || {}
    isEditing.value = true
    tocaAmountModal.value.show({
      isEditing: true,
      ...fullTocaAmount
    })
  } catch (error) {
    console.error('Failed to fetch TOCA Amount data for editing:', error)
    showAlert('Error', 'Failed to load TOCA Amount data for editing.', 'danger')
  }
}

// Action handlers
const handleEdit = openEditModal

const handleDelete = async (tocaAmount) => {
  const confirmed = await confirmAction(
    `Delete "Min Amount: ${tocaAmount.min_amount} - Max Amount: ${tocaAmount.max_amount}"?`,
    '<strong>Warning:</strong> This action cannot be undone!'
  )
  if (!confirmed) return

  try {
    await axios.delete(`/api/toca-amounts/${tocaAmount.id}`)
    showAlert('Deleted', `"Min Amount: ${tocaAmount.min_amount} - Max Amount: ${tocaAmount.max_amount}" was deleted successfully.`, 'success')
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