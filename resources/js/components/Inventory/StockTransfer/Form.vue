<template>
  <div class="container-fluid">
    <form @submit.prevent="submitForm">
      <div class="card border mb-0 shadow">
        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
          <h4 class="mb-0 font-weight-bold">{{ isEditMode ? 'Edit Stock Transfer' : 'Create Stock Transfer' }}</h4>
          <button type="button" class="btn btn-outline-primary btn-sm" @click="goToIndex">
            <i class="fal fa-backward"></i>
          </button>
        </div>

        <div class="card-body">
          <!-- Stock Transfer Details -->
          <div class="border rounded p-3 mb-4">
            <h5 class="font-weight-bold mb-3 text-primary">🏷️ Stock Transfer Details</h5>
            <div class="form-row">
              <div class="form-group col-md-4">
                <label for="transaction_date" class="font-weight-bold">Transaction Date <span class="text-danger">*</span></label>
                <input v-model="form.transaction_date" type="text" class="form-control datepicker" id="transaction_date" required />
              </div>
              <div class="form-group col-md-4">
                <label for="warehouse_id" class="font-weight-bold">From Warehouse <span class="text-danger">*</span></label>
                <select ref="fromWarehouseSelect" v-model="form.warehouse_id" class="form-control" id="warehouse_id" required>
                  <option value="">Select Warehouse</option>
                  <option v-for="warehouse in warehousesFrom" :key="warehouse.id" :value="warehouse.id">
                    {{ warehouse.name }}
                  </option>
                </select>
              </div>
              <div class="form-group col-md-4">
                <label for="destination_warehouse_id" class="font-weight-bold">To Warehouse <span class="text-danger">*</span></label>
                <select ref="toWarehouseSelect" v-model="form.destination_warehouse_id" class="form-control" id="destination_warehouse_id" required>
                  <option value="">Select Warehouse</option>
                  <option v-for="warehouse in warehousesTo" :key="warehouse.id" :value="warehouse.id">
                    {{ warehouse.name }}
                  </option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label for="remarks" class="font-weight-bold">Remarks</label>
              <textarea v-model="form.remarks" class="form-control" id="remarks" rows="2"></textarea>
            </div>
          </div>

          <!-- Transfer Items -->
          <div class="border rounded p-3 mb-4">
            <div class="form-row">
              <div class="form-group col-md-4">
                <label for="import_file" class="font-weight-bold">Import Items</label>
                <div class="input-group">
                  <input
                    type="file"
                    class="d-none"
                    id="import_file"
                    accept=".xlsx,.xls,.csv"
                    ref="fileInput"
                    @change="handleFileUpload"
                  />
                  <button type="button" class="btn btn-outline-secondary" @click="triggerFileInput">
                    <i class="fal fa-file-upload"></i>
                    {{ selectedFileName || 'Choose file...' }}
                  </button>
                  <button
                    type="button"
                    class="btn btn-outline-primary btn-lg btn-icon rounded-circle hover-effect-dot ml-2"
                    @click="importFile"
                    :disabled="isImporting"
                  >
                    <span v-if="isImporting" class="spinner-border spinner-border-sm mr-1"></span>
                    <i class="fal fa-upload"></i>
                  </button>
                  <a
                    class="btn btn-outline-danger btn-lg btn-icon rounded-circle hover-effect-dot ml-2"
                    href="/sampleExcel/stock_transfers_sample.xlsx"
                    download="stock_transfers_sample.xlsx"
                  >
                    <i class="fal fa-file-excel"></i>
                  </a>
                </div>
              </div>
              <div class="form-group col-md-8">
                <label for="product_select" class="font-weight-bold">Add Product</label>
                <select ref="productSelect" class="form-control" id="product_select">
                  <option value="">Select Product</option>
                  <option v-for="product in products" :key="product.id" :value="product.id">
                    {{ product.item_code }} - {{ product.product_name }} {{ product.description }}
                  </option>
                </select>
              </div>
            </div>
            <h5 class="font-weight-bold mb-3 text-primary">📦 Transfer Items <span class="text-danger">*</span></h5>
            <div class="table-responsive">
              <table class="table table-bordered table-sm table-hover">
                <thead class="thead-light">
                  <tr>
                    <th>Code</th>
                    <th>Description</th>
                    <th>UoM</th>
                    <th>Qty On Hand</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Total Value</th>
                    <th>Remarks</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, index) in form.items" :key="item.id || index">
                    <td>{{ ProductCode(item.product_id) }}</td>
                    <td>{{ ProductDescription(item.product_id) }}</td>
                    <td>{{ itemUnitName(item.product_id) }}</td>
                    <td><input type="number" class="form-control" :value="item.stock_on_hand ? item.stock_on_hand.toFixed(4) : '0.0000'" readonly /></td>
                    <td><input type="number" class="form-control" v-model.number="item.quantity" min="0.0001" step="0.0001" required /></td>
                    <td><input type="number" class="form-control" v-model.number="item.unit_price" min="0" step="0.0001" required /></td>
                    <td><input type="number" class="form-control" :value="(item.quantity * item.unit_price).toFixed(4)" readonly /></td>
                    <td><textarea class="form-control" rows="1" v-model="item.remarks"></textarea></td>
                    <td>
                      <button type="button" class="btn btn-danger btn-sm" @click="removeItem(index)">
                        <i class="fal fa-trash"></i>
                      </button>
                    </td>
                  </tr>
                  <tr v-if="form.items.length === 0">
                    <td colspan="9" class="text-center text-muted">No items added</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Approval Assignments -->
          <div class="border rounded p-3 mb-4">
            <h5 class="font-weight-bold mb-3 text-primary">👥 Approval Assignments</h5>
            <div class="table-responsive">
              <table class="table table-bordered table-sm table-hover">
                <thead class="thead-light">
                  <tr>
                    <th>Approval Type</th>
                    <th>Assigned User</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(approval, index) in form.approvals" :key="index">
                    <td>
                      <select
                        v-model="approval.request_type"
                        class="form-control approval-type-select"
                        :data-row="index"
                        required
                        :disabled="approval.isDefault"
                        @change="updateUsersForRow(index)"
                      >
                        <option value="">Select Type</option>
                        <option value="receive">Receive</option>
                        <option value="approve">Approve</option>
                      </select>
                    </td>
                    <td>
                      <select
                        v-model="approval.user_id"
                        class="form-control user-select"
                        :data-row="index"
                        required
                      >
                        <option value="">Select User</option>
                        <option v-for="user in approval.availableUsers" :key="user.id" :value="user.id">
                          {{ user.name }}
                        </option>
                      </select>
                    </td>
                    <td>
                      <button
                        type="button"
                        class="btn btn-danger btn-sm"
                        @click="removeApproval(index)"
                        :disabled="approval.isDefault"
                      >
                        <i class="fal fa-trash-alt"></i> Remove
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm mt-2" @click="addApproval">
              <i class="fal fa-plus"></i> Add Approval
            </button>
          </div>

          <!-- Buttons -->
          <div class="text-right">
            <button
              type="submit"
              class="btn btn-primary btn-sm mr-2"
              :disabled="isSubmitting || !form.items.length || !validateApprovals()"
            >
              <span v-if="isSubmitting" class="spinner-border spinner-border-sm mr-1"></span>
              {{ isEditMode ? (props.initialData.approval_status === 'Returned' ? 'Re-Submit' : 'Update') : 'Create' }}
            </button>
            <button type="button" class="btn btn-secondary btn-sm" @click="goToIndex">Cancel</button>
          </div>
        </div>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import axios from 'axios'
import { showAlert } from '@/Utils/bootbox'
import { initSelect2, destroySelect2 } from '@/Utils/select2'

const props = defineProps({
  initialData: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['formSubmitted'])
const isSubmitting = ref(false)
const isImporting = ref(false)
const products = ref([])
const warehousesFrom = ref([])
const warehousesTo = ref([])
const users = ref({ receive: [], approve: [] })
const fromWarehouseSelect = ref(null)
const toWarehouseSelect = ref(null)
const productSelect = ref(null)
const fileInput = ref(null)
const selectedFileName = ref('')
const isEditMode = computed(() => !!props.initialData.id)

const form = ref({
  transaction_date: '',
  warehouse_id: '',
  destination_warehouse_id: '',
  remarks: '',
  items: [],
  approvals: [],
})

const ProductCode = computed(() => (productId) => {
  const product = products.value.find(p => p.id === Number(productId))
  return product?.item_code || 'N/A'
})

const ProductDescription = computed(() => (productId) => {
  const product = products.value.find(p => p.id === Number(productId))
  return product ? `${product.product_name} ${product.description || ''}` : 'N/A'
})

const itemUnitName = computed(() => (productId) => {
  const product = products.value.find(p => p.id === Number(productId))
  return product?.unit_name || 'N/A'
})

// Navigation
const goToIndex = () => {
  window.location.href = '/inventory/stock-transfers'
}

// Fetch Data
const fetchEditData = async (stockTransferId) => {
  try {
    const { data } = await axios.get(`/api/inventory/stock-transfers/${stockTransferId}/data`)
    if (data.data) {
      form.value.transaction_date = data.data.transaction_date || ''
      form.value.warehouse_id = data.data.warehouse_id || ''
      form.value.destination_warehouse_id = data.data.destination_warehouse_id || ''
      form.value.remarks = data.data.remarks || ''
      form.value.items = data.data.stock_transfer_items?.map(item => ({
        id: item.id || null,
        product_id: Number(item.product_id),
        quantity: parseFloat(item.quantity) || 1,
        unit_price: parseFloat(item.unit_price) || 0,
        stock_on_hand: parseFloat(item.stock_on_hand) || 0,
        remarks: item.remarks || '',
      })) || []
      form.value.approvals = data.data.approvals?.map(approval => ({
        id: approval.id || null,
        user_id: Number(approval.responder_id) || null,
        request_type: approval.request_type || '',
        isDefault: ['receive', 'approve'].includes(approval.request_type),
        availableUsers: [],
      })) || []
      console.log('Fetched edit data:', data.data)
    } else {
      throw new Error('Invalid response data')
    }
  } catch (err) {
    console.error('Failed to fetch edit data:', err)
    showAlert('Error', err.response?.data?.message || 'Failed to load stock transfer data.', 'danger')
  }
}

const fetchProducts = async (warehouseId = '', cutoffDate = '') => {
  try {
    const params = {}
    if (warehouseId) params.warehouse_id = warehouseId
    if (cutoffDate) params.cutoff_date = cutoffDate
    const { data } = await axios.get('/api/inventory/stock-transfers/get-products', { params })
    products.value = Array.isArray(data) ? data : data.data
    console.log('Fetched products:', products.value, 'with params:', params)
  } catch (err) {
    console.error('Failed to load products:', err)
    showAlert('Error', 'Failed to load products.', 'danger')
  }
}

const fetchWarehousesFrom = async () => {
  try {
    const { data } = await axios.get('/api/inventory/stock-transfers/get-warehouses-from')
    warehousesFrom.value = Array.isArray(data) ? data : data.data
  } catch (err) {
    console.error('Failed to load from warehouses:', err)
    showAlert('Error', 'Failed to load from warehouses.', 'danger')
  }
}

const fetchWarehousesTo = async () => {
  try {
    const { data } = await axios.get('/api/inventory/stock-transfers/get-warehouses-to')
    warehousesTo.value = Array.isArray(data) ? data : data.data
  } catch (err) {
    console.error('Failed to load to warehouses:', err)
    showAlert('Error', 'Failed to load to warehouses.', 'danger')
  }
}

const fetchUsersForApproval = async (requestType) => {
  try {
    const { data } = await axios.get('/api/inventory/stock-transfers/get-users-for-approval', {
      params: { request_type: requestType },
    })
    users.value[requestType] = Array.isArray(data.data) ? data.data : []
  } catch (err) {
    console.error(`Failed to load users for ${requestType}:`, err)
    showAlert('Error', `Failed to load users for ${requestType} approval.`, 'danger')
  }
}

// Approval Management
const addApproval = async () => {
  try {
    form.value.approvals.push({
      id: null,
      request_type: '',
      user_id: null,
      isDefault: false,
      availableUsers: [],
    })

    await nextTick()
    const index = form.value.approvals.length - 1
    const approvalSelect = document.querySelector(`.approval-type-select[data-row="${index}"]`)
    const userSelect = document.querySelector(`.user-select[data-row="${index}"]`)

    if (!approvalSelect || !userSelect) {
      console.warn(`DOM elements for row ${index} not found`)
      showAlert('Error', 'Failed to initialize approval dropdowns.', 'danger')
      return
    }

    initSelect2(approvalSelect, {
      placeholder: 'Select Type',
      width: '100%',
      allowClear: true,
    }, (value) => {
      form.value.approvals[index].request_type = value || ''
      updateUsersForRow(index)
    })
    $(approvalSelect).val('').trigger('change.select2')

    initSelect2(userSelect, {
      placeholder: 'Select User',
      width: '100%',
      allowClear: true,
    }, (value) => {
      form.value.approvals[index].user_id = value ? Number(value) : null
    })
    $(userSelect).val('').trigger('change.select2')
  } catch (err) {
    console.error('Error adding approval:', err)
    showAlert('Error', 'Failed to add approval assignment.', 'danger')
  }
}

const removeApproval = async (index) => {
  try {
    if (form.value.approvals[index].isDefault) {
      showAlert('Error', 'Default approval types cannot be removed.', 'danger')
      return
    }
    const approvalSelect = document.querySelector(`.approval-type-select[data-row="${index}"]`)
    const userSelect = document.querySelector(`.user-select[data-row="${index}"]`)
    if (approvalSelect) destroySelect2(approvalSelect)
    if (userSelect) destroySelect2(userSelect)
    form.value.approvals.splice(index, 1)
  } catch (err) {
    console.error('Error removing approval:', err)
    showAlert('Error', 'Failed to remove approval assignment.', 'danger')
  }
}

const updateUsersForRow = async (index) => {
  try {
    const requestType = form.value.approvals[index].request_type
    if (requestType && ['receive', 'approve'].includes(requestType)) {
      if (!users.value[requestType]?.length) {
        await fetchUsersForApproval(requestType)
      }
      form.value.approvals[index].availableUsers = users.value[requestType] || []
      await nextTick()
      const userSelect = document.querySelector(`.user-select[data-row="${index}"]`)
      if (userSelect) {
        destroySelect2(userSelect)
        initSelect2(userSelect, {
          placeholder: 'Select User',
          width: '100%',
          allowClear: true,
        }, (value) => {
          form.value.approvals[index].user_id = value ? Number(value) : null
        })
        const currentUserId = form.value.approvals[index].user_id
        const validUser = users.value[requestType].find(user => user.id === currentUserId)
        $(userSelect).val(validUser ? currentUserId : '').trigger('change.select2')
        if (!validUser && currentUserId) {
          form.value.approvals[index].user_id = null
          showAlert('Warning', `Previous user for ${requestType} is no longer valid. Please select a new user.`, 'warning')
        }
      }
    } else {
      form.value.approvals[index].availableUsers = []
      form.value.approvals[index].user_id = null
      await nextTick()
      const userSelect = document.querySelector(`.user-select[data-row="${index}"]`)
      if (userSelect) {
        destroySelect2(userSelect)
        initSelect2(userSelect, {
          placeholder: 'Select User',
          width: '100%',
          allowClear: true,
        }, (value) => {
          form.value.approvals[index].user_id = value ? Number(value) : null
        })
        $(userSelect).val('').trigger('change.select2')
      }
    }
  } catch (err) {
    console.error(`Error updating users for row ${index}:`, err)
    showAlert('Error', 'Failed to update user dropdown.', 'danger')
  }
}

const validateApprovals = () => {
  const defaultTypes = ['receive', 'approve']
  const presentTypes = form.value.approvals.map(approval => approval.request_type).filter(type => defaultTypes.includes(type))
  const uniqueTypes = new Set(presentTypes)

  if (uniqueTypes.size !== 2 || !defaultTypes.every(type => presentTypes.includes(type))) {
    showAlert('Error', 'Exactly one Receive and one Approve assignment are required.', 'danger')
    return false
  }

  return true
}

// Item Management
const addItem = async (productId) => {
  if (!productId) {
    showAlert('Warning', 'Please select a product.', 'warning')
    return
  }
  if (!form.value.warehouse_id) {
    showAlert('Error', 'Please select a from warehouse.', 'danger')
    return
  }
  if (!form.value.transaction_date) {
    showAlert('Error', 'Please select a transaction date.', 'danger')
    return
  }

  try {
    const product = products.value.find(p => p.id === Number(productId))
    if (!product) {
      showAlert('Error', 'Selected product not found.', 'danger')
      return
    }

    const existingItemIndex = form.value.items.findIndex(item => item.product_id === Number(productId))
    if (existingItemIndex !== -1) {
      form.value.items[existingItemIndex].quantity += 1
      showAlert('Info', `Quantity increased for ${product.item_code}`, 'info')
    } else {
      const newItem = {
        id: null,
        product_id: Number(productId),
        quantity: 1,
        unit_price: parseFloat(product.average_price) || 0,
        stock_on_hand: parseFloat(product.stock_on_hand) || 0,
        remarks: '',
      }
      form.value.items.push(newItem)
    }

    console.log('Updated items after addItem:', form.value.items)
    $(productSelect.value).val(null).trigger('select2:unselect')
  } catch (err) {
    console.error('Error adding item:', err)
    showAlert('Error', 'Failed to add product to table.', 'danger')
  }
}

const removeItem = (index) => {
  try {
    form.value.items.splice(index, 1)
    console.log('Items after removal:', form.value.items)
  } catch (err) {
    console.error('Error removing item:', err)
    showAlert('Error', 'Failed to remove item.', 'danger')
  }
}

const handleFileUpload = (event) => {
  try {
    const file = event.target.files[0]
    const validMimeTypes = [
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'application/vnd.ms-excel',
      'text/csv',
      'application/csv',
      'text/plain',
    ]
    if (file) {
      selectedFileName.value = file.name
      if (!validMimeTypes.includes(file.type)) {
        showAlert('Error', 'Please upload a valid Excel or CSV file (.xlsx, .xls, or .csv).', 'danger')
        fileInput.value.value = ''
        selectedFileName.value = ''
      }
    } else {
      selectedFileName.value = ''
    }
  } catch (err) {
    console.error('Error handling file upload:', err)
    showAlert('Error', 'Failed to process file upload.', 'danger')
  }
}

const triggerFileInput = () => {
  try {
    if (fileInput.value && typeof fileInput.value.click === 'function') {
      fileInput.value.click()
    }
  } catch (err) {
    console.error('Error triggering file input:', err)
    showAlert('Error', 'Failed to open file picker.', 'danger')
  }
}

const importFile = async () => {
  if (!fileInput.value.files[0]) {
    showAlert('Error', 'Please select a file to import.', 'danger')
    return
  }
  if (!form.value.warehouse_id) {
    showAlert('Error', 'Please select a from warehouse.', 'danger')
    return
  }
  if (!form.value.transaction_date) {
    showAlert('Error', 'Please select a transaction date.', 'danger')
    return
  }

  if (isImporting.value) return
  isImporting.value = true

  // Ensure products are up-to-date before importing
  await fetchProducts(form.value.warehouse_id, form.value.transaction_date)

  const formData = new FormData()
  formData.append('file', fileInput.value.files[0])

  try {
    const { data, status } = await axios.post('/api/inventory/stock-transfers/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    if (status === 200 && data.data) {
      form.value.items = data.data.items
        .map(item => {
          const product = products.value.find(p => p.id === Number(item.product_id))
          if (!product) {
            console.warn(`Product ${item.product_id} not found in products`)
            return null
          }
          return {
            id: item.id || null,
            product_id: Number(item.product_id),
            quantity: Math.max(parseFloat(item.quantity) || 1, 0.0001),
            unit_price: parseFloat(item.unit_price) || parseFloat(product.average_price) || 0,
            stock_on_hand: parseFloat(product.stock_on_hand) || 0,
            remarks: item.remarks || '',
          }
        })
        .filter(item => item !== null)
      console.log('Items after import:', form.value.items)
      showAlert('Success', 'Stock transfer items imported successfully.', 'success')
      fileInput.value.value = ''
      selectedFileName.value = ''
    } else {
      const errors = data.errors || [data.message || 'Unknown error occurred']
      const errorList = errors.map((error, index) => `${index + 1}. ${error}`).join('<br>')
      showAlert('Import Errors', `The following errors were found in the Excel file:<br><br>${errorList}`, 'danger')
    }
  } catch (err) {
    console.error('Import error:', err.response?.data || err)
    const errors = err.response?.data?.errors || [err.response?.data?.message || 'Failed to import stock transfer items.']
    const errorList = errors.map((error, index) => `${index + 1}. ${error}`).join('<br>')
    showAlert('Error', `Import failed:<br><br>${errorList}`, 'danger')
  } finally {
    isImporting.value = false
  }
}

// Form Submission
const submitForm = async () => {
  if (isSubmitting.value) return
  if (!form.value.transaction_date) {
    showAlert('Error', 'Please select a transaction date.', 'danger')
    return
  }
  if (!form.value.warehouse_id || !form.value.destination_warehouse_id) {
    showAlert('Error', 'Please select both from and to warehouses.', 'danger')
    return
  }
  if (form.value.items.length === 0) {
    showAlert('Error', 'At least one item is required to submit.', 'danger')
    return
  }
  if (form.value.items.some(item => !item.product_id || item.quantity <= 0 || item.unit_price < 0)) {
    showAlert('Error', 'All items must have a valid product, positive quantity, and non-negative unit price.', 'danger')
    return
  }
  if (!validateApprovals()) {
    return
  }

  isSubmitting.value = true
  try {
    const payload = {
      warehouse_id: form.value.warehouse_id,
      destination_warehouse_id: form.value.destination_warehouse_id,
      transaction_date: form.value.transaction_date,
      remarks: form.value.remarks?.trim() || null,
      items: form.value.items.map(item => ({
        id: item.id || null,
        product_id: item.product_id,
        quantity: parseFloat(item.quantity),
        unit_price: parseFloat(item.unit_price),
        remarks: item.remarks?.trim() || null,
      })),
      approvals: form.value.approvals.map(approval => ({
        id: approval.id || null,
        user_id: approval.user_id,
        request_type: approval.request_type,
      })),
    }

    const url = isEditMode.value ? `/api/inventory/stock-transfers/${props.initialData.id}` : '/api/inventory/stock-transfers'
    const method = isEditMode.value ? 'put' : 'post'
    await axios[method](url, payload)
    await showAlert('Success', isEditMode.value ? 'Stock transfer updated successfully.' : 'Stock transfer created successfully.', 'success')
    emit('formSubmitted')
    goToIndex()
  } catch (err) {
    console.error('Submit error:', err.response?.data || err)
    await showAlert('Error', err.response?.data?.message || err.message || 'Failed to save stock transfer.', 'danger')
  } finally {
    isSubmitting.value = false
  }
}

// Datepicker Initialization
const initDatepicker = async () => {
  await nextTick()
  $('#transaction_date').datepicker({
    format: 'yyyy-mm-dd',
    autoclose: true,
    todayHighlight: true,
    orientation: 'bottom left',
  }).on('changeDate', () => {
    form.value.transaction_date = $('#transaction_date').val()
  })
}

// Watchers
watch([() => form.value.warehouse_id, () => form.value.transaction_date], async ([newWarehouseId, newDate]) => {
  if (!newWarehouseId || !newDate) {
    console.log('Watcher skipped: Missing warehouse_id or transaction_date', {
      warehouse_id: newWarehouseId,
      transaction_date: newDate,
    })
    return
  }
  try {
    await fetchProducts(newWarehouseId, newDate)
    // Update existing items with new stock_on_hand and unit_price
    form.value.items = form.value.items
      .map(item => {
        const product = products.value.find(p => p.id === Number(item.product_id))
        if (!product) {
          console.warn(`Product ${item.product_id} not found in watcher update`)
          return null
        }
        return {
          ...item,
          stock_on_hand: parseFloat(product.stock_on_hand) || 0,
          unit_price: parseFloat(product.average_price) || 0,
        }
      })
      .filter(item => item !== null)
    console.log('Items after watcher update:', form.value.items)
  } catch (err) {
    console.error('Failed to refresh products:', err)
    showAlert('Error', 'Failed to refresh product data.', 'danger')
  }
})

// Lifecycle Hooks
onMounted(async () => {
  try {
    const defaultTypes = ['receive', 'approve']
    const seenTypes = new Set()

    // Fetch edit data if in edit mode
    if (isEditMode.value && props.initialData.id) {
      await fetchEditData(props.initialData.id)
    } else {
      // Initialize default approvals for create mode
      form.value.approvals = defaultTypes.map(type => ({
        id: null,
        request_type: type,
        user_id: null,
        isDefault: true,
        availableUsers: [],
      }))
    }

    // Fetch initial data
    await Promise.all([
      // Fetch products with initial warehouse_id and transaction_date if in edit mode
      isEditMode.value && form.value.warehouse_id && form.value.transaction_date
        ? fetchProducts(form.value.warehouse_id, form.value.transaction_date)
        : fetchProducts(),
      fetchWarehousesFrom(),
      fetchWarehousesTo(),
      Promise.all(defaultTypes.map(fetchUsersForApproval)),
    ])

    // Initialize Select2
    await nextTick()
    if (fromWarehouseSelect.value) {
      initSelect2(fromWarehouseSelect.value, {
        placeholder: 'Select From Warehouse',
        width: '100%',
        allowClear: true,
      }, (val) => (form.value.warehouse_id = val))
      if (form.value.warehouse_id) {
        $(fromWarehouseSelect.value).val(form.value.warehouse_id).trigger('change.select2')
      }
    }

    if (toWarehouseSelect.value) {
      initSelect2(toWarehouseSelect.value, {
        placeholder: 'Select To Warehouse',
        width: '100%',
        allowClear: true,
      }, (val) => (form.value.destination_warehouse_id = val))
      if (form.value.destination_warehouse_id) {
        $(toWarehouseSelect.value).val(form.value.destination_warehouse_id).trigger('change.select2')
      }
    }

    if (productSelect.value) {
      initSelect2(productSelect.value, {
        placeholder: 'Select Product',
        width: '100%',
        allowClear: true,
      })
      $(productSelect.value).on('select2:select', (e) => {
        const productId = e.params.data.id
        addItem(productId)
      })
    }

    // Initialize Approval Select2
    $('.approval-type-select').each(function () {
      const index = $(this).data('row')
      const approval = form.value.approvals[index]
      initSelect2(this, {
        placeholder: 'Select Type',
        width: '100%',
        allowClear: !approval.isDefault,
        disabled: approval.isDefault,
      }, (value) => {
        form.value.approvals[index].request_type = value || ''
        updateUsersForRow(index)
      })
      $(this).val(approval.request_type || '').trigger('change.select2')
    })

    $('.user-select').each(function () {
      const index = $(this).data('row')
      initSelect2(this, {
        placeholder: 'Select User',
        width: '100%',
        allowClear: true,
      }, (value) => {
        form.value.approvals[index].user_id = value ? Number(value) : null
      })
      $(this).val(form.value.approvals[index].user_id || '').trigger('change.select2')
    })

    await initDatepicker()
    if (form.value.transaction_date) {
      $('#transaction_date').datepicker('setDate', form.value.transaction_date)
    }

    // Update approval users after fetching edit data
    for (let i = 0; i < form.value.approvals.length; i++) {
      await updateUsersForRow(i)
    }
  } catch (err) {
    console.error('Error in onMounted:', err)
    showAlert('Error', 'Failed to initialize form.', 'danger')
  }
})

onUnmounted(() => {
  try {
    if (fromWarehouseSelect.value) {
      destroySelect2(fromWarehouseSelect.value)
    }
    if (toWarehouseSelect.value) {
      destroySelect2(toWarehouseSelect.value)
    }
    if (productSelect.value) {
      destroySelect2(productSelect.value)
    }
    $('.approval-type-select').each(function () {
      destroySelect2(this)
    })
    $('.user-select').each(function () {
      destroySelect2(this)
    })
    $('#transaction_date').datepicker('destroy')
  } catch (err) {
    console.error('Error in onUnmounted:', err)
  }
})
</script>