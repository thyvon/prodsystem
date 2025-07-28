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
          <i class="fal fa-plus"></i> Create Product
        </button>
      </template>

      <template #cell.image="{ row }">
        <img
          v-if="row.image"
          :src="`/storage/${row.image}`"
          alt="Product Image"
          style="max-width: 60px; max-height: 60px;"
        />
      </template>

      <template #cell.category="{ row }">
        <span v-if="row.category">{{ row.category.name }}</span>
        <span v-else>-</span>
      </template>
      <template #cell.brand="{ row }">
        <span v-if="row.brand">{{ row.brand.name }}</span>
        <span v-else>-</span>
      </template>

    </datatable>

    <!-- Product Modal -->
    <ProductModal ref="productModal" :isEditing="isEditing" @submitted="reloadDatatable" />
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import axios from 'axios'
import ProductModal from '@/components/Product/ProductModal.vue'
import { confirmAction, showAlert } from '@/Utils/bootbox'

// Refs and reactive state
const datatableRef = ref(null)
const productModal = ref(null)
const isEditing = ref(false)

const pageLength = ref(10)

const datatableParams = reactive({
  // Add any extra filters/search here if needed
  sortColumn: 'created_at',
  sortDirection: 'desc',
})

const datatableHeaders = [
  { text: 'Item Code', value: 'item_code', width: '10%', sortable: true },
  { text: 'Image', value: 'image', width: '10%', sortable: false },
  { text: 'Name', value: 'name', width: '25%', sortable: true },
  { text: 'Category', value: 'category_name', width: '10%', sortable: false }, 
  { text: 'Sub Category', value: 'sub_category_name', width: '10%', sortable: false }, 
  { text: 'Has Attributes', value: 'has_variants', width: '10%', sortable: false },
  { text: 'Active', value: 'is_active', width: '5%', sortable: false },
  { text: 'Created', value: 'created_at', width: '10%', sortable: true },
  { text: 'Updated', value: 'updated_at', width: '10%', sortable: false }
]
const datatableFetchUrl = '/api/products'
const datatableActions = ['edit', 'delete']
const datatableOptions = {
  responsive: true,
  pageLength: pageLength.value,
  lengthMenu: [[10, 20, 50, 100, 1000], [10 ,20, 50, 100, 1000]],
}

// Modal handling
const openCreateModal = () => {
  isEditing.value = false
  productModal.value.show({ isEditing: false })
}

const openEditModal = async (product) => {
  try {
    const response = await axios.get(`/api/products/${product.id}/edit`)
    const fullProduct = response.data.product

    isEditing.value = true
    productModal.value.show({
      isEditing: true,
      ...fullProduct
    })
  } catch (error) {
    console.error('Failed to fetch product data for editing:', error)
  }
}

// Action handlers
const handleEdit = openEditModal

const handleDelete = async (product) => {
  const confirmed = await confirmAction(
    `Delete "${product.name}"?`,
    '<strong>Warning:</strong> This action cannot be undone!'
  )

  if (!confirmed) return

  try {
    await axios.delete(`/api/products/${product.id}`)
    showAlert('Deleted', `"${product.name}" was deleted successfully.`, 'success')
    reloadDatatable()
  } catch (e) {
    console.error(e)
    showAlert('Failed to delete', e.response?.data?.message || 'Something went wrong.', 'danger')
  }
}

// Event handlers for the datatable
const handleSortChange = ({ column, direction }) => {
  datatableParams.sortColumn = column
  datatableParams.sortDirection = direction
}

const handlePageChange = (page) => {
  // Optional: If you want to track current page for filters, etc.
  // datatableParams.page = page
}

const handleLengthChange = (length) => {
  // Optional: If you want to track current length for filters, etc.
  // datatableParams.limit = length
}

const handleSearchChange = (search) => {
  datatableParams.search = search
}

// Handlers for the datatable
const datatableHandlers = {
  edit: handleEdit,
  delete: handleDelete,
}

// Refresh datatable after modal actions
const reloadDatatable = () => {
  datatableRef.value?.reload()
}
</script>