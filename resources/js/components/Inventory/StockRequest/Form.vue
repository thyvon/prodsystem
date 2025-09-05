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
          <!-- Stock Request Details -->
          <div class="border rounded p-3 mb-4">
            <h5 class="font-weight-bold mb-3 text-primary">🏷️ Stock Request Details</h5>
            <div class="form-row">
              <div class="form-group col-md-4">
                <label for="request_date" class="font-weight-bold">Request Date <span class="text-danger">*</span></label>
                <input v-model="form.request_date" type="text" class="form-control datepicker" id="request_date" required placeholder="Enter request date" />
              </div>
              <div class="form-group col-md-4">
                <label for="type" class="font-weight-bold">Type <span class="text-danger">*</span></label>
                <select ref="typeSelect" v-model="form.type" class="form-control" id="type" required>
                  <option value="">Select Type</option>
                  <option value="Using">Using</option>
                  <option value="Donate">Donate</option>
                </select>
              </div>
              <div class="form-group col-md-4">
                <label for="warehouse_id" class="font-weight-bold">Warehouse <span class="text-danger">*</span></label>
                <select ref="warehouseSelect" v-model="form.warehouse_id" class="form-control" id="warehouse_id" required>
                  <option value="">Select Warehouse</option>
                  <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">{{ warehouse.name }}</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label for="purpose" class="font-weight-bold">Purpose <span class="text-danger">*</span></label>
              <textarea v-model="form.purpose" class="form-control" id="purpose" rows="2"></textarea>
            </div>
          </div>

          <!-- Request Items -->
          <div class="border rounded p-3 mb-4">
            <h5 class="font-weight-bold mb-3 text-primary">📦 Request Items <span class="text-danger">*</span></h5>
            <div class="form-row mb-3">
              <div class="form-group col-md-12">
                <label for="product_select" class="font-weight-bold">Add Product</label>
                <select ref="productSelect" class="form-control" id="product_select">
                  <option value="">Select Product</option>
                  <option v-for="product in products" :key="product.id" :value="product.id" :disabled="product.stock_on_hand === 0 || product.stock_on_hand === null">
                    {{ product.item_code }} - {{ product.product_name }} {{ product.description }} (Stock: {{ product.stock_on_hand || 0 }})
                  </option>
                </select>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-bordered table-sm table-hover">
                <thead class="thead-light">
                  <tr>
                    <th style="min-width: 100px;">Code</th>
                    <th style="min-width: 300px;">Description</th>
                    <th style="min-width: 30px;">UoM</th>
                    <th style="min-width: 100px;">Qty On Hand</th>
                    <th style="min-width: 100px;">Request Qty</th>
                    <th style="min-width: 120px;">Avg Price</th>
                    <th style="min-width: 120px;">Total Value</th>
                    <th style="min-width: 120px;">Department</th>
                    <th style="min-width: 120px;">Campus</th>
                    <th style="min-width: 200px;">Remarks</th>
                    <th style="min-width: 100px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, index) in form.items" :key="item.product_id">
                    <td>{{ getProductCode(item.product_id) }}</td>
                    <td>{{ getProductDescription(item.product_id) }}</td>
                    <td>{{ getItemUnitName(item.product_id) }}</td>
                    <td><input class="form-control" :value="getStockOnHand(item.product_id)" readonly /></td>
                    <td><input type="number" class="form-control quantity-input" v-model.number="item.quantity" min="0.0001" step="0.0001" :max="getStockOnHand(item.product_id)" /></td>
                    <td><input class="form-control" :value="getProductPrice(item.product_id)" readonly /></td>
                    <td><input class="form-control" :value="(item.quantity * getProductPrice(item.product_id)).toFixed(4)" readonly /></td>
                    <td>
                      <select class="form-control department-select" v-model="item.department_id" :data-row="index">
                        <option v-for="dep in form.departments" :key="dep.id" :value="dep.id">{{ dep.short_name }}</option>
                      </select>
                    </td>
                    <td>
                      <select class="form-control campus-select" v-model="item.campus_id" :data-row="index">
                        <option v-for="c in form.campuses" :key="c.id" :value="c.id">{{ c.short_name }}</option>
                      </select>
                    </td>
                    <td><textarea class="form-control remarks-input" v-model="item.remarks"></textarea></td>
                    <td>
                      <button class="btn btn-danger btn-sm remove-btn" @click="removeItem(index)">
                        <i class="fal fa-trash-alt"></i> Remove
                      </button>
                    </td>
                  </tr>
                  <tr v-if="form.items.length === 0">
                    <td colspan="11" class="text-center text-muted">No items added</td>
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
                    <th style="min-width: 200px;">Approval Type</th>
                    <th style="min-width: 200px;">Assigned User</th>
                    <th style="min-width: 100px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(approval, index) in form.approvals" :key="index">
                    <td>
                      <select v-model="approval.request_type" class="form-control approval-type-select" :data-row="index" required :disabled="approval.isDefault" @change="updateUsersForRow(index)">
                        <option value="">Select Type</option>
                        <option value="approve">Approve</option>
                      </select>
                    </td>
                    <td>
                      <select v-model="approval.user_id" class="form-control user-select" :data-row="index" required>
                        <option value="">Select User</option>
                        <option v-for="user in approval.availableUsers" :key="user.id" :value="user.id">{{ user.name }}</option>
                      </select>
                    </td>
                    <td>
                      <button class="btn btn-danger btn-sm" @click="removeApproval(index)" :disabled="approval.isDefault">
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

          <!-- Form Actions -->
          <div class="text-right">
            <button type="submit" class="btn btn-primary btn-sm mr-2" :disabled="isSubmitting || form.items.length === 0 || form.approvals.length === 0">
              <span v-if="isSubmitting" class="spinner-border spinner-border-sm mr-1"></span>
              {{ isEditMode ? (initialData.approval_status === 'Returned' ? 'Re-Submit' : 'Update') : 'Create' }}
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
  departments: { type: Array, default: () => [] },
  campuses: { type: Array, default: () => [] },
  defaultDepartment: { type: Object, default: () => null },
  defaultCampus: { type: Object, default: () => null }
})

const emit = defineEmits(['submitted'])
const isSubmitting = ref(false)
const products = ref([])
const warehouses = ref([])
const users = ref({ approve: [] })
const warehouseSelect = ref(null)
const typeSelect = ref(null)
const productSelect = ref(null)
const isEditMode = computed(() => !!props.initialData?.id)
const stockRequestId = ref(props.initialData?.id || null)
let isAddingItem = false

const form = ref({
  warehouse_id: null,
  request_date: '',
  type: '',
  purpose: '',
  items: [],
  approvals: [],
  departments: props.departments,
  campuses: props.campuses,
  defaultDepartment: props.defaultDepartment,
  defaultCampus: props.defaultCampus
})

const goToIndex = () => window.location.href = `/inventory/stock-requests`

const fetchUsersForApproval = async (requestType) => {
  try {
    const { data } = await axios.get('/api/inventory/stock-requests/users-for-approval', { params: { request_type: requestType } })
    users.value[requestType] = Array.isArray(data.data) ? data.data : []
  } catch (err) {
    await showAlert('Error', `Failed to load users for ${requestType} approval.`, 'danger')
  }
}

const fetchProducts = async () => {
  if (!form.value.warehouse_id || !form.value.request_date) {
    await showAlert('Warning', 'Please select a warehouse and request date before loading products.', 'warning')
    return
  }
  try {
    const { data } = await axios.get(`/api/inventory/stock-requests/get-products`, {
      params: { warehouse_id: form.value.warehouse_id, date: form.value.request_date }
    })
    products.value = Array.isArray(data) ? data : data.data
    await rebuildProductSelect()
  } catch (err) {
    await showAlert('Error', 'Failed to load products.', 'danger')
  }
}

const fetchWarehouses = async () => {
  try {
    const { data } = await axios.get(`/api/inventory/stock-requests/get-warehouses`)
    warehouses.value = Array.isArray(data) ? data : data.data
  } catch (err) {
    await showAlert('Error', 'Failed to load warehouses.', 'danger')
  }
}

const rebuildProductSelect = async () => {
  await nextTick()
  if (!productSelect.value) return
  destroySelect2(productSelect.value)
  productSelect.value.innerHTML = '<option value="">Select Product</option>'
  products.value.forEach(p => {
    const option = document.createElement('option')
    option.value = p.id
    option.textContent = `${p.item_code} - ${p.product_name} ${p.description || ''} (Stock: ${p.stock_on_hand || 0})`
    if (p.stock_on_hand === 0 || p.stock_on_hand === null) option.disabled = true
    productSelect.value.appendChild(option)
  })
  initSelect2(productSelect.value, { placeholder: 'Select Product', width: '100%', allowClear: true })
}

const getItemUnitName = (productId) => products.value.find(p => p.id === productId)?.unit_name || '-'
const getProductDescription = (productId) => {
  const product = products.value.find(p => p.id === productId)
  return product ? `${product.product_name} ${product.description || ''}` : '-'
}
const getProductPrice = (productId) => products.value.find(p => p.id === productId)?.average_price || 0
const getStockOnHand = (productId) => products.value.find(p => p.id === productId)?.stock_on_hand || 0
const getProductCode = (productId) => products.value.find(p => p.id === productId)?.item_code || '-'

const addItem = async (productId) => {
  if (isAddingItem || !productId) return
  isAddingItem = true
  try {
    const product = products.value.find(p => p.id === Number(productId))
    if (!product) {
      await showAlert('Error', 'Selected product not found.', 'danger')
      return
    }
    const existingItem = form.value.items.find(item => item.product_id === Number(productId))
    if (existingItem) {
      existingItem.quantity += 1
      await showAlert('Info', `Quantity increased for ${product.item_code}`, 'info')
    } else {
      form.value.items.push({
        product_id: Number(productId),
        department_id: props.defaultDepartment?.id || null,
        campus_id: props.defaultCampus?.id || null,
        quantity: 1,
        average_price: parseFloat(product.average_price) || 0,
        stock_on_hand: parseFloat(product.stock_on_hand) || 0,
        remarks: ''
      })
      await showAlert('Success', `Added ${product.item_code} to the request.`, 'success')
    }
    if (productSelect.value) $(productSelect.value).val(null).trigger('select2:unselect')
    await nextTick()
    await initInlineSelects()
  } catch (err) {
    await showAlert('Error', 'Failed to add product to table.', 'danger')
  } finally {
    isAddingItem = false
  }
}

const removeItem = async (index) => {
  try {
    form.value.items.splice(index, 1)
    await nextTick()
    await initInlineSelects()
    await showAlert('Success', 'Item removed from the request.', 'success')
  } catch (err) {
    await showAlert('Error', 'Failed to remove item.', 'danger')
  }
}

const addApproval = async () => {
  try {
    form.value.approvals.push({ id: null, request_type: '', user_id: null, isDefault: false, availableUsers: [] })
    await nextTick()
    const index = form.value.approvals.length - 1
    const approvalSelect = document.querySelector(`.approval-type-select[data-row="${index}"]`)
    const userSelect = document.querySelector(`.user-select[data-row="${index}"]`)
    if (approvalSelect && userSelect) {
      initSelect2(approvalSelect, { placeholder: 'Select Type', width: '100%', allowClear: true }, (value) => {
        form.value.approvals[index].request_type = value || ''
        updateUsersForRow(index)
      })
      initSelect2(userSelect, { placeholder: 'Select User', width: '100%', allowClear: true }, (value) => {
        form.value.approvals[index].user_id = value ? Number(value) : null
      })
      $(approvalSelect).val('').trigger('change.select2')
      $(userSelect).val('').trigger('change.select2')
    }
  } catch (err) {
    await showAlert('Error', 'Failed to add approval assignment.', 'danger')
  }
}

const removeApproval = async (index) => {
  if (form.value.approvals[index].isDefault) {
    await showAlert('Error', 'Default approval types cannot be removed.', 'danger')
    return
  }
  try {
    const approvalSelect = document.querySelector(`.approval-type-select[data-row="${index}"]`)
    const userSelect = document.querySelector(`.user-select[data-row="${index}"]`)
    if (approvalSelect) destroySelect2(approvalSelect)
    if (userSelect) destroySelect2(userSelect)
    form.value.approvals.splice(index, 1)
    await showAlert('Success', 'Approval assignment removed.', 'success')
  } catch (err) {
    await showAlert('Error', 'Failed to remove approval.', 'danger')
  }
}

const updateUsersForRow = async (index) => {
  try {
    const requestType = form.value.approvals[index].request_type
    const userSelect = document.querySelector(`.user-select[data-row="${index}"]`)
    if (requestType && ['approve'].includes(requestType)) {
      if (!users.value[requestType].length) await fetchUsersForApproval(requestType)
      form.value.approvals[index].availableUsers = users.value[requestType]
      if (userSelect) {
        destroySelect2(userSelect)
        initSelect2(userSelect, { placeholder: 'Select User', width: '100%', allowClear: true }, (value) => {
          form.value.approvals[index].user_id = value ? Number(value) : null
        })
        const currentUserId = form.value.approvals[index].user_id
        const validUser = users.value[requestType].find(user => user.id === currentUserId)
        $(userSelect).val(validUser ? currentUserId : '').trigger('change.select2')
        if (!validUser && currentUserId) {
          form.value.approvals[index].user_id = null
          await showAlert('Warning', `Previous user for ${requestType} is no longer valid. Please select a new user.`, 'warning')
        }
      }
    } else {
      form.value.approvals[index].availableUsers = []
      form.value.approvals[index].user_id = null
      if (userSelect) {
        destroySelect2(userSelect)
        initSelect2(userSelect, { placeholder: 'Select User', width: '100%', allowClear: true }, (value) => {
          form.value.approvals[index].user_id = value ? Number(value) : null
        })
        $(userSelect).val('').trigger('change.select2')
      }
    }
  } catch (err) {
    await showAlert('Error', 'Failed to update user dropdown.', 'danger')
  }
}

const validateApprovals = async () => {
  if (form.value.approvals.length < 1 || !form.value.approvals.some(a => a.request_type === 'approve')) {
    await showAlert('Error', 'At least one "Approve" assignment is required.', 'danger')
    return false
  }
  for (const approval of form.value.approvals) {
    if (!approval.request_type || !approval.user_id) {
      await showAlert('Error', 'All approvals must have a type and user selected.', 'danger')
      return false
    }
  }
  return true
}

const submitForm = async () => {
  if (isSubmitting.value || form.value.items.length === 0 || !(await validateApprovals())) {
    await showAlert('Error', 'At least one item and valid approvals are required.', 'danger')
    return
  }
  isSubmitting.value = true
  try {
    const payload = {
      warehouse_id: form.value.warehouse_id,
      request_date: form.value.request_date,
      type: form.value.type,
      purpose: form.value.purpose,
      items: form.value.items.map(item => ({
        id: item.id || null,
        product_id: item.product_id,
        department_id: item.department_id || null,
        campus_id: item.campus_id || null,
        quantity: parseFloat(item.quantity),
        average_price: parseFloat(item.average_price),
        remarks: item.remarks?.trim() || null
      })),
      approvals: form.value.approvals.map(approval => ({
        id: approval.id || null,
        user_id: approval.user_id,
        request_type: approval.request_type
      }))
    }
    const url = isEditMode.value ? `/api/inventory/stock-requests/${stockRequestId.value}` : `/api/inventory/stock-requests`
    await axios[isEditMode.value ? 'put' : 'post'](url, payload)
    await showAlert('Success', isEditMode.value ? 'Stock request updated successfully.' : 'Stock request created successfully.', 'success')
    emit('submitted')
    goToIndex()
  } catch (err) {
    await showAlert('Error', err.response?.data?.message || 'Failed to save stock request.', 'danger')
  } finally {
    isSubmitting.value = false
  }
}

const initDatepicker = async () => {
  await nextTick()
  $('#request_date').datepicker({
    format: 'yyyy-mm-dd',
    autoclose: true,
    todayHighlight: true,
    orientation: 'bottom left'
  }).on('changeDate', () => {
    form.value.request_date = $('#request_date').val()
  })
}

const initInlineSelects = async () => {
  await nextTick()
  document.querySelectorAll('.department-select').forEach((el, idx) => {
    if (!el.classList.contains('select2-hidden-accessible')) {
      initSelect2(el, { placeholder: 'Select Department', width: '100%' }, v => {
        form.value.items[idx].department_id = Number(v) || null
      })
      $(el).val(form.value.items[idx].department_id || props.defaultDepartment?.id || '').trigger('change.select2')
    }
  })
  document.querySelectorAll('.campus-select').forEach((el, idx) => {
    if (!el.classList.contains('select2-hidden-accessible')) {
      initSelect2(el, { placeholder: 'Select Campus', width: '100%' }, v => {
        form.value.items[idx].campus_id = Number(v) || null
      })
      $(el).val(form.value.items[idx].campus_id || props.defaultCampus?.id || '').trigger('change.select2')
    }
  })
}

watch([() => form.value.warehouse_id, () => form.value.request_date], async ([newId, newDate]) => {
  if (!newId || !newDate) return
  await fetchProducts()
  form.value.items = form.value.items.filter(item => {
    const product = products.value.find(p => p.id === item.product_id)
    if (!product) return false
    item.stock_on_hand = parseFloat(product.stock_on_hand) || 0
    item.average_price = parseFloat(product.average_price) || 0
    return true
  })
  await initInlineSelects()
})

onMounted(async () => {
  const defaultApprovalTypes = ['approve']
  const seenTypes = new Set()

  if (isEditMode.value) {
    form.value = {
      ...form.value,
      warehouse_id: props.initialData.warehouse_id,
      type: props.initialData.type,
      purpose: props.initialData.purpose,
      request_date: props.initialData.request_date,
      items: (props.initialData.items || []).map(item => ({
        id: item.id || null,
        product_id: Number(item.product_id),
        department_id: item.department_id || props.defaultDepartment?.id || null,
        campus_id: item.campus_id || props.defaultCampus?.id || null,
        quantity: parseFloat(item.quantity) || 1,
        average_price: parseFloat(item.average_price) || 0,
        stock_on_hand: parseFloat(item.stock_on_hand) || 0,
        remarks: item.remarks || ''
      })),
      approvals: (props.initialData.approvals || []).map(approval => {
        const isFirst = !seenTypes.has(approval.request_type)
        if (isFirst && defaultApprovalTypes.includes(approval.request_type)) seenTypes.add(approval.request_type)
        return {
          id: approval.id || null,
          user_id: Number(approval.user_id),
          request_type: approval.request_type || 'approve',
          isDefault: isFirst && defaultApprovalTypes.includes(approval.request_type),
          availableUsers: []
        }
      })
    }
  } else {
    form.value.approvals = defaultApprovalTypes.map(type => ({
      id: null,
      request_type: type,
      user_id: null,
      isDefault: true,
      availableUsers: []
    }))
  }

  await Promise.all([fetchWarehouses(), initDatepicker()])
  if (form.value.warehouse_id && form.value.request_date) await fetchProducts()

  if (warehouseSelect.value) {
    initSelect2(warehouseSelect.value, { placeholder: 'Select Warehouse', width: '100%', allowClear: true }, val => {
      form.value.warehouse_id = val
    })
    if (form.value.warehouse_id) $(warehouseSelect.value).val(form.value.warehouse_id).trigger('change.select2')
  }

  if (typeSelect.value) {
    initSelect2(typeSelect.value, { placeholder: 'Select Type', width: '100%', allowClear: true }, val => {
      form.value.type = val || ''
    })
    $(typeSelect.value).val(form.value.type || 'Using').trigger('change.select2')
  }

  if (productSelect.value) {
    initSelect2(productSelect.value, { placeholder: 'Select Product', width: '100%', allowClear: true })
    $(productSelect.value).off('select2:select').on('select2:select', (e) => {
      const productId = e.params.data.id
      addItem(productId)
    })
  }

  form.value.approvals.forEach(async (approval, index) => {
    if (approval.request_type) await updateUsersForRow(index)
    const approvalSelect = document.querySelector(`.approval-type-select[data-row="${index}"]`)
    const userSelect = document.querySelector(`.user-select[data-row="${index}"]`)
    if (approvalSelect && userSelect) {
      initSelect2(approvalSelect, {
        placeholder: 'Select Type',
        width: '100%',
        allowClear: true,
        data: defaultApprovalTypes.map(type => ({ id: type, text: type }))
      }, (value) => {
        form.value.approvals[index].request_type = value || ''
        updateUsersForRow(index)
      })
      initSelect2(userSelect, { placeholder: 'Select User', width: '100%', allowClear: true }, (value) => {
        form.value.approvals[index].user_id = value ? Number(value) : null
      })
      $(approvalSelect).val(approval.request_type || '').trigger('change.select2')
      $(userSelect).val(approval.user_id || '').trigger('change.select2')
    }
  })

  if (isEditMode.value && form.value.items.length) {
    await initInlineSelects()
    if (form.value.request_date) $('#request_date').datepicker('setDate', form.value.request_date)
  }
})

onUnmounted(() => {
  if (warehouseSelect.value) destroySelect2(warehouseSelect.value)
  if (typeSelect.value) destroySelect2(typeSelect.value)
  if (productSelect.value) destroySelect2(productSelect.value)
  document.querySelectorAll('.approval-type-select').forEach(el => destroySelect2(el))
  document.querySelectorAll('.user-select').forEach(el => destroySelect2(el))
  document.querySelectorAll('.department-select').forEach(el => destroySelect2(el))
  document.querySelectorAll('.campus-select').forEach(el => destroySelect2(el))
  $('#request_date').datepicker('destroy')
})
</script>

<!-- <style scoped>
.card-header { border-bottom: 1px solid #e3e6f0; }
.btn-icon { width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; }
</style> -->