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
          <i class="fal fa-plus"></i> Create Role
        </button>
      </template>
    </datatable>

    <RoleModal ref="roleModal" :isEditing="isEditing" @submitted="reloadDatatable" />
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import RoleModal from '@/components/UserManagement/RoleModal.vue'
import { confirmAction, showAlert } from '@/Utils/bootbox'
import axios from 'axios'

const datatableRef = ref(null)
const roleModal = ref(null)
const isEditing = ref(false)
const pageLength = ref(10)

const datatableParams = reactive({
  page: 1,
  limit: pageLength.value,
  search: '',
  sortColumn: 'created_at',
  sortDirection: 'desc',
})

const datatableHeaders = [
  { text: 'Name', value: 'name', width: '30%', sortable: true },
  { text: 'Guard Name', value: 'guard_name', width: '30%', sortable: true },
  { text: 'Created At', value: 'created_at', width: '20%', sortable: true },
  { text: 'Updated At', value: 'updated_at', width: '20%', sortable: true }
]

const datatableFetchUrl = '/api/roles'
const datatableActions = ['edit', 'delete']
const datatableOptions = {
  responsive: true,
  pageLength: pageLength.value,
  lengthMenu: [[10, 20, 50, 100, 1000], [10 ,20, 50, 100, 1000]],
}

const openCreateModal = () => {
  isEditing.value = false
  roleModal.value?.show({ isEditing: false })
}

const openEditModal = async (role) => {
  isEditing.value = true
  roleModal.value?.show({ isEditing: true, ...role })
}

const handleEdit = openEditModal

const handleDelete = async (role) => {
  if (!confirm(`Delete "${role.name}"?`)) return
  try {
    await axios.delete(`/api/roles/${role.id}`)
    datatableRef.value?.reload && datatableRef.value.reload()
    alert('Deleted')
  } catch (e) {
    console.error(e)
    alert('Failed to delete')
  }
}

const handleSortChange = ({ column, direction }) => {
  datatableParams.sortColumn = column
  datatableParams.sortDirection = direction
}

const handlePageChange = (page) => {
  datatableParams.page = page
}

const handleLengthChange = (length) => {
  datatableParams.limit = length
}

const handleSearchChange = (search) => {
  datatableParams.search = search
}

const reloadDatatable = () => {
  datatableRef.value?.reload && datatableRef.value.reload()
}

const datatableHandlers = {
  edit: handleEdit,
  delete: handleDelete,
}
</script>
