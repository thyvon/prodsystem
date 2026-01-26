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
              <div class="form-group col-md-4">
                <label for="request_date" class="font-weight-bold">Request Date <span class="text-danger">*</span></label>
                <input
                  v-model="form.request_date_display"
                  type="text"
                  class="form-control"
                  id="request_date"
                  required
                  placeholder="Enter request date"
                />
              </div>
              <div class="form-group col-md-4">
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
              <div class="form-group col-md-4">
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
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h5 class="font-weight-bold text-primary">📦 Request Items <span class="text-danger">*</span></h5>
              <button type="button" class="btn btn-sm btn-success" @click="openItemsSelection">Add Items</button>
            </div>
            <div class="table-responsive">
              <table class="table table-bordered table-sm table-hover">
                <thead class="thead-light">
                <tr>
                  <th style="min-width: 80px;">Code</th>
                  <th style="min-width: 200px;">Description</th>
                  <th style="min-width: 60px;">UoM</th>
                  <th style="min-width: 100px;">Qty On Hand</th>
                  <th style="min-width: 100px;">Request Qty</th>
                  <th style="min-width: 100px;">Avg Price</th>
                  <th style="min-width: 120px;">Total Value</th>
                  <th style="min-width: 150px;">Department</th>
                  <th style="min-width: 150px;">Campus</th>
                  <th style="min-width: 180px;">Remarks</th>
                  <th style="min-width: 120px;">Actions</th>
                </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, index) in form.items" :key="item.product_id">
                    <td>{{ item.item_code }}</td>
                    <td>{{ item.description }}</td>
                    <td>{{ item.unit_name }}</td>
                    <td>
                      <input class="form-control" :value="item.stock_on_hand" readonly />
                    </td>
                    <td>
                      <input
                        type="number"
                        class="form-control quantity-input"
                        v-model.number="item.quantity"
                        min="0.0001"
                        step="0.0001"
                        :data-row="index"
                      />
                    </td>
                    <td>
                      <input class="form-control" :value="item.average_price" readonly />
                    </td>
                    <td>
                      <input class="form-control" :value="(item.quantity * item.average_price).toFixed(4)" readonly />
                    </td>
                    <td>
                      <select
                        class="form-control department-select"
                        :data-row="index"
                        v-model="item.department_id"
                      >
                        <option v-for="dep in form.departments" :value="dep.id" :key="dep.id">
                          {{ dep.short_name }}
                        </option>
                      </select>
                    </td>
                    <td>
                      <select
                        class="form-control campus-select"
                        :data-row="index"
                        v-model="item.campus_id"
                      >
                        <option v-for="c in form.campuses" :value="c.id" :key="c.id">
                          {{ c.short_name }}
                        </option>
                      </select>
                    </td>
                    <td>
                      <textarea
                        class="form-control remarks-input"
                        :data-row="index"
                        v-model="item.remarks"
                      ></textarea>
                    </td>
                    <td>
                      <button
                        type="button"
                        class="btn btn-danger btn-sm remove-btn"
                        :data-row="index"
                        @click="removeItem(index)"
                      >
                        <i class="fal fa-trash-alt"></i> Remove
                      </button>
                    </td>
                  </tr>
                  <tr v-if="form.items.length === 0">
                    <td colspan="11" class="text-center">No items added.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

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
                  <tr v-if="form.approvals.length === 0">
                    <td colspan="3" class="text-center">No approvals added.</td>
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
              <span v-if="isSubmitting" class="spinner-border spinner-border-sm mr-1"></span>
              {{
                isEditMode
                  ? (initialData.approval_status === 'Returned' ? 'Re-Submit' : 'Update')
                  : 'Create'
              }}
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

    <!-- Items Modal -->
    <div ref="itemsModal" class="modal fade" tabindex="-1" role="dialog">
      <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Select Products</h5>
            <button type="button" class="close" @click="closeItemsModal">&times;</button>
          </div>
          <div class="modal-body">
            <div class="table-responsive">
              <table ref="modalItemsTable" class="table table-bordered table-sm">
                <thead>
                  <tr>
                    <th>Select</th>
                    <th>Code</th>
                    <th>Description</th>
                    <th>UoM</th>
                    <th>Qty On Hand</th>
                    <th>Avg Price</th>
                  </tr>
                </thead>
                <tbody>
                  <!-- DataTable will populate this -->
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" @click="closeItemsModal">Cancel</button>
            <button class="btn btn-success btn-sm" @click="addSelectedItems">Add Selected</button>
          </div>
        </div>
      </div>
    </div>
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
  departments: { type: Array, default: () => [] },
  campuses: { type: Array, default: () => [] },
  defaultDepartment: { type: Object, default: () => null },
  defaultCampus: { type: Object, default: () => null }
})

const emit = defineEmits(['submitted'])
const isSubmitting = ref(false)
const warehouses = ref([])
const users = ref({ approve: [] })
const warehouseSelect = ref(null)
const typeSelect = ref(null)
const isEditMode = computed(() => !!props.initialData?.id)
const stockRequestId = ref(props.initialData?.id || null)
let isAddingItem = false
let isAddingApproval = false

const itemsModal = ref(null)
const modalItemsTable = ref(null)

const form = ref({
  warehouse_id: null,
  request_date: '',
  type: '',
  purpose: '',
  request_date_display: '',
  items: [],
  approvals: [],
  departments: props.departments,
  campuses: props.campuses,
  defaultDepartment: props.defaultDepartment,
  defaultCampus: props.defaultCampus
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

const openItemsSelection = async () => {
  if (!form.value.warehouse_id) {
    showAlert('Warning', 'Please select a warehouse first.', 'warning')
    return
  }

  $(itemsModal.value).modal('show')
  await nextTick()
  initModalDataTable()
}

const closeItemsModal = () => $(itemsModal.value).modal('hide')

const initModalDataTable = () => {
  const table = $(itemsModal.value).find('table');
  if ($.fn.DataTable.isDataTable(table)) {
    table.DataTable().destroy();
  }

  table.DataTable({
    serverSide: true,
    processing: true,
    responsive: true,
    autoWidth: false,
    ajax: {
      url: '/api/inventory/stock-requests/get-products',
      type: 'GET',
      data: function (d) {
        d.warehouse_id = form.value.warehouse_id
        d.cutoff_date = form.value.request_date
      }
    },
    columns: [
      {
        data: 'id',
        render: function (data) {
            return `<div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input select-product" id="select-item-${data}" value="${data}">
                    <label class="custom-control-label" for="select-item-${data}"></label>
                  </div>`;
        },
        orderable: false
      },
      { data: 'item_code' },
      {
        data: null,
        render: function (data, type, row) {
          return `${row.description}`;
        }
      },
      { data: 'unit_name' },
      { data: 'stock_on_hand' },
      { data: 'average_price' }
    ],
    paging: true,
    lengthChange: true,
    pageLength: 10,
    searching: true,
    info: true,
    ordering: true,
    order: [[1, 'asc']],
    language: {
      info: "Showing _START_ to _END_ of _TOTAL_ entries",
      infoEmpty: "No entries to show",
      infoFiltered: "(filtered from _MAX_ total entries)",
      lengthMenu: "Show _MENU_ entries"
    }
  });
}


const addSelectedItems = () => {
  const table = $(itemsModal.value).find('table').DataTable();

  table.rows().every(function () {
    const rowNode = this.node();
    if (rowNode) {
      const $checkbox = $(rowNode).find('.select-product');
      if ($checkbox.is(':checked')) {
        const data = this.data();
        addItem(data);
      }
    }
  });

  closeItemsModal();
}

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
    if (requestType && ['approve'].includes(requestType)) {
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
  if (form.value.approvals.length < 1) {
    showAlert('Error', 'At least one approval assignment (Approve) is required.', 'danger')
    return false
  }
  const defaultTypes = ['approve']
  const presentTypes = form.value.approvals
    .map(approval => approval.request_type)
    .filter(type => defaultTypes.includes(type))
  if (presentTypes.length < 1 || !defaultTypes.every(type => presentTypes.includes(type))) {
    showAlert('Error', 'All default approval types (Approve) must be present.', 'danger')
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

const addItem = (product) => {
  try {
    if (isAddingItem) return
    isAddingItem = true
    if (!product || !product.id) {
      console.warn('No product provided')
      showAlert('Warning', 'Please select a product.', 'warning')
      return
    }

    const existingItemIndex = form.value.items.findIndex(item => item.product_id === Number(product.id))
    if (existingItemIndex !== -1) {
      form.value.items[existingItemIndex].quantity += 1
      showAlert('Info', `Quantity increased for ${product.item_code}`, 'info')
    } else {
      const newItem = {
        product_id: Number(product.id),
        item_code: product.item_code,
        description: product.description,
        unit_name: product.unit_name,
        department_id: props.defaultDepartment?.id || null,
        campus_id: props.defaultCampus?.id || null,
        quantity: 1,
        average_price: parseFloat(product.average_price) || 0,
        stock_on_hand: parseFloat(product.stock_on_hand) || 0,
        remarks: '',
      }
      form.value.items.push(newItem)
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
  } catch (err) {
    console.error('Error removing item:', err)
    showAlert('Error', 'Failed to remove item.', 'danger')
  }
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
        remarks: item.remarks?.toString().trim() || null,
      })),
      approvals: form.value.approvals.map(approval => ({
        id: approval.id || null,
        user_id: approval.user_id,
        request_type: approval.request_type,
      })),
    }
    const url = isEditMode.value
      ? `/api/inventory/stock-requests/${stockRequestId.value}`
      : `/api/inventory/stock-requests`
    const method = isEditMode.value ? 'put' : 'post'
    await axios[method](url, payload)
    await showAlert('Success', isEditMode.value ? 'Stock request updated successfully.' : 'Stock request created successfully.', 'success')
    emit('submitted')
    goToIndex()
  } catch (err) {
    console.error('Submit error:', err.response?.data || err)
    await showAlert('Error', err.response?.data?.message || err.message || 'Failed to save stock request.', 'danger')
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

onMounted(async () => {
  try {
    const defaultApprovalTypes = ['approve']
    const seenTypes = new Set()

    // Initialize form
    if (props.initialData?.id) {
      form.value.warehouse_id = props.initialData.warehouse_id
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
      form.value.items = props.initialData.items?.map(item => ({
        id: item.id || null,
        product_id: Number(item.product_id),
        item_code: item.item_code || '',
        description: item.description || '',
        unit_name: item.unit_name || '',
        quantity: parseFloat(item.quantity) || 1,
        average_price: parseFloat(item.average_price) || 0,
        stock_on_hand: parseFloat(item.stock_on_hand) || 0,
        remarks: item.remarks || '',
        department_id: item.department_id || props.defaultDepartment?.id || null,
        campus_id: item.campus_id || props.defaultCampus?.id || null,
      })) || []
      form.value.approvals = props.initialData.approvals?.map(approval => {
        const isFirst = !seenTypes.has(approval.request_type)
        if (isFirst && defaultApprovalTypes.includes(approval.request_type)) seenTypes.add(approval.request_type)
        return {
          id: approval.id || null,
          user_id: Number(approval.user_id),
          request_type: approval.request_type || 'approve',
          isDefault: isFirst && defaultApprovalTypes.includes(approval.request_type),
          availableUsers: [],
        }
      }) || []
    } else {
      form.value.approvals = defaultApprovalTypes.map(type => ({
        id: null,
        request_type: type,
        user_id: null,
        isDefault: true,
        availableUsers: [],
      }))
    }

    // Fetch supporting data
    await fetchWarehouses()

    // Initialize approval dropdowns
    await nextTick()
    for (let i = 0; i < form.value.approvals.length; i++) {
      const approvalSelect = document.querySelector(`.approval-type-select[data-row="${i}"]`)
      const userSelect = document.querySelector(`.user-select[data-row="${i}"]`)
      if (!approvalSelect || !userSelect) {
        console.warn(`DOM elements for approval row ${i} not found`)
        continue
      }
      initSelect2(approvalSelect, {
        placeholder: 'Select Type',
        width: '100%',
        allowClear: true,
        data: defaultApprovalTypes.map(type => ({ id: type, text: type })),
      }, (value) => {
        form.value.approvals[i].request_type = value || ''
        updateUsersForRow(i)
      })
      $(approvalSelect).val(form.value.approvals[i].request_type || '').trigger('change.select2')
      await updateUsersForRow(i)
    }

    // Initialize regular Select2 for warehouse & type
    await nextTick()
    if (warehouseSelect.value) {
      initSelect2(warehouseSelect.value, { placeholder: 'Select Warehouse', width: '100%', allowClear: true }, v => form.value.warehouse_id = v)
      if (form.value.warehouse_id) $(warehouseSelect.value).val(form.value.warehouse_id).trigger('change')
    }
    if (typeSelect.value) {
      initSelect2(
        typeSelect.value,
        { placeholder: 'Select Type', width: '100%', allowClear: true },
        value => { form.value.type = value || '' }
      )
      if (form.value.type) {
        $(typeSelect.value).val(form.value.type).trigger('change')
      } else {
        form.value.type = 'Using'
        $(typeSelect.value).val('Using').trigger('change')
      }
    }

    // Initialize Select2 for department & campus in table
    await nextTick()
    const initInlineSelects = () => {
      document.querySelectorAll('.department-select').forEach((el, idx) => {
        if (!$(el).hasClass('select2-hidden-accessible')) {
          initSelect2(el, { placeholder: 'Select Department', width: '100%' }, v => {
            form.value.items[idx].department_id = Number(v) || null
          })
          $(el).val(form.value.items[idx].department_id || props.defaultDepartment?.id || '').trigger('change.select2')
        }
      })
      document.querySelectorAll('.campus-select').forEach((el, idx) => {
        if (!$(el).hasClass('select2-hidden-accessible')) {
          initSelect2(el, { placeholder: 'Select Campus', width: '100%' }, v => {
            form.value.items[idx].campus_id = Number(v) || null
          })
          $(el).val(form.value.items[idx].campus_id || props.defaultCampus?.id || '').trigger('change.select2')
        }
      })
    }
    initInlineSelects()

    // Re-initialize Select2 on table updates
    watch(() => form.value.items, () => {
      nextTick(() => initInlineSelects())
    }, { deep: true })

    // Initialize Datepicker
    await initDatepicker()
  } catch (err) {
    console.error('Error in onMounted:', err)
    showAlert('Error', 'Failed to initialize form.', 'danger')
  }
})

onUnmounted(() => {
  try {
    if (warehouseSelect.value) destroySelect2(warehouseSelect.value)
    if (typeSelect.value) destroySelect2(typeSelect.value)
    document.querySelectorAll('.approval-type-select').forEach(el => destroySelect2(el))
    document.querySelectorAll('.user-select').forEach(el => destroySelect2(el))
    document.querySelectorAll('.department-select').forEach(el => destroySelect2(el))
    document.querySelectorAll('.campus-select').forEach(el => destroySelect2(el))
    $('#request_date').datepicker('destroy')
  } catch (err) {
    console.error('Error in onUnmounted:', err)
  }
})
</script>
