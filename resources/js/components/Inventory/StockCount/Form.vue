<template>
  <div class="container-fluid">
    <form @submit.prevent="submitForm">
      <div class="card border mb-0 shadow">
        <div class="card-header d-flex justify-content-between align-items-center bg-light py-2">
          <h4 class="mb-0 font-weight-bold">
            {{ isEditMode ? 'Edit Stock Count' : 'Create Stock Count' }}
          </h4>
          <button type="button" class="btn btn-outline-primary btn-sm" @click="goToIndex">
            Back
          </button>
        </div>

        <div class="card-body">
          <!-- Header -->
          <div class="border rounded p-3 mb-4 bg-white">
            <div class="form-row">
              <div class="form-group col-md-4">
                <label class="font-weight-bold">Count Date <span class="text-danger">*</span></label>
                <input
                  id="transaction_date"
                  v-model="form.transaction_date"
                  type="text"
                  class="form-control"
                  placeholder="yyyy-mm-dd"
                  required
                />
              </div>

              <div class="form-group col-md-4">
                <label class="font-weight-bold">Warehouse <span class="text-danger">*</span></label>
                <select
                  ref="warehouseSelect"
                  v-model="form.warehouse_id"
                  class="form-control"
                  required
                >
                  <option value="">Select Warehouse</option>
                  <option v-for="w in warehouses" :key="w.id" :value="w.id">
                    {{ w.name }}
                  </option>
                </select>
              </div>

              <div class="form-group col-md-4">
                <label class="font-weight-bold">Reference No</label>
                <input v-model="form.reference_no" type="text" class="form-control" readonly />
              </div>
            </div>

            <div class="form-group">
              <label>Remarks</label>
              <textarea v-model="form.remarks" class="form-control" rows="2"></textarea>
            </div>
          </div>

          <!-- Items Section -->
          <div class="border rounded p-3 mb-4 bg-white">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="mb-0 text-primary">Counted Items</h5>
              <div>
                <button type="button" class="btn btn-sm btn-outline-secondary mr-2" @click="triggerFileInput">
                  Import Excel
                </button>
                <input type="file" ref="fileInput" class="d-none" accept=".xlsx,.xls,.csv" @change="handleFileUpload" />
                <button type="button" class="btn btn-sm btn-success" @click="openProductsModal">
                  Add Items
                </button>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-bordered table-sm table-hover">
                <thead class="thead-light">
                  <tr>
                    <th>Code</th>
                    <th>Description</th>
                    <th>UoM</th>
                    <th>System Qty</th>
                    <th>Counted Qty *</th>
                    <th>Variance</th>
                    <th>Remarks</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, i) in form.items" :key="i">
                    <td>{{ item.item_code }}</td>
                    <td>{{ item.product_name }} {{ item.description }}</td>
                    <td>{{ item.unit_name }}</td>
                    <td><input type="number" :value="item.ending_quantity.toFixed(4)" class="form-control" readonly /></td>
                    <td>
                      <input
                        type="number"
                        v-model.number="item.counted_quantity"
                        class="form-control"
                        min="0"
                        step="0.0001"
                        required
                      />
                    </td>
                    <td>
                      <input
                        type="number"
                        :value="(item.counted_quantity - item.ending_quantity).toFixed(4)"
                        :class="{
                          'text-danger font-weight-bold': item.counted_quantity < item.ending_quantity,
                          'text-success font-weight-bold': item.counted_quantity > item.ending_quantity
                        }"
                        class="form-control"
                        readonly
                      />
                    </td>
                    <td><textarea v-model="item.remarks" class="form-control" rows="1"></textarea></td>
                    <td>
                      <button type="button" class="btn btn-sm btn-danger" @click="removeItem(i)">
                        Remove
                      </button>
                    </td>
                  </tr>
                  <tr v-if="!form.items.length">
                    <td colspan="8" class="text-center text-muted">No items added yet</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Approval Assignments -->
          <div class="border rounded p-3 mb-4 bg-white">
            <h5 class="mb-3 text-primary">Approval Assignments</h5>
            <table class="table table-bordered table-sm">
              <thead class="thead-light">
                <tr>
                  <th width="30%">Type</th>
                  <th width="50%">Assigned User</th>
                  <th width="20%"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(app, i) in form.approvals" :key="i">
                  <td>
                    <select
                      v-model="app.request_type"
                      class="form-control approval-type-select"
                      :data-index="i"
                      :disabled="app.isDefault"
                      required
                    >
                      <option value="initial">Initial</option>
                      <option value="approve">Approve</option>
                    </select>
                  </td>
                  <td>
                    <select
                      v-model="app.user_id"
                      class="form-control user-select"
                      :data-index="i"
                      required
                    >
                      <option value="">Select User</option>
                      <option v-for="u in app.availableUsers" :key="u.id" :value="u.id">
                        {{ u.name }}
                      </option>
                    </select>
                  </td>
                  <td>
                    <button
                      type="button"
                      class="btn btn-sm btn-danger"
                      @click="removeApproval(i)"
                      :disabled="app.isDefault"
                    >
                      Remove
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
            <button type="button" class="btn btn-sm btn-outline-primary mt-2" @click="addApproval">
              Add Approval
            </button>
          </div>

          <!-- Submit -->
          <div class="text-right">
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="isSubmitting || !form.items.length || !validateApprovals()"
            >
              <span v-if="isSubmitting" class="spinner-border spinner-border-sm mr-2"></span>
              {{ isEditMode ? 'Update' : 'Create Stock Count' }}
            </button>
            <button type="button" class="btn btn-secondary ml-2" @click="goToIndex">Cancel</button>
          </div>
        </div>
      </div>
    </form>

    <!-- Products Modal -->
    <div ref="itemsModal" class="modal fade" tabindex="-1">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Select Products to Count</h5>
            <button type="button" class="close" @click="closeItemsModal">×</button>
          </div>
          <div class="modal-body">
            <table ref="modalTable" class="table table-bordered table-sm">
              <thead class="thead-light">
                <tr>
                  <th><input type="checkbox" @change="toggleAll($event)" /></th>
                  <th>Code</th>
                  <th>Description</th>
                  <th>UoM</th>
                  <th>System Qty</th>
                </tr>
              </thead>
            </table>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="closeItemsModal">Cancel</button>
            <button class="btn btn-success" @click="addSelectedItems">Add Selected</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick, watch } from 'vue'
import axios from 'axios'
import { showAlert } from '@/Utils/bootbox'
import { initSelect2, destroySelect2 } from '@/Utils/select2'

const props = defineProps({ initialId: [String, Number] })
const emit = defineEmits(['submitted'])

const isEditMode = ref(false)
const isSubmitting = ref(false)

const form = ref({
  transaction_date: '',
  warehouse_id: null,
  reference_no: '',
  remarks: '',
  items: [],
  approvals: []
})

const warehouses = ref([])
const fileInput = ref(null)
const itemsModal = ref(null)
const warehouseSelect = ref(null)

const goToIndex = () => window.location.href = '/inventory/stock-counts'

const fetchWarehouses = async () => {
  const { data } = await axios.get('/api/inventory/stock-counts/get-warehouses')
  warehouses.value = data.data || data
}

const initDatepicker = () => {
  $('#transaction_date').datepicker({
    format: 'yyyy-mm-dd',
    autoclose: true,
    todayHighlight: true,
    orientation: 'bottom left'
  }).on('changeDate', (e) => {
    form.value.transaction_date = e.format()
  })
}

const initWarehouseSelect2 = () => {
  if (!warehouseSelect.value) return
  initSelect2(warehouseSelect.value, {
    placeholder: 'Select Warehouse',
    width: '100%',
    allowClear: false
  }, (val) => {
    form.value.warehouse_id = val
    form.value.items = [] // clear items on warehouse change
  })

  if (form.value.warehouse_id) {
    $(warehouseSelect.value).val(form.value.warehouse_id).trigger('change')
  }
}

const initApprovalSelect2 = async () => {
  const { data } = await axios.get('/api/inventory/stock-counts/get-users-for-approval')
  const users = {
    initial: data.initial || [],
    approve: data.approve || []
  }

  await nextTick()

  $('.approval-type-select').each(function () {
    const index = $(this).data('index')
    initSelect2(this, {
      placeholder: 'Select Type',
      width: '100%',
      allowClear: false
    }, (val) => {
      form.value.approvals[index].request_type = val
      updateUserSelect(index, users)
    })
    $(this).val(form.value.approvals[index].request_type).trigger('change')
  })

  $('.user-select').each(function () {
    const index = $(this).data('index')
    const type = form.value.approvals[index].request_type
    const userList = users[type] || []

    destroySelect2(this)
    initSelect2(this, {
      placeholder: 'Select User',
      width: '100%',
      data: userList.map(u => ({ id: u.id, text: u.name }))
    }, (val) => {
      form.value.approvals[index].user_id = val ? Number(val) : null
    })

    if (form.value.approvals[index].user_id) {
      $(this).val(form.value.approvals[index].user_id).trigger('change')
    }
  })
}

const updateUserSelect = (index, users) => {
  nextTick(() => {
    const select = document.querySelector(`.user-select[data-index="${index}"]`)
    if (!select) return

    const type = form.value.approvals[index].request_type
    const userList = users[type] || []

    destroySelect2(select)
    initSelect2(select, {
      placeholder: 'Select User',
      width: '100%',
      data: userList.map(u => ({ id: u.id, text: u.name }))
    }, (val) => {
      form.value.approvals[index].user_id = val ? Number(val) : null
    })

    $(select).val(form.value.approvals[index].user_id || '').trigger('change')
  })
}

const addApproval = async () => {
  form.value.approvals.push({
    request_type: '',
    user_id: null,
    isDefault: false,
    availableUsers: []
  })
  await nextTick()
  const index = form.value.approvals.length - 1
  const typeEl = document.querySelector(`.approval-type-select[data-index="${index}"]`)
  const userEl = document.querySelector(`.user-select[data-index="${index}"]`)

  initSelect2(typeEl, { placeholder: 'Select Type', width: '100%' }, (val) => {
    form.value.approvals[index].request_type = val
    updateUserSelect(index, { initial: [], approve: [] })
  })
  initSelect2(userEl, { placeholder: 'Select User', width: '100%' })
}

const removeApproval = (i) => {
  if (form.value.approvals[i].isDefault) return
  const typeEl = document.querySelector(`.approval-type-select[data-index="${i}"]`)
  const userEl = document.querySelector(`.user-select[data-index="${i}"]`)
  if (typeEl) destroySelect2(typeEl)
  if (userEl) destroySelect2(userEl)
  form.value.approvals.splice(i, 1)
}

const validateApprovals = () => {
  const types = form.value.approvals.map(a => a.request_type)
  return types.includes('initial') && types.includes('approve') && new Set(types).size === 2
}

const openProductsModal = async () => {
  if (!form.value.warehouse_id || !form.value.transaction_date) {
    showAlert('Warning', 'Please select Warehouse and Count Date first.', 'warning')
    return
  }
  await nextTick()
  const table = $(itemsModal.value).find('table')
  if ($.fn.DataTable.isDataTable(table)) table.DataTable().destroy()

  table.DataTable({
    serverSide: true,
    processing: true,
    ajax: {
      url: '/api/inventory/stock-counts/get-products',
      data: d => {
        d.warehouse_id = form.value.warehouse_id
        d.cutoff_date = form.value.transaction_date
      }
    },
    columns: [
      { data: 'id', orderable: false, render: id => `<input type="checkbox" class="select-item" value="${id}">` },
      { data: 'item_code' },
      { data: null, render: (d, t, r) => `${r.product_name} ${r.description || ''}` },
      { data: 'unit_name' },
      { data: 'stock_on_hand', className: 'text-right' }
    ]
  })

  $(itemsModal.value).modal('show')
}

const addSelectedItems = () => {
  const table = $(itemsModal.value).find('table').DataTable()
  const selected = []
  table.rows().every(function () {
    if ($(this.node()).find('.select-item').is(':checked')) selected.push(this.data())
  })

  selected.forEach(p => {
    if (!form.value.items.find(i => i.product_id === p.id)) {
      form.value.items.push({
        product_id: p.id,
        item_code: p.item_code,
        product_name: p.product_name,
        description: p.description || '',
        unit_name: p.unit_name,
        ending_quantity: parseFloat(p.stock_on_hand) || 0,
        counted_quantity: parseFloat(p.stock_on_hand) || 0,
        remarks: ''
      })
    }
  })

  $(itemsModal.value).find('.select-item').prop('checked', false)
  $(itemsModal.value).modal('hide')
}

const removeItem = i => form.value.items.splice(i, 1)
const closeItemsModal = () => $(itemsModal.value).modal('hide')
const toggleAll = e => $(itemsModal.value).find('.select-item').prop('checked', e.target.checked)
const triggerFileInput = () => fileInput.value.click()
const handleFileUpload = e => { if (e.target.files[0]) importFile() }

const importFile = async () => {
  const file = fileInput.value?.files[0]
  if (!file || !form.value.warehouse_id || !form.value.transaction_date) {
    showAlert('Error', 'Please select file, warehouse, and date.', 'danger')
    return
  }

  const formData = new FormData()
  formData.append('file', file)

  try {
    const { data } = await axios.post('/api/inventory/stock-counts/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    const productsResp = await axios.get('/api/inventory/stock-counts/get-products', {
      params: { warehouse_id: form.value.warehouse_id, cutoff_date: form.value.transaction_date }
    })
    const products = productsResp.data.data || productsResp.data

    form.value.items = data.data.items.map(item => {
      const p = products.find(x => x.id == item.product_id)
      return p ? {
        product_id: p.id,
        item_code: p.item_code,
        product_name: p.product_name,
        description: p.description || '',
        unit_name: p.unit_name,
        ending_quantity: parseFloat(p.stock_on_hand),
        counted_quantity: parseFloat(item.counted_quantity || p.stock_on_hand),
        remarks: item.remarks || ''
      } : null
    }).filter(Boolean)

    showAlert('Success', 'Items imported!', 'success')
    fileInput.value.value = ''
  } catch (err) {
    showAlert('Error', err.response?.data?.message || 'Import failed', 'danger')
  }
}

const submitForm = async () => {
  if (!form.value.transaction_date || !form.value.warehouse_id || !form.value.items.length) {
    showAlert('Error', 'Please complete all required fields.', 'danger')
    return
  }
  if (!validateApprovals()) {
    showAlert('Error', 'Must assign exactly one Initial and one Approve user.', 'danger')
    return
  }

  isSubmitting.value = true
  try {
    const payload = {
      transaction_date: form.value.transaction_date,
      warehouse_id: form.value.warehouse_id,
      remarks: form.value.remarks || null,
      items: form.value.items.map(i => ({
        product_id: i.product_id,
        ending_quantity: i.ending_quantity,
        counted_quantity: i.counted_quantity,
        remarks: i.remarks || null
      })),
      approvals: form.value.approvals.map(a => ({
        user_id: a.user_id,
        request_type: a.request_type
      }))
    }

    const url = isEditMode.value
      ? `/api/inventory/stock-counts/${props.initialId}`
      : '/api/inventory/stock-counts'

    await axios[isEditMode.value ? 'put' : 'post'](url, payload)
    showAlert('Success', 'Stock count saved successfully!', 'success')
    emit('submitted')
    goToIndex()
  } catch (err) {
    showAlert('Error', err.response?.data?.message || 'Failed to save', 'danger')
  } finally {
    isSubmitting.value = false
  }
}

onMounted(async () => {
  await fetchWarehouses()

  // Default approvals
  form.value.approvals = [
    { request_type: 'initial', user_id: null, isDefault: true },
    { request_type: 'approve', user_id: null, isDefault: true }
  ]

  initDatepicker()
  initWarehouseSelect2()
  await initApprovalSelect2()

  if (props.initialId) {
    isEditMode.value = true
    // Add loadEditData() if needed later
  }
})

onUnmounted(() => {
  $('#transaction_date').datepicker('destroy')
  if (warehouseSelect.value) destroySelect2(warehouseSelect.value)
  $('.approval-type-select, .user-select').each(function () { destroySelect2(this) })
})
</script>