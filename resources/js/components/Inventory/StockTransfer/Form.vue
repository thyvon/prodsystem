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
                    {{ product.item_code }} - {{ product.product_name }} {{ product.description }}, Stock: {{ product.stock_on_hand }}
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
                    <td>{{ item.item_code }}</td>
                    <td>{{ item.product_name }} {{ item.description }}</td>
                    <td>{{ item.unit_name }}</td>
                    <td><input type="number" class="form-control" :value="item.stock_on_hand.toFixed(4)" readonly /></td>
                    <td><input type="number" class="form-control" v-model.number="item.quantity" min="0.0001" step="0.0001" required /></td>
                    <td><input type="number" class="form-control" :value="item.unit_price ? item.unit_price.toFixed(4) : '0.0000'" readonly /></td>
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
                        <option value="initial">Initial</option>
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

const emit = defineEmits(['submitted'])
const isSubmitting = ref(false)
const isImporting = ref(false)
const products = ref([])
const warehousesFrom = ref([])
const warehousesTo = ref([])
const users = ref({ initial: [], approve: [] })
const fromWarehouseSelect = ref(null)
const toWarehouseSelect = ref(null)
const productSelect = ref(null)
const fileInput = ref(null)
const selectedFileName = ref('')
const isEditMode = computed(() => !!props.initialData.id)
const productsCache = ref({})

const form = ref({
  transaction_date: '',
  warehouse_id: '',
  destination_warehouse_id: '',
  remarks: '',
  items: [],
  approvals: [],
})

const mapItem = (product, existingItem = {}) => ({
  id: existingItem.id || null,
  product_id: Number(product.id),
  item_code: product.item_code || 'N/A',
  product_name: product.product_name || '',
  description: product.description || '',
  unit_name: product.unit_name || 'N/A',
  quantity: parseFloat(existingItem.quantity) || 1,
  unit_price: parseFloat(product.average_price) || 0, // Always use average_price
  stock_on_hand: parseFloat(product.stock_on_hand) || 0,
  total_value: parseFloat(((parseFloat(existingItem.quantity) || 1) * (parseFloat(product.average_price) || 0)).toFixed(4)),
  remarks: existingItem.remarks || '',
})

const goToIndex = () => {
  window.location.href = '/inventory/stock-transfers'
}

const fetchEditData = async (stockTransferId) => {
  try {
    const { data } = await axios.get(`/api/inventory/stock-transfers/${stockTransferId}/edit`)
    if (data.data) {
      form.value = {
        transaction_date: data.data.transaction_date || '',
        warehouse_id: data.data.warehouse_id || '',
        destination_warehouse_id: data.data.destination_warehouse_id || '',
        remarks: data.data.remarks || '',
        items: [],
        approvals: data.data.approvals?.map(approval => ({
          id: approval.id || null,
          user_id: Number(approval.responder_id) || null,
          request_type: approval.request_type || '',
          isDefault: ['initial', 'approve'].includes(approval.request_type),
          availableUsers: [],
        })) || [],
      }
      // Fetch products to get average_price
      if (data.data.warehouse_id && data.data.transaction_date) {
        await fetchProducts(data.data.warehouse_id, data.data.transaction_date)
        form.value.items = data.data.stock_transfer_items?.map(item => {
          const product = products.value.find(p => p.id === Number(item.product_id))
          return product ? mapItem(product, item) : null
        }).filter(Boolean) || []
        productsCache.value[form.value.warehouse_id] = JSON.parse(JSON.stringify(form.value.items))
      }
    } else {
      throw new Error('Invalid response data')
    }
  } catch (err) {
    showAlert('Error', err.response?.data?.message || 'Failed to load stock transfer data.', 'danger')
  }
}

const fetchProducts = async (warehouseId = '', cutoffDate = '') => {
  try {
    const params = { warehouse_id: warehouseId, cutoff_date: cutoffDate }
    const { data } = await axios.get('/api/inventory/stock-transfers/get-products', { params })
    products.value = (Array.isArray(data) ? data : data.data).map(product => ({
      ...product,
      unit_price: parseFloat(product.average_price) || 0,
    }))
  } catch (err) {
    showAlert('Error', 'Failed to load products.', 'danger')
  }
}

const fetchWarehousesFrom = async () => {
  try {
    const { data } = await axios.get('/api/inventory/stock-transfers/get-warehouses-from')
    warehousesFrom.value = Array.isArray(data) ? data : data.data
  } catch (err) {
    showAlert('Error', 'Failed to load from warehouses.', 'danger')
  }
}

const fetchWarehousesTo = async () => {
  try {
    const { data } = await axios.get('/api/inventory/stock-transfers/get-warehouses-to')
    warehousesTo.value = Array.isArray(data) ? data : data.data
  } catch (err) {
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
    showAlert('Error', `Failed to load users for ${requestType} approval.`, 'danger')
  }
}

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
      showAlert('Error', 'Failed to initialize approval dropdowns.', 'danger')
      return
    }

    initSelect2(approvalSelect, { placeholder: 'Select Type', width: '100%', allowClear: true }, (value) => {
      form.value.approvals[index].request_type = value || ''
      updateUsersForRow(index)
    })
    $(approvalSelect).val('').trigger('change.select2')

    initSelect2(userSelect, { placeholder: 'Select User', width: '100%', allowClear: true }, (value) => {
      form.value.approvals[index].user_id = value ? Number(value) : null
    })
    $(userSelect).val('').trigger('change.select2')
  } catch (err) {
    showAlert('Error', 'Failed to add approval assignment.', 'danger')
  }
}

const removeApproval = async (index) => {
  if (form.value.approvals[index].isDefault) {
    showAlert('Error', 'Default approval types cannot be removed.', 'danger')
    return
  }
  const approvalSelect = document.querySelector(`.approval-type-select[data-row="${index}"]`)
  const userSelect = document.querySelector(`.user-select[data-row="${index}"]`)
  if (approvalSelect) destroySelect2(approvalSelect)
  if (userSelect) destroySelect2(userSelect)
  form.value.approvals.splice(index, 1)
}

const updateUsersForRow = async (index) => {
  const requestType = form.value.approvals[index].request_type
  if (!requestType || !['initial', 'approve'].includes(requestType)) {
    form.value.approvals[index].availableUsers = []
    form.value.approvals[index].user_id = null
    await updateUserSelect(index, '')
    return
  }

  if (!users.value[requestType]?.length) {
    await fetchUsersForApproval(requestType)
  }
  form.value.approvals[index].availableUsers = users.value[requestType] || []
  await updateUserSelect(index, form.value.approvals[index].user_id)
}

const updateUserSelect = async (index, currentUserId) => {
  await nextTick()
  const userSelect = document.querySelector(`.user-select[data-row="${index}"]`)
  if (!userSelect) return

  destroySelect2(userSelect)
  initSelect2(userSelect, { placeholder: 'Select User', width: '100%', allowClear: true }, (value) => {
    form.value.approvals[index].user_id = value ? Number(value) : null
  })

  const validUser = users.value[form.value.approvals[index].request_type]?.find(user => user.id === currentUserId)
  $(userSelect).val(validUser ? currentUserId : '').trigger('change.select2')
  if (!validUser && currentUserId) {
    form.value.approvals[index].user_id = null
    showAlert('Warning', `Previous user for ${form.value.approvals[index].request_type} is no longer valid.`, 'warning')
  }
}

const validateApprovals = () => {
  const requiredTypes = ['initial', 'approve']
  const presentTypes = form.value.approvals.map(a => a.request_type).filter(t => requiredTypes.includes(t))
  if (new Set(presentTypes).size !== requiredTypes.length || !requiredTypes.every(t => presentTypes.includes(t))) {
    showAlert('Error', 'Exactly one Initial and one Approve assignment are required.', 'danger')
    return false
  }
  return true
}

const addItem = async (productId) => {
  if (!productId || !form.value.warehouse_id || !form.value.transaction_date) {
    showAlert('Error', !productId ? 'Please select a product.' : !form.value.warehouse_id ? 'Please select a from warehouse.' : 'Please select a transaction date.', 'danger')
    return
  }

  const product = products.value.find(p => p.id === Number(productId))
  if (!product) {
    showAlert('Error', 'Selected product not found.', 'danger')
    return
  }

  const existingItemIndex = form.value.items.findIndex(item => item.product_id === Number(productId))
  if (existingItemIndex !== -1) {
    form.value.items[existingItemIndex].quantity += 1
    form.value.items[existingItemIndex].total_value = parseFloat((form.value.items[existingItemIndex].quantity * form.value.items[existingItemIndex].unit_price).toFixed(4))
    showAlert('Info', `Quantity increased for ${product.item_code}`, 'info')
  } else {
    form.value.items.push(mapItem(product))
  }

  productsCache.value[form.value.warehouse_id] = JSON.parse(JSON.stringify(form.value.items))
  $(productSelect.value).val(null).trigger('select2:unselect')
}

const removeItem = (index) => {
  form.value.items.splice(index, 1)
  productsCache.value[form.value.warehouse_id] = JSON.parse(JSON.stringify(form.value.items))
}

const handleFileUpload = (event) => {
  const file = event.target.files[0]
  const validMimeTypes = [
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-excel',
    'text/csv',
    'application/csv',
    'text/plain',
  ]
  selectedFileName.value = file?.name || ''
  if (file && !validMimeTypes.includes(file.type)) {
    showAlert('Error', 'Please upload a valid Excel or CSV file (.xlsx, .xls, or .csv).', 'danger')
    fileInput.value.value = ''
    selectedFileName.value = ''
  }
}

const triggerFileInput = () => {
  fileInput.value?.click()
}

const importFile = async () => {
  if (!fileInput.value.files[0] || !form.value.warehouse_id || !form.value.transaction_date) {
    showAlert('Error', !fileInput.value.files[0] ? 'Please select a file to import.' : !form.value.warehouse_id ? 'Please select a from warehouse.' : 'Please select a transaction date.', 'danger')
    return
  }

  if (isImporting.value) return
  isImporting.value = true

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
          return product ? mapItem(product, item) : null
        })
        .filter(Boolean)
      productsCache.value[form.value.warehouse_id] = JSON.parse(JSON.stringify(form.value.items))
      showAlert('Success', 'Stock transfer items imported successfully.', 'success')
      fileInput.value.value = ''
      selectedFileName.value = ''
    } else {
      const errors = data.errors || [data.message || 'Unknown error occurred']
      showAlert('Import Errors', `The following errors were found in the Excel file:<br><br>${errors.map((e, i) => `${i + 1}. ${e}`).join('<br>')}`, 'danger')
    }
  } catch (err) {
    const errors = err.response?.data?.errors || [err.response?.data?.message || 'Failed to import stock transfer items.']
    showAlert('Error', `Import failed:<br><br>${errors.map((e, i) => `${i + 1}. ${e}`).join('<br>')}`, 'danger')
  } finally {
    isImporting.value = false
  }
}

const submitForm = async () => {
  if (isSubmitting.value || !form.value.transaction_date || !form.value.warehouse_id || !form.value.destination_warehouse_id || !form.value.items.length || form.value.items.some(item => !item.product_id || item.quantity <= 0 || item.unit_price < 0)) {
    showAlert('Error', !form.value.transaction_date ? 'Please select a transaction date.' : !form.value.warehouse_id || !form.value.destination_warehouse_id ? 'Please select both from and to warehouses.' : !form.value.items.length ? 'At least one item is required.' : 'All items must have a valid product, positive quantity, and non-negative unit price.', 'danger')
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
        total_value: parseFloat((item.quantity * item.unit_price).toFixed(4)),
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
    emit('submitted')
    goToIndex()
  } catch (err) {
    showAlert('Error', err.response?.data?.message || err.message || 'Failed to save stock transfer.', 'danger')
  } finally {
    isSubmitting.value = false
  }
}

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

watch(() => form.value.warehouse_id, async newId => {
  if (!newId) {
    form.value.items = []
    productsCache.value = {}
    return
  }

  if (productsCache.value[newId]) {
    form.value.items = JSON.parse(JSON.stringify(productsCache.value[newId]))
    return
  }

  await fetchProducts(newId, form.value.transaction_date)
})

watch([() => form.value.warehouse_id, () => form.value.transaction_date], async ([newId, newDate]) => {
  if (!newId || !newDate) return
  try {
    await fetchProducts(newId, newDate)
    form.value.items = form.value.items
      .map(item => {
        const product = products.value.find(p => p.id === item.product_id)
        return product ? mapItem(product, item) : null
      })
      .filter(Boolean)
    productsCache.value[newId] = JSON.parse(JSON.stringify(form.value.items))
  } catch (err) {
    showAlert('Error', 'Failed to refresh stock and price.', 'danger')
  }
})

onMounted(async () => {
  const defaultTypes = ['initial', 'approve']
  if (isEditMode.value && props.initialData.id) {
    await fetchEditData(props.initialData.id)
  } else {
    form.value.approvals = defaultTypes.map(type => ({
      id: null,
      request_type: type,
      user_id: null,
      isDefault: true,
      availableUsers: [],
    }))
  }

  await Promise.all([
    isEditMode.value && form.value.warehouse_id && form.value.transaction_date
      ? fetchProducts(form.value.warehouse_id, form.value.transaction_date)
      : fetchProducts(),
    fetchWarehousesFrom(),
    fetchWarehousesTo(),
    Promise.all(defaultTypes.map(fetchUsersForApproval)),
  ])

  await nextTick()
  if (fromWarehouseSelect.value) {
    initSelect2(fromWarehouseSelect.value, { placeholder: 'Select From Warehouse', width: '100%', allowClear: true }, (val) => (form.value.warehouse_id = val))
    if (form.value.warehouse_id) $(fromWarehouseSelect.value).val(form.value.warehouse_id).trigger('change.select2')
  }

  if (toWarehouseSelect.value) {
    initSelect2(toWarehouseSelect.value, { placeholder: 'Select To Warehouse', width: '100%', allowClear: true }, (val) => (form.value.destination_warehouse_id = val))
    if (form.value.destination_warehouse_id) $(toWarehouseSelect.value).val(form.value.destination_warehouse_id).trigger('change.select2')
  }

  if (productSelect.value) {
    initSelect2(productSelect.value, { placeholder: 'Select Product', width: '100%', allowClear: true })
    $(productSelect.value).on('select2:select', (e) => addItem(e.params.data.id))
  }

  $('.approval-type-select').each(function () {
    const index = $(this).data('row')
    const approval = form.value.approvals[index]
    initSelect2(this, { placeholder: 'Select Type', width: '100%', allowClear: !approval.isDefault, disabled: approval.isDefault }, (value) => {
      form.value.approvals[index].request_type = value || ''
      updateUsersForRow(index)
    })
    $(this).val(approval.request_type || '').trigger('change.select2')
  })

  $('.user-select').each(function () {
    const index = $(this).data('row')
    initSelect2(this, { placeholder: 'Select User', width: '100%', allowClear: true }, (value) => {
      form.value.approvals[index].user_id = value ? Number(value) : null
    })
    $(this).val(form.value.approvals[index].user_id || '').trigger('change.select2')
  })

  await initDatepicker()
  if (form.value.transaction_date) $('#transaction_date').datepicker('setDate', form.value.transaction_date)

  for (let i = 0; i < form.value.approvals.length; i++) {
    await updateUsersForRow(i)
  }
})

onUnmounted(() => {
  if (fromWarehouseSelect.value) destroySelect2(fromWarehouseSelect.value)
  if (toWarehouseSelect.value) destroySelect2(toWarehouseSelect.value)
  if (productSelect.value) destroySelect2(productSelect.value)
  $('.approval-type-select').each(function () { destroySelect2(this) })
  $('.user-select').each(function () { destroySelect2(this) })
  $('#transaction_date').datepicker('destroy')
})
</script>