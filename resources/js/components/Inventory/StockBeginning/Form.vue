<template>
  <div class="container-fluid">
    <form @submit.prevent="submitForm">
      <div class="card border mb-0 shadow">
        <div class="card-header d-flex justify-content-between align-items-center bg-light py-2">
          <h4 class="mb-0 font-weight-bold">
            {{ isEditMode ? 'Edit Stock Beginning' : 'Create Stock Beginning' }}
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
                <label class="font-weight-bold">Beginning Date <span class="text-danger">*</span></label>
                <input
                  id="beginning_date"
                  v-model="form.beginning_date"
                  type="text"
                  class="form-control"
                  placeholder="yyyy-mm-dd"
                  required readonly
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
                    {{ w.text }}
                  </option>
                </select>
              </div>
            </div>
          </div>

          <!-- Items Section -->
          <div class="border rounded p-3 mb-4 bg-white">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="mb-0 text-primary">Stock Beginning Items</h5>
              <div class="d-flex align-items-center gap-2">
                
                <!-- Download Sample Excel -->
                <button
                  type="button"
                  class="btn btn-sm btn-outline-secondary d-flex align-items-center"
                  @click="downloadSampleExcel"
                >
                  <i class="fal fa-file-excel mr-1"></i>
                  Download Sample Excel
                </button>

                <!-- Import Excel -->
                <button 
                  type="button"
                  class="btn btn-sm btn-outline-secondary d-flex align-items-center"
                  @click="triggerFileInput" 
                  :disabled="isImporting"
                >
                  <span v-if="isImporting" class="spinner-border spinner-border-sm mr-1"></span>
                  <i v-else class="fal fa-file-excel mr-1"></i>
                  Import Excel
                </button>
                <input 
                  type="file" 
                  ref="fileInput" 
                  class="d-none" 
                  accept=".xlsx,.xls,.csv" 
                  @change="handleFileUpload" 
                />

                <!-- Add Items -->
                <button 
                  type="button"
                  class="btn btn-sm btn-success d-flex align-items-center" 
                  @click="openProductsModal"
                >
                  <i class="fal fa-plus mr-1"></i>
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
                    <th>Quantity *</th>
                    <th>Unit Price *</th>
                    <th>Total</th>
                    <th>Remarks</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, i) in form.items" :key="i">
                    <td>{{ item.item_code }}</td>
                    <td>{{ item.product_name }} {{ item.description }}</td>
                    <td>{{ item.unit_name }}</td>
                    <td>
                      <input
                        type="number"
                        v-model.number="item.quantity"
                        class="form-control"
                        min="0"
                        step="0.0001"
                        required
                      />
                    </td>
                    <td>
                      <input
                        type="number"
                        v-model.number="item.unit_price"
                        class="form-control"
                        min="0"
                        step="0.0001"
                        required
                      />
                    </td>
                    <td>{{ (item.quantity * item.unit_price).toFixed(2) }}</td>
                    <td>
                      <textarea v-model="item.remarks" class="form-control" rows="1"></textarea>
                    </td>
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
                      <option value="review">Review</option>
                      <option value="check">Check</option>
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
          </div>

          <!-- Submit -->
          <div class="text-right">
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="isSubmitting || !form.items.length || !validateApprovals()"
            >
              <span v-if="isSubmitting" class="spinner-border spinner-border-sm mr-2"></span>
              {{ isEditMode ? 'Update' : 'Create Stock Beginning' }}
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
            <h5 class="modal-title">Select Products</h5>
            <button type="button" class="close" @click="closeItemsModal">×</button>
          </div>
          <div class="modal-body">
            <div class="table-responsive">
              <table ref="modalTable" class="table table-bordered table-sm table-hover table-striped">
                <thead class="thead-light">
                  <tr>
                    <th>
                      <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="select-all" @change="toggleAll($event)" />
                        <label class="custom-control-label" for="select-all"></label>
                      </div>
                    </th>
                    <th>Code</th>
                    <th>Description</th>
                    <th>UoM</th>
                  </tr>
                </thead>
              </table>
            </div>
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

const props = defineProps({ stockBeginningId: [String, Number] })
const emit = defineEmits(['submitted'])

const isEditMode = ref(false)
const isSubmitting = ref(false)
const isImporting = ref(false)

const form = ref({
  beginning_date: '',
  warehouse_id: null,
  items: [],
  approvals: [],
})

const warehouses = ref([])
const fileInput = ref(null)
const itemsModal = ref(null)
const warehouseSelect = ref(null)

const goToIndex = () => window.location.href = '/inventory/stock-beginnings'

/* -------------------- Fetch Warehouses -------------------- */
const fetchWarehouses = async () => {
  const { data } = await axios.get('/api/main-value-lists/get-warehouses')
  warehouses.value = data.data || data
}

/* -------------------- Datepicker -------------------- */
const initDatepicker = () => {
  $('#beginning_date').datepicker({
    format: 'yyyy-mm-dd',
    autoclose: true,
    todayHighlight: true,
    orientation: 'bottom left'
  }).on('changeDate', e => {
    form.value.beginning_date = e.format()
  })
}

/* -------------------- Warehouse Select2 -------------------- */
const initWarehouseSelect2 = async () => {
  if (!warehouseSelect.value) return
  const warehouseData = (warehouses.value || []).map(w => ({ id: w.id, text: w.name || w.text }))

  initSelect2(warehouseSelect.value, {
    placeholder: 'Select Warehouse',
    width: '100%',
    allowClear: false,
    data: warehouseData
  }, val => {
    form.value.warehouse_id = val ? Number(val) : null
  })

  if (form.value.warehouse_id) {
    $(warehouseSelect.value).val(form.value.warehouse_id).trigger('change')
  }
}

/* -------------------- Approval Select2 -------------------- */
const initApprovalSelect2 = async () => {
  const { data } = await axios.get('/api/inventory/stock-beginnings/users')
  const users = {
    review: data.review || [],
    check: data.check || [],
    approve: data.approve || []
  }

  await nextTick()

  $('.approval-type-select').each(function () {
    const index = $(this).data('index')
    initSelect2(this, {
      placeholder: 'Select Type',
      width: '100%',
      allowClear: false,
      data: [
        { id: 'review', text: 'Review' },
        { id: 'check', text: 'Check' },
        { id: 'approve', text: 'Approve' }
      ]
    }, val => {
      form.value.approvals[index].request_type = val
      updateUserSelect(index, users)
    })
    $(this).val(form.value.approvals[index].request_type).trigger('change')
  })

  $('.user-select').each(function () {
    const index = $(this).data('index')
    updateUserSelect(index, users)
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
    }, val => form.value.approvals[index].user_id = val ? Number(val) : null)

    if (form.value.approvals[index].user_id) {
      $(select).val(form.value.approvals[index].user_id).trigger('change')
    }
  })
}

/* -------------------- Approvals -------------------- */
const addApproval = async () => {
  form.value.approvals.push({ request_type: '', user_id: null, isDefault: false })
  await nextTick()
  const index = form.value.approvals.length - 1
  const typeEl = document.querySelector(`.approval-type-select[data-index="${index}"]`)
  const userEl = document.querySelector(`.user-select[data-index="${index}"]`)
  initSelect2(typeEl, { placeholder: 'Select Type', width: '100%' })
  initSelect2(userEl, { placeholder: 'Select User', width: '100%' })
}

const removeApproval = i => {
  if (form.value.approvals[i].isDefault) return
  destroySelect2(document.querySelector(`.approval-type-select[data-index="${i}"]`))
  destroySelect2(document.querySelector(`.user-select[data-index="${i}"]`))
  form.value.approvals.splice(i, 1)
}

const validateApprovals = () => {
  const types = form.value.approvals.map(a => a.request_type)
  return ['review', 'check', 'approve'].every(t => types.includes(t))
}

/* -------------------- Products Modal -------------------- */
const openProductsModal = async () => {
  if (!form.value.warehouse_id || !form.value.beginning_date) {
    showAlert('Warning', 'Please select Warehouse and Beginning Date first.', 'warning')
    return
  }

  await nextTick()
  const tableEl = $(itemsModal.value).find('table')
  if ($.fn.DataTable.isDataTable(tableEl)) tableEl.DataTable().destroy()

  tableEl.DataTable({
    serverSide: true,
    processing: true,
    responsive: true,
    autoWidth: false,
    ajax: {
      url: '/api/inventory/stock-beginnings/get-products',
      type: 'GET',
      data: d => {
        d.warehouse_id = form.value.warehouse_id
        d.cutoff_date = form.value.beginning_date
      }
    },
    columns: [
      { data: 'id', orderable: false, render: id => `
        <div class="custom-control custom-checkbox">
          <input type="checkbox" class="custom-control-input select-item" id="chk-${id}" value="${id}">
          <label class="custom-control-label" for="chk-${id}"></label>
        </div>
      ` },
      { data: 'item_code' },
      { data: 'description' },
      { data: 'unit_name' }
    ]
  })

  $(itemsModal.value).modal('show')
}

const addSelectedItems = () => {
  const table = $(itemsModal.value).find('table').DataTable()
  table.rows().every(function () {
    if ($(this.node()).find('.select-item').is(':checked')) {
      const p = this.data()
      if (!form.value.items.find(i => i.product_id === p.id)) {
        form.value.items.push({
          product_id: p.id,
          item_code: p.item_code,
          product_name: p.product_name || '',
          description: p.description || '',
          unit_name: p.unit_name || '',
          quantity: 0,
          unit_price: 0,
          remarks: ''
        })
      }
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


const downloadSampleExcel = () => {
  const link = document.createElement('a')
  link.href = '/sampleExcel/stock_beginnings_sample.xlsx'
  link.download = 'stock_beginnings_sample.xlsx'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}
/* -------------------- File Import -------------------- */
const importFile = async () => {
  const file = fileInput.value.files[0]
  if (!file || !form.value.warehouse_id || !form.value.beginning_date) {
    showAlert('Warning', 'Please select Warehouse and Beginning Date first.', 'warning')
    return
  }

  isImporting.value = true
  const fd = new FormData()
  fd.append('file', file)

  try {
    const { data } = await axios.post('/api/inventory/stock-beginnings/import', fd, { headers: { 'Content-Type': 'multipart/form-data' } })

    if (data.errors?.length) { showAlert('Error', data.errors.join('<br>'), 'danger', { html: true }); return }

    (data.data?.items || []).forEach(r => {
      if (!form.value.items.find(i => i.product_id === r.product_id)) form.value.items.push({
        product_id: r.product_id,
        item_code: r.item_code,
        product_name: r.product_name || '',
        description: r.description || '',
        unit_name: r.unit_name || '',
        quantity: parseFloat(r.quantity) || 0,
        unit_price: parseFloat(r.unit_price) || 0,
        remarks: r.remarks || ''
      })
    })
    fileInput.value.value = ''
    showAlert('Success', `Imported ${(data.data?.items || []).length} items`, 'success')
  } catch (err) {
    showAlert('Error', err.response?.data?.message || 'Failed to import', 'danger')
  } finally { isImporting.value = false }
}

/* -------------------- Submit -------------------- */
const submitForm = async () => {
  if (!form.value.beginning_date || !form.value.warehouse_id || !form.value.items.length) {
    showAlert('Error', 'Please fill all required fields and add items.', 'danger')
    return
  }
  if (!validateApprovals()) {
    showAlert('Error', 'Please assign Review, Check, and Approve users.', 'danger')
    return
  }

  isSubmitting.value = true
  try {
    const payload = {
      warehouse_id: form.value.warehouse_id,
      beginning_date: form.value.beginning_date,
      items: form.value.items.map(i => ({
        product_id: i.product_id,
        quantity: parseFloat(i.quantity),
        unit_price: parseFloat(i.unit_price),
        remarks: i.remarks || null
      })),
      approvals: form.value.approvals.map(a => ({ user_id: a.user_id, request_type: a.request_type }))
    }

    const url = isEditMode.value
      ? `/api/inventory/stock-beginnings/${props.stockBeginningId}`
      : '/api/inventory/stock-beginnings'

    await axios[isEditMode.value ? 'put' : 'post'](url, payload)
    await showAlert('Success', 'Stock Beginning saved successfully!', 'success')
    emit('submitted')
    goToIndex()
  } catch (err) {
    showAlert('Error', err.response?.data?.message || 'Failed to save', 'danger')
  } finally { isSubmitting.value = false }
}

/* -------------------- Load Edit Data -------------------- */
const loadEditData = async id => {
  try {
    const { data } = await axios.get(`/api/inventory/stock-beginnings/${id}/edit`)
    const d = data.data

    form.value.beginning_date = d.beginning_date
    form.value.warehouse_id = d.warehouse_id
    form.value.items = d.items.map(i => ({
      product_id: i.product_id,
      item_code: i.item_code,
      product_name: i.product_name || '',
      description: i.description || '',
      unit_name: i.unit_name || '',
      quantity: parseFloat(i.quantity),
      unit_price: parseFloat(i.unit_price),
      remarks: i.remarks || ''
    }))
    form.value.approvals = d.approvals.map(a => ({ request_type: a.request_type, user_id: a.user_id, isDefault: true }))

    await nextTick()
    initWarehouseSelect2()
    await initApprovalSelect2()
  } catch (err) {
    showAlert('Error', err.response?.data?.message || 'Failed to load Stock Beginning', 'danger')
  }
}

/* -------------------- Mounted & Unmounted -------------------- */
onMounted(async () => {
  await fetchWarehouses()
  form.value.approvals = [
    { request_type: 'review', user_id: null, isDefault: true },
    { request_type: 'check', user_id: null, isDefault: true },
    { request_type: 'approve', user_id: null, isDefault: true }
  ]
  initDatepicker()
  initWarehouseSelect2()
  await initApprovalSelect2()

  if (props.stockBeginningId) {
    isEditMode.value = true
    await loadEditData(props.stockBeginningId)
  }
})

onUnmounted(() => {
  $('#beginning_date').datepicker('destroy')
  if (warehouseSelect.value) destroySelect2(warehouseSelect.value)
  $('.approval-type-select, .user-select').each(function () { destroySelect2(this) })
})
</script>

