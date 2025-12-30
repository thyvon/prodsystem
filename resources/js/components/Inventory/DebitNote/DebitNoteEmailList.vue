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
      <!-- Create + Import buttons -->
      <template #additional-header>
        <button class="btn btn-success mr-2" @click="openCreateModal">
          <i class="fal fa-plus"></i> Create
        </button>

        <button class="btn btn-primary" @click="openImportModal">
          <i class="fal fa-file-excel"></i> Import
        </button>
      </template>

      <!-- Send To -->
      <template #cell-send_to_email="{ value }">
        <div v-if="value?.length">
          <div v-for="(email, i) in value" :key="i">{{ email }}</div>
        </div>
        <span v-else class="text-muted">—</span>
      </template>

      <!-- CC -->
      <template #cell-cc_to_email="{ value }">
        <div v-if="value?.length">
          <div v-for="(email, i) in value" :key="i">{{ email }}</div>
        </div>
        <span v-else class="text-muted">—</span>
      </template>
    </datatable>

    <!-- Modals -->
    <DebitNoteEmailModal
      ref="debitNoteModal"
      :is-editing="isEditing"
      @submitted="reloadDatatable"
    />

    <DebitNoteEmailImportModal
      ref="importModal"
      @imported="reloadDatatable"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import axios from 'axios'
import DebitNoteEmailModal from '@/components/Inventory/DebitNote/DebitNoteEmailModal.vue'
import DebitNoteEmailImportModal from '@/components/Inventory/DebitNote/DebitNoteEmailImportModal.vue'
import { confirmAction, showAlert } from '@/Utils/bootbox'

// Refs
const datatableRef = ref(null)
const debitNoteModal = ref(null)
const importModal = ref(null)
const isEditing = ref(false)

// Params
const datatableParams = reactive({
  sortColumn: 'created_at',
  sortDirection: 'desc'
})

// Headers
const datatableHeaders = [
  { text: 'Department', value: 'department_name', width: '20%', sortable: true },
  { text: 'Warehouse', value: 'warehouse_name', width: '20%', sortable: true },
  { text: 'Receiver Name', value: 'receiver_name', width: '20%', sortable: true },
  { text: 'Send To', value: 'send_to_email', width: '20%', sortable: false },
  { text: 'CC', value: 'cc_to_email', width: '20%', sortable: false },
  { text: 'Created', value: 'created_at', width: '20%', sortable: true }
]

// API
const datatableFetchUrl = '/api/inventory/debit-note/emails'

// Actions (always enabled)
const datatableActions = computed(() => ['edit', 'delete'])

const datatableOptions = {
  responsive: true,
  pageLength: 10,
  lengthMenu: [[10, 20, 50, 100], [10, 20, 50, 100]]
}

// Modals
const openCreateModal = () => {
  isEditing.value = false
  debitNoteModal.value.show({ isEditing: false })
}

const openEditModal = async (row) => {
  const res = await axios.get(`/api/inventory/debit-note/emails/${row.id}/edit`)
  isEditing.value = true
  debitNoteModal.value.show({
    isEditing: true,
    ...res.data.data
  })
}

const openImportModal = () => {
  importModal.value.show()
}

// Delete
const handleDelete = async (row) => {
  const confirmed = await confirmAction(
    'Delete this configuration?',
    'This action cannot be undone.'
  )
  if (!confirmed) return

  await axios.delete(`/api/inventory/debit-note/emails/${row.id}`)
  showAlert('Deleted', 'Deleted successfully.', 'success')
  reloadDatatable()
}

// Datatable handlers
const datatableHandlers = {
  edit: openEditModal,
  delete: handleDelete
}

// Events
const handleSortChange = ({ column, direction }) => {
  datatableParams.sortColumn = column
  datatableParams.sortDirection = direction
}
const handlePageChange = () => {}
const handleLengthChange = () => {}
const handleSearchChange = (search) => (datatableParams.search = search)

// Reload
const reloadDatatable = () => {
  datatableRef.value?.reload()
}
</script>
