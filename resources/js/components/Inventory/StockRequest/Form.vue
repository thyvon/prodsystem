<template>
  <div class="container-fluid">
    <form @submit.prevent="submitForm">
      <div class="card border mb-0 shadow">
        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
          <h4 class="mb-0 font-weight-bold">{{ isEditMode ? 'Edit Stock Request' : 'Create Stock Request' }}</h4>
          <button type="button" class="btn btn-outline-primary btn-sm" @click="goToIndex">
            <i class="fal fa-backward"></i>
          </button>
        </div>

        <div class="card-body">
          <div class="border rounded p-3 mb-4">
            <h5 class="font-weight-bold mb-3 text-primary">🏷️ Stock Request Details</h5>
            <div class="form-row">
              <div class="form-group col-md-3">
                <label for="request_date" class="font-weight-bold">Request Date <span class="text-danger">*</span></label>
                <input
                  v-model="form.request_date_display"
                  type="text"
                  class="form-control datepicker"
                  id="request_date"
                  required
                  placeholder="Enter request date"
                />
              </div>
              <div class="form-group col-md-3">
                <label for="campus_id" class="font-weight-bold">Campus <span class="text-danger">*</span></label>
                <select
                  ref="campusSelect"
                  v-model="form.campus_id"
                  class="form-control"
                  id="campus_id"
                  required
                >
                  <option value="">Select Campus</option>
                  <option
                    v-for="campus in campuses"
                    :key="campus.id"
                    :value="campus.id"
                  >
                    {{ campus.name }}
                  </option>
                </select>
              </div>
              <div class="form-group col-md-3">
                <label for="type" class="font-weight-bold">Type <span class="text-danger">*</span></label>
                <select
                  ref="typeSelect"
                  class="form-control"
                  id="type"
                  required
                >
                  <option value="">Select Type</option>
                  <option value="Using">Using</option>
                  <option value="Donate">Donate</option>
                </select>
              </div>
              <div class="form-group col-md-3">
                <label for="warehouse_id" class="font-weight-bold">Warehouse <span class="text-danger">*</span></label>
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
            <div class="form-row">
              <div class="form-group col-md-12">
                <label for="purpose" class="font-weight-bold">Purpose <span class="text-danger">*</span></label>
                <textarea v-model="form.purpose" class="form-control" id="purpose" rows="2"></textarea>
              </div>
            </div>
          </div>

          <div class="border rounded p-3 mb-4">
            <div class="form-row mb-3">
              <div class="form-group col-md-12">
                 <h5 class="font-weight-bold mb-3 text-primary">📦 Request Items <span class="text-danger">*</span></h5>
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
                  </option>
                </select>
              </div>
            </div>
            <div class="table-responsive">
              <table id="stockItemsTable" class="table table-bordered table-sm table-hover">
                <thead class="thead-light">
                  <tr>
                    <th style="min-width: 100px;">Code</th>
                    <th style="min-width: 300px;">Description</th>
                    <th style="min-width: 30px;">UoM</th>
                    <th style="min-width: 100px;">Qty On Hand</th>
                    <th style="min-width: 100px;">Request Qty</th>
                    <th style="min-width: 120px;">Avg Price</th>
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
                        <option value="review">Review</option>
                        <option value="check">Check</option>
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
                        <option
                          v-for="user in approval.availableUsers"
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
            <button
              type="button"
              class="btn btn-outline-primary btn-sm mt-2"
              @click="addApproval"
            >
              <i class="fal fa-plus"></i> Add Approval
            </button>
          </div>

          <div class="text-right">
            <button
              type="submit"
              class="btn btn-primary btn-sm mr-2"
              :disabled="isSubmitting || form.items.length === 0 || form.approvals.length === 0"
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
const products = ref([])
const warehouses = ref([])
const campuses = ref([])
const users = ref({ review: [], check: [], approve: [] })
const warehouseSelect = ref(null)
const campusSelect = ref(null)
const typeSelect = ref(null)
const productSelect = ref(null)
const isEditMode = ref(!!props.initialData?.id)
const stockRequestId = ref(props.initialData?.id || null)
const table = ref(null)
let isAddingItem = false
let isAddingApproval = false

const form = ref({
  warehouse_id: null,
  campus_id: null,
  request_date: '',
  type: '',
  purpose: '',
  request_date_display: '',
  items: [],
  approvals: [],
})

const goToIndex = () => { window.location.href = `/inventory/stock-requests` }

const fetchUsersForApproval = async (requestType) => {
  try {
    const response = await axios.get('/api/inventory/stock-requests/users-for-approval', {
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
    const response = await axios.get(`/api/inventory/stock-requests/get-products`, {
      params: {
        warehouse_id: form.value.warehouse_id,
        date: form.value.request_date,
      }
    })
    products.value = Array.isArray(response.data) ? response.data : response.data.data

    // After fetching, re-render the table values
    await Promise.all([
      updateTableValues(),
      rebuildProductSelect()
    ])

    // Force DataTable to redraw with updated prices
    if (table.value) {
      table.value.rows().invalidate().draw()
    }
  } catch (err) {
    console.error('Failed to load products:', err)
    showAlert('Error', 'Failed to load products.', 'danger')
  }
}

const rebuildProductSelect = async () => {
  await nextTick();

  // Destroy previous Select2 instance
  if (productSelect.value) destroySelect2(productSelect.value);

  // Clear options
  const selectEl = productSelect.value;
  selectEl.innerHTML = '<option value="">Select Product</option>';

  products.value.forEach((p) => {
    const option = document.createElement('option');
    option.value = p.id;
    option.textContent = `${p.item_code} - ${p.product_name} ${p.description} (Stock: ${p.stock_on_hand})`;

    // Disable if stock_on_hand = 0
    if (p.stock_on_hand === 0 || p.stock_on_hand === null) {
      option.disabled = true;
    }

    selectEl.appendChild(option);
  });

  // Initialize Select2
  if (selectEl) {
    initSelect2(selectEl, { placeholder: 'Select Product', width: '100%', allowClear: true });

    $(selectEl).on('select2:select', (e) => {
      const productId = e.params.data.id;
      addItem(productId); // Your function to add the item row
    });
  }
};

const updateTableValues = () => {
  form.value.items.forEach((item, index) => {
    const product = products.value.find(p => p.id === item.product_id)
    if (product) {
      // Update the item's data to match the latest product info
      item.average_price = product.average_price
      item.stock_on_hand = product.stock_on_hand
      // Update the DOM inputs
      const $row = $(`#stockItemsTable tbody tr:eq(${index})`)
      $row.find('input').eq(0).val(product.stock_on_hand)
      $row.find('input').eq(1).val(product.average_price)
      const $qtyInput = $row.find('input.quantity-input')
      $qtyInput.val(Number(item.quantity) || 0)
      $qtyInput.attr('max', Number(product.stock_on_hand) || 0)
    }
  })
}



const fetchWarehouses = async () => {
  try {
    const response = await axios.get(`/api/inventory/stock-requests/get-warehouses`)
    warehouses.value = Array.isArray(response.data) ? response.data : response.data.data
  } catch (err) {
    console.error('Failed to load warehouses:', err)
    showAlert('Error', 'Failed to load warehouses.', 'danger')
  }
}

const fetchCampuses = async () => {
  try {
    const response = await axios.get(`/api/inventory/stock-requests/get-campuses`)
    campuses.value = Array.isArray(response.data) ? response.data : response.data.data
  } catch (err) {
    console.error('Failed to load campuses:', err)
    showAlert('Error', 'Failed to load campuses.', 'danger')
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

const ProductPrice = computed(() => {
  return (productId) => {
    if (!productId) return '-'
    const product = products.value.find(p => p.id === productId)
    return product ? `${product.average_price}` : '-'
  }
})

const StockOnhand = computed(() => {
  return (productId) => {
    if (!productId) return '-'
    const product = products.value.find(p => p.id === productId)
    return product ? `${product.stock_on_hand}` : '-'
  }
})

const ProductCode = computed(() => {
  return (productId) => {
    if (!productId) return '-'
    const product = products.value.find(p => p.id === productId)
    return product ? `${product.item_code}` : '-'
  }
})


const addApproval = async () => {
  if (isAddingApproval) return
  isAddingApproval = true

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
    $(approvalSelect).val(form.value.approvals[index].request_type || '').trigger('change.select2')

    initSelect2(userSelect, {
      placeholder: 'Select User',
      width: '100%',
      allowClear: true,
    }, (value) => {
      form.value.approvals[index].user_id = value ? Number(value) : null
    })
    $(userSelect).val(form.value.approvals[index].user_id || '').trigger('change.select2')
  } catch (err) {
    console.error('Error adding approval:', err)
    showAlert('Error', 'Failed to add approval assignment.', 'danger')
  } finally {
    isAddingApproval = false
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
    if (requestType && ['review', 'check', 'approve'].includes(requestType)) {
      if (!users.value[requestType].length) {
        await fetchUsersForApproval(requestType)
      }
      form.value.approvals[index].availableUsers = users.value[requestType]
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
        // Only set user_id if it exists in the new availableUsers list
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
  if (form.value.approvals.length < 3) {
    showAlert('Error', 'At least three approval assignments (Review, Check, Approve) are required.', 'danger')
    return false
  }

  const defaultTypes = ['review', 'check', 'approve']
  const presentTypes = form.value.approvals
    .map(approval => approval.request_type)
    .filter(type => defaultTypes.includes(type))

  if (presentTypes.length < 3 || !defaultTypes.every(type => presentTypes.includes(type))) {
    showAlert('Error', 'All default approval types (Review, Check, Approve) must be present.', 'danger')
    return false
  }

  for (const approval of form.value.approvals) {
    if (!approval.request_type) {
      showAlert('Error', 'All approval types must be specified.', 'danger')
      return false
    }
    if (!approval.user_id) {
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
        average_price: product.average_price,
        stock_on_hand: product.stock_on_hand,
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
  if (!validateApprovals()) {
    return
  }

  isSubmitting.value = true
  try {
    const payload = {
      warehouse_id: form.value.warehouse_id,
      campus_id: form.value.campus_id,
      request_date: form.value.request_date,
      type: form.value.type,
      purpose: form.value.purpose,
      items: form.value.items.map(item => ({
        id: item.id || null,
        product_id: item.product_id,
        quantity: parseFloat(item.quantity),
        average_price: parseFloat(item.average_price),
        remarks: item.remarks?.toString().trim() || null,
      })),
      approvals: form.value.approvals.map(approval => ({
        id: approval.id || null,
        user_id: approval.user_id,
        request_type: approval.request_type,
      })),
    }

    console.log(payload);

    const url = isEditMode.value
      ? `/api/inventory/stock-requests/${stockRequestId.value}`
      : `/api/inventory/stock-requests`
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
    $('#request_date').datepicker({
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
        form.value.request_date = `${year}-${month}-${day}`
        form.value.request_date_display = date.toLocaleDateString('en-US', {
          month: 'short',
          day: '2-digit',
          year: 'numeric',
        })
      } else {
        form.value.request_date = ''
        form.value.request_date_display = ''
      }
    })
  } catch (err) {
    console.error('Error initializing datepicker:', err)
    showAlert('Error', 'Failed to initialize date picker.', 'danger')
  }
}

watch(() => form.value.request_date_display, (newDisplayDate) => {
  try {
    if (newDisplayDate) {
      const date = new Date(newDisplayDate)
      if (!isNaN(date.getTime())) {
        const year = date.getFullYear()
        const month = String(date.getMonth() + 1).padStart(2, '0')
        const day = String(date.getDate()).padStart(2, '0')
        form.value.request_date = `${year}-${month}-${day}`
        $('#request_date').datepicker('setDate', newDisplayDate)
      } else {
        form.value.request_date = ''
        $('#request_date').datepicker('setDate', null)
      }
    } else {
      form.value.request_date = ''
      $('#request_date').datepicker('setDate', null)
    }
  } catch (err) {
    console.error('Error watching request_date_display:', err)
    showAlert('Error', 'Failed to process date change.', 'danger')
  }
})

watch(
  [() => form.value.warehouse_id, () => form.value.request_date],
  () => {
    if (form.value.warehouse_id && form.value.request_date) {
      fetchProducts()
    }
  }
)

onMounted(async () => {
  try {
    // Group incoming approvals and flag only the first one of each type as default
    const defaultTypes = ['review', 'check', 'approve']
    const seenTypes = new Set()

    if (props.initialData?.id) {
      form.value.warehouse_id = props.initialData.warehouse_id
      form.value.campus_id = props.initialData.campus_id
      form.value.type = props.initialData.type
      form.value.purpose = props.initialData.purpose
      form.value.request_date = props.initialData.request_date

      if (props.initialData.request_date) {
        const [year, month, day] = props.initialData.request_date.split('-')
        const date = new Date(year, month - 1, day)
        form.value.request_date_display = date.toLocaleDateString('en-US', {
          month: 'short',
          day: '2-digit',
          year: 'numeric',
        })
      }

      // Prepare stock items
      form.value.items = props.initialData.items?.map(item => ({
        id: item.id || null,
        product_id: Number(item.product_id),
        quantity: parseFloat(item.quantity) || 1,
        average_price: parseFloat(item.average_price) || 0,
        remarks: item.remarks || '',
      })) || []

      // Approvals: flag first of each type as isDefault, allow duplicates
      form.value.approvals = props.initialData.approvals?.map((approval, i, arr) => {
        const isFirstOfType = !seenTypes.has(approval.request_type)
        if (isFirstOfType && defaultTypes.includes(approval.request_type)) {
          seenTypes.add(approval.request_type)
        }

        return {
          id: approval.id || null,
          user_id: Number(approval.user_id),
          request_type: approval.request_type || 'approve',
          isDefault: isFirstOfType && defaultTypes.includes(approval.request_type),
          availableUsers: [],
        }
      }) || []
    } else {
      // If new entry, push one default of each type
      form.value.approvals = defaultTypes.map(type => ({
        id: null,
        request_type: type,
        user_id: null,
        isDefault: true,
        availableUsers: [],
      }))
    }

    // Load all necessary data
    await Promise.all([
      fetchProducts(),
      fetchWarehouses(),
      fetchCampuses(),
      Promise.all(defaultTypes.map(fetchUsersForApproval))
    ])

    // Load availableUsers per approval row
    for (let i = 0; i < form.value.approvals.length; i++) {
      await updateUsersForRow(i)
    }

    // Initialize DataTable
    table.value = $('#stockItemsTable').DataTable({
      data: form.value.items,
      responsive: true,
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
          data: 'product_id',
          render: (data) => `<input type="text" class="form-control" value="${StockOnhand.value(data)}" readonly />`
        },
        {
          data: 'quantity',
          render: (data, type, row, meta) => `
            <input type="number" class="form-control quantity-input" value="${data}" min="0.0001" step="0.0001" required data-row="${meta.row}"/>
          `
        },
        {
          data: 'product_id',
          render: (data) => `<input type="text" class="form-control" value="${ProductPrice.value(data)}" readonly />`
        },
        {
          data: null,
          render: (data) => `<input type="text" class="form-control" value="${(data.quantity * data.average_price).toFixed(4)}" readonly />`
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

    // Table input bindings
    $('#stockItemsTable').on('change', '.quantity-input', function () {
      const index = $(this).data('row')
      form.value.items[index].quantity = parseFloat($(this).val()) || 1
      table.value.row(index).invalidate().draw()
    })

    $('#stockItemsTable').on('input', '.remarks-input', function () {
      const index = $(this).data('row')
      form.value.items[index].remarks = $(this).val()
    })

    $('#stockItemsTable').on('click', '.remove-btn', function () {
      const index = $(this).data('row')
      removeItem(index)
    })

    // Init warehouse select
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

    // Init campus select
    await nextTick()
    if (campusSelect.value) {
      initSelect2(campusSelect.value, {
        placeholder: 'Select Campus',
        width: '100%',
        allowClear: true,
      }, (v) => (form.value.campus_id = v))

      if (form.value.campus_id) {
        $(campusSelect.value).val(form.value.campus_id).trigger('change')
      }
    }

    if (typeSelect.value) {
      initSelect2(typeSelect.value, {
        placeholder: 'Select Campus',
        width: '100%',
        allowClear: true,
      }, (v) => {
        // Always update Vue's form value when Select2 changes
        form.value.type = v || ''
      })

      // Set initial value if needed
      $(typeSelect.value).val(form.value.type).trigger('change')
    }

    // Approval Type Select2
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

    // User Select2
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
    if (campusSelect.value) {
      destroySelect2(campusSelect.value)
    }

    if (typeSelect.value) {
      destroySelect2(typeSelect.value)
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
    $('#request_date').datepicker('destroy')
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
</style>