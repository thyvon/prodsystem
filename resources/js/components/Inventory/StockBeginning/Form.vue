<template>
  <div class="container-fluid">
    <form @submit.prevent="submitForm">
      <div class="card border mb-0 shadow">
        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
          <h4 class="mb-0 font-weight-bold">{{ isEditMode ? 'Edit Stock Beginning' : 'Create Stock Beginning' }}</h4>
          <button type="button" class="btn btn-outline-primary btn-sm" @click="goToIndex">
            <i class="fal fa-backward"></i>
          </button>
        </div>

        <div class="card-body">
          <div class="border rounded p-3 mb-4">
            <h5 class="font-weight-bold mb-3 text-primary">🏷️ Stock Beginning Details</h5>
            <div class="form-row">
              <div class="form-group col-md-3">
                <label for="beginning_date" class="font-weight-bold">Beginning Date</label>
                <input
                  v-model="form.beginning_date_display"
                  type="text"
                  class="form-control datepicker"
                  id="beginning_date"
                  required
                  placeholder="MMM DD, YYYY (e.g., Jul 25, 2025)"
                />
              </div>
              <div class="form-group col-md-3">
                <label for="warehouse_id" class="font-weight-bold">Warehouse</label>
                <select
                  ref="warehouseSelect"
                  v-model="form.warehouse_id"
                  class="form-control"
                  id="warehouse_id"
                  required
                >
                  <option value="">Select Warehouse</option>
                  <option
                    v-for="warehouse in warehouses"
                    :key="warehouse.id"
                    :value="warehouse.id"
                  >
                    {{ warehouse.name }}
                  </option>
                </select>
              </div>
            </div>
          </div>

          <div class="border rounded p-3 mb-4">
            <div class="form-row">
              <div class="form-group col-md-4">
                <label for="import_file" class="font-weight-bold">Import Stock Items</label>
                <div class="input-group">
                  <input
                    type="file"
                    class="d-none"
                    id="import_file"
                    accept=".xlsx,.xls,.csv"
                    ref="fileInput"
                    @change="handleFileUpload"
                  />
                  <button
                    type="button"
                    class="btn btn-outline-secondary"
                    @click="triggerFileInput"
                  >
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
                    href="/sampleExcel/stock_beginnings_sample.xlsx"
                    download="stock_beginnings_sample.xlsx"
                  >
                    <i class="fal fa-file-excel"></i>
                  </a>
                </div>
              </div>
              <div class="form-group col-md-8">
                <label for="product_select" class="font-weight-bold">Add Product</label>
                <select
                  ref="productSelect"
                  class="form-control"
                  id="product_select"
                >
                  <option value="">Select Product</option>
                  <option
                    v-for="product in products"
                    :key="product.id"
                    :value="product.id"
                  >
                    {{ product.item_code }} - {{ product.product_name }} {{ product.description }}
                  </option>
                </select>
              </div>
            </div>
            <h5 class="font-weight-bold mb-3 text-primary">📦 Stock Items</h5>
            <div class="table-responsive">
              <table id="stockItemsTable" class="table table-bordered table-sm table-hover">
                <thead class="thead-light">
                  <tr>
                    <th style="min-width: 100px;">Code</th>
                    <th style="min-width: 300px;">Description</th>
                    <th style="min-width: 30px;">UoM</th>
                    <th style="min-width: 100px;">Quantity</th>
                    <th style="min-width: 120px;">Unit Price</th>
                    <th style="min-width: 120px;">Total Value</th>
                    <th style="min-width: 200px;">Remarks</th>
                    <th style="min-width: 100px;">Actions</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>

          <div class="border rounded p-3 mb-4">
            <h5 class="font-weight-bold mb-3 text-primary">👥 Approval Assignments</h5>
            <div class="table-responsive">
              <table class="table table-bordered table-sm table-hover">
                <thead class="thead-light">
                  <tr>
                    <th style="min-width: 200px;">Approval Type</th>
                    <th style="min-width: 200px;">Assigned User</th>
                    <th style="min-width: 100px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(responder, index) in form.responders" :key="index">
                    <td>
                      <select
                        v-model="responder.request_type"
                        class="form-control approval-type-select"
                        :data-row="index"
                        required
                        :disabled="responder.isDefault"
                        @change="updateUsersForRow(index)"
                      >
                        <option value="">Select Type</option>
                        <option value="review">Review</option>
                        <option value="check">Check</option>
                        <option value="approve">Approve</option>
                      </select>
                    </td>
                    <td>
                      <select
                        v-model="responder.user_id"
                        class="form-control user-select"
                        :data-row="index"
                        required
                      >
                        <option value="">Select User</option>
                        <option
                          v-for="user in responder.availableUsers"
                          :key="user.id"
                          :value="user.id"
                        >
                          {{ user.name }}
                        </option>
                      </select>
                    </td>
                    <td>
                      <button
                        type="button"
                        class="btn btn-danger btn-sm"
                        @click="removeResponder(index)"
                        :disabled="responder.isDefault"
                      >
                        <i class="fal fa-trash-alt"></i> Remove
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <button
              type="button"
              class="btn btn-outline-primary btn-sm mt-2"
              @click="addResponder"
            >
              <i class="fal fa-plus"></i> Add Approval
            </button>
          </div>

          <div class="text-right">
            <button
              type="submit"
              class="btn btn-primary btn-sm mr-2"
              :disabled="isSubmitting || form.items.length === 0 || form.responders.length === 0"
            >
              <span
                v-if="isSubmitting"
                class="spinner-border spinner-border-sm mr-1"
              ></span>
              {{ isEditMode ? 'Update' : 'Create' }}
            </button>
            <button
              type="button"
              class="btn btn-secondary btn-sm"
              @click="goToIndex"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick, watch, computed } from 'vue'
import axios from 'axios'
import { showAlert } from '@/Utils/bootbox'
import { initSelect2, destroySelect2 } from '@/Utils/select2'

const props = defineProps({
  initialData: {
    type: Object,
    default: () => ({}),
  },
})

const emit = defineEmits(['submitted'])

const isSubmitting = ref(false)
const isImporting = ref(false)
const products = ref([])
const warehouses = ref([])
const users = ref({ review: [], check: [], approve: [] })
const warehouseSelect = ref(null)
const productSelect = ref(null)
const fileInput = ref(null)
const selectedFileName = ref('')
const isEditMode = ref(!!props.initialData?.id)
const stockBeginningId = ref(props.initialData?.id || null)
const table = ref(null)
let isAddingItem = false
let isAddingResponder = false

const form = ref({
  warehouse_id: null,
  beginning_date: '',
  beginning_date_display: '',
  items: [],
  responders: [],
})

const goToIndex = () => { window.location.href = `/stock-beginnings` }

const fetchUsersForApproval = async (requestType) => {
  try {
    const response = await axios.get('/api/stock-beginnings/users', {
      params: { request_type: requestType },
    })
    users.value[requestType] = Array.isArray(response.data.data) ? response.data.data : []
  } catch (err) {
    console.error(`Failed to load users for ${requestType}:`, err)
    showAlert('Error', `Failed to load users for ${requestType} approval.`, 'danger')
  }
}

const fetchProducts = async () => {
  try {
    const response = await axios.get(`/api/product-variants-stock`)
    products.value = Array.isArray(response.data) ? response.data : response.data.data
  } catch (err) {
    console.error('Failed to load products:', err)
    showAlert('Error', 'Failed to load products.', 'danger')
  }
}

const fetchWarehouses = async () => {
  try {
    const response = await axios.get(`/api/warehouses`)
    warehouses.value = Array.isArray(response.data) ? response.data : response.data.data
  } catch (err) {
    console.error('Failed to load warehouses:', err)
    showAlert('Error', 'Failed to load warehouses.', 'danger')
  }
}

const itemUnitName = computed(() => {
  return (productId) => {
    if (!productId) return '-'
    const product = products.value.find(p => p.id === productId)
    return product?.unit_name || '-'
  }
})

const ProductDescription = computed(() => {
  return (productId) => {
    if (!productId) return '-'
    const product = products.value.find(p => p.id === productId)
    return product ? `${product.product_name} ${product.description}` : '-'
  }
})

const ProductCode = computed(() => {
  return (productId) => {
    if (!productId) return '-'
    const product = products.value.find(p => p.id === productId)
    return product ? `${product.item_code}` : '-'
  }
})

const addResponder = async () => {
  if (isAddingResponder) return
  isAddingResponder = true

  try {
    form.value.responders.push({
      id: null,
      request_type: '',
      user_id: null,
      isDefault: false,
      availableUsers: [],
    })

    await nextTick()
    const index = form.value.responders.length - 1
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
      form.value.responders[index].request_type = value || ''
      updateUsersForRow(index)
    })
    $(approvalSelect).val(form.value.responders[index].request_type || '').trigger('change.select2')

    initSelect2(userSelect, {
      placeholder: 'Select User',
      width: '100%',
      allowClear: true,
    }, (value) => {
      form.value.responders[index].user_id = value ? Number(value) : null
    })
    $(userSelect).val(form.value.responders[index].user_id || '').trigger('change.select2')
  } catch (err) {
    console.error('Error adding responder:', err)
    showAlert('Error', 'Failed to add approval assignment.', 'danger')
  } finally {
    isAddingResponder = false
  }
}

const removeResponder = async (index) => {
  try {
    if (form.value.responders[index].isDefault) {
      showAlert('Error', 'Default approval types cannot be removed.', 'danger')
      return
    }
    const approvalSelect = document.querySelector(`.approval-type-select[data-row="${index}"]`)
    const userSelect = document.querySelector(`.user-select[data-row="${index}"]`)
    if (approvalSelect) destroySelect2(approvalSelect)
    if (userSelect) destroySelect2(userSelect)
    form.value.responders.splice(index, 1)
  } catch (err) {
    console.error('Error removing responder:', err)
    showAlert('Error', 'Failed to remove approval assignment.', 'danger')
  }
}

const updateUsersForRow = async (index) => {
  try {
    const requestType = form.value.responders[index].request_type
    if (requestType && ['review', 'check', 'approve'].includes(requestType)) {
      if (!users.value[requestType].length) {
        await fetchUsersForApproval(requestType)
      }
      form.value.responders[index].availableUsers = users.value[requestType]
      await nextTick()
      const userSelect = document.querySelector(`.user-select[data-row="${index}"]`)
      if (userSelect) {
        destroySelect2(userSelect)
        initSelect2(userSelect, {
          placeholder: 'Select User',
          width: '100%',
          allowClear: true,
        }, (value) => {
          form.value.responders[index].user_id = value ? Number(value) : null
        })
        // Only set user_id if it exists in the new availableUsers list
        const currentUserId = form.value.responders[index].user_id
        const validUser = users.value[requestType].find(user => user.id === currentUserId)
        $(userSelect).val(validUser ? currentUserId : '').trigger('change.select2')
        if (!validUser && currentUserId) {
          form.value.responders[index].user_id = null
          showAlert('Warning', `Previous user for ${requestType} is no longer valid. Please select a new user.`, 'warning')
        }
      }
    } else {
      form.value.responders[index].availableUsers = []
      form.value.responders[index].user_id = null
      await nextTick()
      const userSelect = document.querySelector(`.user-select[data-row="${index}"]`)
      if (userSelect) {
        destroySelect2(userSelect)
        initSelect2(userSelect, {
          placeholder: 'Select User',
          width: '100%',
          allowClear: true,
        }, (value) => {
          form.value.responders[index].user_id = value ? Number(value) : null
        })
        $(userSelect).val('').trigger('change.select2')
      }
    }
  } catch (err) {
    console.error(`Error updating users for row ${index}:`, err)
    showAlert('Error', 'Failed to update user dropdown.', 'danger')
  }
}

const validateResponders = () => {
  if (form.value.responders.length < 3) {
    showAlert('Error', 'At least three approval assignments (Review, Check, Approve) are required.', 'danger')
    return false
  }

  const defaultTypes = ['review', 'check', 'approve']
  const presentTypes = form.value.responders
    .map(responder => responder.request_type)
    .filter(type => defaultTypes.includes(type))

  if (presentTypes.length < 3 || !defaultTypes.every(type => presentTypes.includes(type))) {
    showAlert('Error', 'All default approval types (Review, Check, Approve) must be present.', 'danger')
    return false
  }

  for (const responder of form.value.responders) {
    if (!responder.request_type) {
      showAlert('Error', 'All approval types must be specified.', 'danger')
      return false
    }
    if (!responder.user_id) {
      showAlert('Error', 'All approval assignments must have a user selected.', 'danger')
      return false
    }
  }

  return true
}

const addItem = (productId) => {
  try {
    if (isAddingItem) {
      return
    }
    isAddingItem = true

    if (!productId) {
      console.warn('No product ID provided')
      showAlert('Warning', 'Please select a product.', 'warning')
      return
    }
    const product = products.value.find(p => p.id === Number(productId))
    if (!product) {
      console.error('Product not found for ID:', productId)
      showAlert('Error', 'Selected product not found.', 'danger')
      return
    }

    const existingItemIndex = form.value.items.findIndex(item => item.product_id === Number(productId))
    if (existingItemIndex !== -1) {
      form.value.items[existingItemIndex].quantity += 1
      if (table.value) {
        table.value.row(existingItemIndex).invalidate().draw()
      }
      showAlert('Info', `Quantity increased for ${product.item_code}`, 'info')
    } else {
      const newItem = {
        product_id: Number(productId),
        quantity: 1,
        unit_price: 0,
        remarks: '',
      }
      form.value.items.push(newItem)
      if (table.value) {
        table.value.rows.add([newItem]).draw()
      } else {
        console.warn('DataTable not initialized')
        showAlert('Error', 'Table not initialized.', 'danger')
      }
    }

    if (productSelect.value) {
      $(productSelect.value).val(null).trigger('select2:unselect')
    }
  } catch (err) {
    console.error('Error adding item:', err)
    showAlert('Error', 'Failed to add product to table.', 'danger')
  } finally {
    isAddingItem = false
  }
}

const removeItem = (index) => {
  try {
    form.value.items.splice(index, 1)
    if (table.value) {
      table.value.clear().rows.add(form.value.items).draw()
    }
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

  if (isImporting.value) return
  isImporting.value = true

  const formData = new FormData()
  formData.append('file', fileInput.value.files[0])

  try {
    const response = await axios.post(`/api/stock-beginnings/import`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    if (response.status === 200 && response.data.data) {
      const importedData = response.data.data
      form.value.items = importedData.items.map(item => ({
        id: item.id || null,
        product_id: Number(item.product_id),
        quantity: parseFloat(item.quantity) || 1,
        unit_price: parseFloat(item.unit_price) || 0,
        remarks: item.remarks || '',
      }))

      if (importedData.beginning_date) {
        const [year, month, day] = importedData.beginning_date.split('-')
        if (year && month && day) {
          const date = new Date(year, month - 1, day)
          const formattedDate = date.toLocaleDateString('en-US', {
            month: 'short',
            day: '2-digit',
            year: 'numeric',
          })
          form.value.beginning_date_display = formattedDate
          $('#beginning_date').datepicker('setDate', formattedDate)
        }
      }

      await nextTick()
      if (table.value) {
        table.value.clear().rows.add(form.value.items).draw()
      }

      if (warehouseSelect.value) {
        destroySelect2(warehouseSelect.value)
        initSelect2(warehouseSelect.value, {
          placeholder: 'Select Warehouse',
          width: '100%',
          allowClear: true,
        }, (v) => (form.value.warehouse_id = v))
        $(warehouseSelect.value).val(form.value.warehouse_id).trigger('change')
      }

      showAlert('Success', 'Stock beginnings data loaded into the form.', 'success')
      fileInput.value.value = ''
      selectedFileName.value = ''
      return
    }

    const errors = response.data.errors || [response.data.message || 'Unknown error occurred']
    if (errors.length > 0) {
      const errorList = errors.map((error, index) => `${index + 1}. ${error}`).join('<br>')
      showAlert('Import Errors', `The following errors were found in the Excel file:<br><br>${errorList}`, 'danger')
      return
    }

    showAlert('Error', 'Unexpected response from server.', 'danger')
  } catch (err) {
    console.error('Import error:', err.response?.data || err)
    const errors = err.response?.data?.errors || [err.response?.data?.message || 'Failed to import stock beginning.']
    const errorList = errors.map((error, index) => `${index + 1}. ${error}`).join('<br>')
    showAlert('Error', `Import failed:<br><br>${errorList}`, 'danger')
  } finally {
    isImporting.value = false
  }
}

const submitForm = async () => {
  if (isSubmitting.value) return
  if (form.value.items.length === 0) {
    await showAlert('Error', 'At least one item is required to submit.', 'danger')
    return
  }
  if (form.value.items.some(item => !item.product_id)) {
    await showAlert('Error', 'All items must have a valid product selected.', 'danger')
    return
  }
  if (!validateResponders()) {
    return
  }

  isSubmitting.value = true
  try {
    const payload = {
      warehouse_id: form.value.warehouse_id,
      beginning_date: form.value.beginning_date,
      items: form.value.items.map(item => ({
        id: item.id || null,
        product_id: item.product_id,
        quantity: parseFloat(item.quantity),
        unit_price: parseFloat(item.unit_price),
        remarks: item.remarks?.toString().trim() || null,
      })),
      responders: form.value.responders.map(responder => ({
        id: responder.id || null,
        user_id: responder.user_id,
        request_type: responder.request_type,
      })),
    }

    const url = isEditMode.value
      ? `/api/stock-beginnings/${stockBeginningId.value}`
      : `/api/stock-beginnings`
    const method = isEditMode.value ? 'put' : 'post'

    await axios[method](url, payload)
    await showAlert('Success', isEditMode.value ? 'Stock beginning updated successfully.' : 'Stock beginning created successfully.', 'success')
    emit('submitted')
    goToIndex()
  } catch (err) {
    console.error('Submit error:', err.response?.data || err)
    await showAlert('Error', err.response?.data?.message || err.message || 'Failed to save stock beginning.', 'danger')
  } finally {
    isSubmitting.value = false
  }
}

const initDatepicker = async () => {
  try {
    await nextTick()
    $('#beginning_date').datepicker({
      format: 'M dd, yyyy',
      autoclose: true,
      todayHighlight: true,
      orientation: 'bottom left',
    }).on('changeDate', (e) => {
      if (e.date) {
        const date = new Date(e.date)
        const year = date.getFullYear()
        const month = String(date.getMonth() + 1).padStart(2, '0')
        const day = String(date.getDate()).padStart(2, '0')
        form.value.beginning_date = `${year}-${month}-${day}`
        form.value.beginning_date_display = date.toLocaleDateString('en-US', {
          month: 'short',
          day: '2-digit',
          year: 'numeric',
        })
      } else {
        form.value.beginning_date = ''
        form.value.beginning_date_display = ''
      }
    })
  } catch (err) {
    console.error('Error initializing datepicker:', err)
    showAlert('Error', 'Failed to initialize date picker.', 'danger')
  }
}

watch(() => form.value.beginning_date_display, (newDisplayDate) => {
  try {
    if (newDisplayDate) {
      const date = new Date(newDisplayDate)
      if (!isNaN(date.getTime())) {
        const year = date.getFullYear()
        const month = String(date.getMonth() + 1).padStart(2, '0')
        const day = String(date.getDate()).padStart(2, '0')
        form.value.beginning_date = `${year}-${month}-${day}`
        $('#beginning_date').datepicker('setDate', newDisplayDate)
      } else {
        form.value.beginning_date = ''
        $('#beginning_date').datepicker('setDate', null)
      }
    } else {
      form.value.beginning_date = ''
      $('#beginning_date').datepicker('setDate', null)
    }
  } catch (err) {
    console.error('Error watching beginning_date_display:', err)
    showAlert('Error', 'Failed to process date change.', 'danger')
  }
})

onMounted(async () => {
  try {
    const defaultResponders = [
      { id: null, request_type: 'review', user_id: null, isDefault: true, availableUsers: [] },
      { id: null, request_type: 'check', user_id: null, isDefault: true, availableUsers: [] },
      { id: null, request_type: 'approve', user_id: null, isDefault: true, availableUsers: [] },
    ]

    if (props.initialData?.id) {
      form.value.warehouse_id = props.initialData.warehouse_id
      form.value.beginning_date = props.initialData.beginning_date
      if (props.initialData.beginning_date) {
        const [year, month, day] = props.initialData.beginning_date.split('-')
        const date = new Date(year, month - 1, day)
        form.value.beginning_date_display = date.toLocaleDateString('en-US', {
          month: 'short',
          day: '2-digit',
          year: 'numeric',
        })
      }
      form.value.items = props.initialData.items?.length
        ? props.initialData.items.map(item => ({
            id: item.id || null,
            product_id: Number(item.product_id),
            quantity: parseFloat(item.quantity) || 1,
            unit_price: parseFloat(item.unit_price) || 0,
            remarks: item.remarks || '',
          }))
        : []
      // Merge default responders with existing ones
      const existingResponders = props.initialData.responders?.length
        ? props.initialData.responders.map(responder => ({
            id: responder.id || null,
            user_id: Number(responder.user_id),
            request_type: ['review', 'check', 'approve'].includes(responder.request_type)
              ? responder.request_type
              : 'approve',
            isDefault: ['review', 'check', 'approve'].includes(responder.request_type),
            availableUsers: [],
          }))
        : []
      // Ensure default responders are included, updating IDs and user_id if they exist
      defaultResponders.forEach(defaultResponder => {
        const existing = existingResponders.find(r => r.request_type === defaultResponder.request_type)
        if (existing) {
          defaultResponder.id = existing.id
          defaultResponder.user_id = existing.user_id
        }
      })
      // Add non-default responders
      const additionalResponders = existingResponders.filter(r => !defaultResponders.some(dr => dr.request_type === r.request_type))
      form.value.responders = [...defaultResponders, ...additionalResponders]
    } else {
      form.value.responders = [...defaultResponders]
    }

    await Promise.all([
      fetchProducts(),
      fetchWarehouses(),
      Promise.all(['review', 'check', 'approve'].map(fetchUsersForApproval))
    ])

    // Initialize availableUsers for existing responders
    for (let i = 0; i < form.value.responders.length; i++) {
      await updateUsersForRow(i)
    }

    table.value = $('#stockItemsTable').DataTable({
      data: form.value.items,
      columns: [
        {
          data: 'product_id',
          render: (data) => ProductCode.value(data)
        },
        {
          data: 'product_id',
          render: (data) => ProductDescription.value(data)
        },
        {
          data: 'product_id',
          render: (data) => itemUnitName.value(data)
        },
        {
          data: 'quantity',
          render: (data, type, row, meta) => `
            <input type="number" class="form-control quantity-input" value="${data}" min="0.0001" step="0.0001" required data-row="${meta.row}"/>
          `
        },
        {
          data: 'unit_price',
          render: (data, type, row, meta) => `
            <input type="number" class="form-control unit-price-input" value="${data}" min="0" step="0.0001" required data-row="${meta.row}"/>
          `
        },
        {
          data: null,
          render: (data) => (data.quantity * data.unit_price).toFixed(4)
        },
        {
          data: 'remarks',
          render: (data, type, row, meta) => `
            <textarea class="form-control remarks-input" rows="1" maxlength="1000" data-row="${meta.row}">${data || ''}</textarea>
          `
        },
        {
          data: null,
          orderable: false,
          searchable: false,
          render: (data, type, row, meta) => `
            <button type="button" class="btn btn-danger btn-sm remove-btn" data-row="${meta.row}">
              <i class="fal fa-trash-alt"></i> Remove
            </button>
          `
        }
      ]
    })

    $('#stockItemsTable').on('change', '.quantity-input', function () {
      try {
        const index = $(this).data('row')
        form.value.items[index].quantity = parseFloat($(this).val()) || 1
        table.value.row(index).invalidate().draw()
      } catch (err) {
        console.error('Error updating quantity:', err)
        showAlert('Error', 'Failed to update quantity.', 'danger')
      }
    })

    $('#stockItemsTable').on('change', '.unit-price-input', function () {
      try {
        const index = $(this).data('row')
        form.value.items[index].unit_price = parseFloat($(this).val()) || 0
        table.value.row(index).invalidate().draw()
      } catch (err) {
        console.error('Error updating unit price:', err)
        showAlert('Error', 'Failed to update unit price.', 'danger')
      }
    })

    $('#stockItemsTable').on('input', '.remarks-input', function () {
      try {
        const index = $(this).data('row')
        form.value.items[index].remarks = $(this).val()
      } catch (err) {
        console.error('Error updating remarks:', err)
        showAlert('Error', 'Failed to update remarks.', 'danger')
      }
    })

    $('#stockItemsTable').on('click', '.remove-btn', function () {
      try {
        const index = $(this).data('row')
        removeItem(index)
      } catch (err) {
        console.error('Error removing item:', err)
        showAlert('Error', 'Failed to remove item.', 'danger')
      }
    })

    await nextTick()
    if (warehouseSelect.value) {
      initSelect2(warehouseSelect.value, {
        placeholder: 'Select Warehouse',
        width: '100%',
        allowClear: true,
      }, (v) => (form.value.warehouse_id = v))
      if (form.value.warehouse_id) {
        $(warehouseSelect.value).val(form.value.warehouse_id).trigger('change')
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
    } else {
      console.warn('productSelect ref is not defined')
      showAlert('Error', 'Product selection dropdown not initialized.', 'danger')
    }

    $('.approval-type-select').each(function () {
      const index = $(this).data('row')
      const isDefault = form.value.responders[index].isDefault
      initSelect2(this, {
        placeholder: 'Select Type',
        width: '100%',
        allowClear: !isDefault,
        disabled: isDefault,
      }, (value) => {
        form.value.responders[index].request_type = value || ''
        updateUsersForRow(index)
      })
      $(this).val(form.value.responders[index].request_type || '').trigger('change.select2')
    })

    $('.user-select').each(function () {
      const index = $(this).data('row')
      initSelect2(this, {
        placeholder: 'Select User',
        width: '100%',
        allowClear: true,
      }, (value) => {
        form.value.responders[index].user_id = value ? Number(value) : null
      })
      $(this).val(form.value.responders[index].user_id || '').trigger('change.select2')
    })

    await initDatepicker()
  } catch (err) {
    console.error('Error in onMounted:', err)
    showAlert('Error', 'Failed to initialize form.', 'danger')
  }
})

onUnmounted(() => {
  try {
    if (table.value) {
      table.value.destroy()
      table.value = null
    }
    if (warehouseSelect.value) {
      destroySelect2(warehouseSelect.value)
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
    $('#beginning_date').datepicker('destroy')
  } catch (err) {
    console.error('Error in onUnmounted:', err)
  }
})
</script>

<style scoped>
.card-header {
  border-bottom: 1px solid #e3e6f0;
}

.btn-icon {
  width: 38px;
  height: 38px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.table-responsive {
  min-height: 100px;
}
</style>