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
        <div class="btn-group" role="group">
          <button class="btn btn-success" @click="openCreateModal">
            <i class="fal fa-plus"></i> Create Product
          </button>
          <button class="btn btn-primary" @click="openImportModal">
            <i class="fal fa-file-import"></i> Import Products
          </button>
        </div>
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

    <!-- Import Modal -->
    <div ref="importModal" class="modal fade" tabindex="-1" role="dialog">
      <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Import Products</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
            <div class="mb-2">
              <a href="/sampleExcel/products_sample.xlsx" class="btn btn-sm btn-info" download>
                <i class="fal fa-download"></i> Export Sample
              </a>
            </div>

            <div class="form-group">
              <label class="font-weight-bold">Select File</label>
              <div class="custom-file">
                <input
                  type="file"
                  class="custom-file-input"
                  ref="importFileInput"
                  accept=".xlsx,.csv"
                  @change="handleFileChange"
                />
                <label class="custom-file-label">
                  {{ importFileName || 'Choose file' }}
                </label>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-success" @click="importFileAction" :disabled="importing">
              <span v-if="importing" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
              <i v-else class="fal fa-file-import"></i> Import
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import axios from 'axios'
import ProductModal from '@/components/Product/ProductModal.vue'
import { showAlert } from '@/Utils/bootbox'

// -------------------- DATATABLE --------------------
const datatableRef = ref(null)
const productModal = ref(null)
const pageLength = ref(10)
const isEditing = ref(false)

const datatableParams = reactive({ sortColumn: 'created_at', sortDirection: 'desc', search: '' })

const datatableHeaders = [
  { text: 'Item Code', value: 'item_code', width: '10%', sortable: true },
  { text: 'Image', value: 'image', width: '10%', sortable: false },
  { text: 'Name', value: 'name', width: '25%', sortable: true },
  { text: 'Category', value: 'category_name', width: '10%', sortable: false },
  { text: 'Sub Category', value: 'sub_category_name', width: '10%', sortable: false },
  { text: 'Has Variants', value: 'has_variants', width: '10%', sortable: false },
  { text: 'Active', value: 'is_active', width: '5%', sortable: false },
  { text: 'Created', value: 'created_at', width: '10%', sortable: true },
  { text: 'Updated', value: 'updated_at', width: '10%', sortable: false }
]

const datatableFetchUrl = '/api/products'
const datatableActions = ['edit', 'delete']
const datatableOptions = {
  responsive: true,
  pageLength: pageLength.value,
  lengthMenu: [[10, 20, 50, 100, 1000], [10, 20, 50, 100, 1000]],
}

// -------------------- DATATABLE ACTIONS --------------------
const openCreateModal = () => {
  isEditing.value = false
  productModal.value.show({ isEditing: false })
}

const openEditModal = async (product) => {
  const response = await axios.get(`/api/products/${product.id}/edit`)
  const fullProduct = response.data.product
  isEditing.value = true
  productModal.value.show({ isEditing: true, ...fullProduct })
}

const handleDelete = async (product) => {
  if (!confirm(`Delete Product "${product.name}"? This action cannot be undone.`)) return
  try {
    await axios.delete(`/api/products/${product.id}`)
    showAlert('Deleted', `"${product.name}" was deleted successfully.`, 'success')
    datatableRef.value?.reload()
  } catch (e) {
    showAlert('Failed', e.response?.data?.message || 'Something went wrong.', 'danger')
  }
}

const datatableHandlers = { edit: openEditModal, delete: handleDelete }
const handleSortChange = ({ column, direction }) => { datatableParams.sortColumn = column; datatableParams.sortDirection = direction }
const handlePageChange = (page) => {}
const handleLengthChange = (length) => {}
const handleSearchChange = (search) => { datatableParams.search = search }
const reloadDatatable = () => { datatableRef.value?.reload() }

// -------------------- IMPORT --------------------
const importModal = ref(null)
const importFileInput = ref(null)
const importFile = ref(null)
const importFileName = ref('')
const importing = ref(false)

const openImportModal = () => { $(importModal.value).modal('show') }

const handleFileChange = (event) => {
  importFile.value = event.target.files[0]
  importFileName.value = importFile.value?.name || ''
  $(importFileInput.value).next('.custom-file-label').html(importFileName.value)
}

const importFileAction = async () => {
  if (!importFile.value) {
    showAlert('Error', 'Please select a file to import.', 'warning');
    return;
  }

  const formData = new FormData();
  formData.append('file', importFile.value);

  try {
    importing.value = true;
    const response = await axios.post('/api/products/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });

    // Show all messages from backend: success + errors
    let message = response.data.message || 'Import completed.';
    if (response.data.errors && response.data.errors.length > 0) {
      message += '\n\nErrors:\n' + response.data.errors.join('\n');
    }

    showAlert('Info', message, 'info');
    datatableRef.value?.reload();
    $(importModal.value).modal('hide');

    // Reset file input
    importFile.value = null;
    importFileName.value = '';
    $(importFileInput.value).val('');
  } catch (error) {
    // Handle errors from the request itself
    const data = error.response?.data;
    let errorText = '';
    if (data?.errors && data.errors.length > 0) {
      errorText = data.errors.join('\n');
    } else {
      errorText = data?.message || 'Something went wrong.';
    }
    showAlert('Error', errorText, 'danger');
    console.error('Import Errors:', data?.errors || data?.message);
  } finally {
    importing.value = false;
  }
};

</script>
