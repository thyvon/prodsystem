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
        <button v-if="canCreateWarehouse" class="btn btn-success" @click="openCreateModal">
          <i class="fal fa-plus"></i> Create Warehouse
        </button>
      </template>
      <template #cell-status="{ value }">
        <span :class="value ? 'badge badge-success' : 'badge badge-secondary'">
          {{ value ? 'Active' : 'Inactive' }}
        </span>
      </template>
    </datatable>

    <!-- Warehouse Modal -->
    <WarehouseModal
      ref="warehouseModal"
      :is-editing="isEditing"
      @submitted="reloadDatatable"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import axios from 'axios'
import WarehouseModal from '@/components/Inventory/Warehouse/WarehouseModal.vue'
import { confirmAction, showAlert } from '@/Utils/bootbox'

// Props
const props = defineProps({
  pageLength: {
    type: Number,
    default: 10
  },
  canCreateWarehouse: {
    type: Boolean,
    default: false
  },
  canUpdateWarehouse: {
    type: Boolean,
    default: false
  },
  canDeleteWarehouse: {
    type: Boolean,
    default: false
  }
})

// Refs and state
const datatableRef = ref(null)
const warehouseModal = ref(null)
const isEditing = ref(false)

// Datatable configuration
const datatableParams = reactive({
  sortColumn: 'created_at',
  sortDirection: 'desc'
})

const datatableHeaders = [
  { text: 'Code', value: 'code', width: '5%', sortable: true },
  { text: 'Name', value: 'name', width: '20%', sortable: true },
  { text: 'Khmer Name', value: 'khmer_name', width: '15%', sortable: true },
  { text: 'Address', value: 'address', width: '25%', sortable: true },
  { text: 'Building', value: 'building_name', width: '10%', sortable: false },
  { text: 'Active', value: 'is_active', width: '5%', sortable: true },
  { text: 'Created By', value: 'created_by_name', width: '10%', sortable: true },
  { text: 'Created', value: 'created_at', width: '10%', sortable: true }
]

const datatableFetchUrl = '/api/inventory/warehouses'

// Conditionally include actions based on permissions
const datatableActions = computed(() => {
  const actions = []
  if (props.canUpdateWarehouse) {
    actions.push('edit')
  }
  if (props.canDeleteWarehouse) {
    actions.push('delete')
  }
  return actions
})

const datatableOptions = {
  responsive: true,
  pageLength: props.pageLength,
  lengthMenu: [[10, 20, 50, 100, 1000], [10, 20, 50, 100, 1000]]
}

// Modal handling
const openCreateModal = () => {
  isEditing.value = false
  warehouseModal.value.show({ isEditing: false })
}

const openEditModal = async (warehouse) => {
  try {
    const response = await axios.get(`/api/inventory/warehouses/${warehouse.id}/edit`)
    const fullWarehouse = response.data.data || {}
    isEditing.value = true
    warehouseModal.value.show({
      isEditing: true,
      ...fullWarehouse
    })
  } catch (error) {
    console.error('Failed to fetch warehouse data for editing:', error)
    showAlert('Error', 'Failed to load warehouse data for editing.', 'danger')
  }
}

// Action handlers
const handleEdit = openEditModal

const handleDelete = async (warehouse) => {
  const confirmed = await confirmAction(
    `Delete "${warehouse.name}"?`,
    '<strong>Warning:</strong> This action cannot be undone!'
  )
  if (!confirmed) return

  try {
    await axios.delete(`/api/inventory/warehouses/${warehouse.id}`)
    showAlert('Deleted', `"${warehouse.name}" was deleted successfully.`, 'success')
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