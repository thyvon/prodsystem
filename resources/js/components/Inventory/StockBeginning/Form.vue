<template>
  <div class="container-fluid">
    <form @submit.prevent="submitForm">
      <div class="card border mb-0 shadow">
        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
          <h4 class="mb-0 font-weight-bold">
            {{ isEditMode ? 'Edit Stock Beginning' : 'Create Stock Beginning' }}
          </h4>
          <button type="button" class="btn btn-outline-primary btn-sm" @click="goToIndex">
            Back
          </button>
        </div>

        <div class="card-body">
          <!-- Header: Warehouse + Date -->
          <div class="border rounded p-3 mb-4 bg-white">
            <h5 class="font-weight-bold mb-3 text-primary">Stock Beginning Details</h5>
            <div class="row">
              <div class="col-md-4">
                <label class="font-weight-bold">Beginning Date <span class="text-danger">*</span></label>
                <input
                  id="beginning_date"
                  type="text"
                  class="form-control"
                  readonly
                  placeholder="Select date"
                  required
                />
              </div>
              <div class="col-md-4">
                <label class="font-weight-bold">Warehouse <span class="text-danger">*</span></label>
                <select ref="warehouseSelect" class="form-control" required></select>
              </div>
            </div>
          </div>

          <!-- Items Section -->
          <div class="border rounded p-3 mb-4 bg-white">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="font-weight-bold text-primary mb-0">Stock Items</h5>
              <div>
                <button type="button" class="btn btn-primary btn-sm" @click="openProductsModal">
                  Add Products
                </button>
                <button type="button" class="btn btn-success btn-sm ml-2" @click="triggerFileInput">
                  Import Excel
                </button>
                <a href="/sampleExcel/stock_beginnings_sample.xlsx" download class="ml-2 text-info">
                  Sample
                </a>
              </div>
            </div>

            <input type="file" ref="fileInput" @change="handleFileUpload" accept=".xlsx,.xls,.csv" class="d-none" />

            <div class="table-responsive mt-3">
              <table class="table table-bordered table-sm table-hover">
                <thead class="thead-light">
                  <tr>
                    <th>Code</th>
                    <th>Description</th>
                    <th>UoM</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Total Value</th>
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
                      <input v-model.number="item.quantity" type="number" step="0.0001" min="0" class="form-control form-control-sm" required />
                    </td>
                    <td>
                      <input v-model.number="item.unit_price" type="number" step="0.0001" min="0" class="form-control form-control-sm" required />
                    </td>
                    <td>{{ (item.quantity * item.unit_price).toFixed(4) }}</td>
                    <td>
                      <input v-model="item.remarks" class="form-control form-control-sm" />
                    </td>
                    <td>
                      <button @click="removeItem(i)" type="button" class="btn btn-danger btn-sm">
                        Remove
                      </button>
                    </td>
                  </tr>
                  <tr v-if="!form.items.length">
                    <td colspan="8" class="text-center text-muted">No items added yet.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Approvals -->
          <div class="border rounded p-3 mb-4 bg-white">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="font-weight-bold text-primary mb-0">Approval Assignments</h5>
              <button type="button" class="btn btn-outline-primary btn-sm" @click="addApproval">
                + Add Approval
              </button>
            </div>

            <div class="table-responsive">
              <table class="table table-bordered table-sm">
                <thead class="thead-light">
                  <tr>
                    <th>Type</th>
                    <th>Assigned User</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(approval, i) in form.approvals" :key="i">
                    <td>
                      <select
                        :data-index="i"
                        class="form-control approval-type-select"
                        :disabled="approval.isDefault"
                        required
                      >
                        <option value="">Select Type</option>
                        <option value="review">Review</option>
                        <option value="check">Check</option>
                        <option value="approve">Approve</option>
                      </select>
                    </td>
                    <td>
                      <select :data-index="i" class="form-control user-select" required></select>
                    </td>
                    <td>
                      <button
                        v-if="!approval.isDefault"
                        @click="removeApproval(i)"
                        type="button"
                        class="btn btn-danger btn-sm"
                      >
                        Remove
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Submit -->
          <div class="text-right">
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="isSubmitting || !form.items.length"
            >
              <span v-if="isSubmitting" class="spinner-border spinner-border-sm mr-2"></span>
              {{ isEditMode ? 'Update' : 'Create' }}
            </button>
            <button type="button" class="btn btn-secondary ml-2" @click="goToIndex">
              Cancel
            </button>
          </div>
        </div>
      </div>
    </form>

    <!-- Product Modal -->
    <div ref="itemsModal" class="modal fade" tabindex="-1">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Select Products</h5>
            <button type="button" class="close" @click="closeItemsModal">×</button>
          </div>
          <div class="modal-body">
            <div class="mb-3 text-right">
              <input type="checkbox" @change="toggleAll" /> Select All
            </div>
            <table class="table table-bordered table-hover">
              <!-- DataTable will inject here -->
            </table>
          </div>
          <div class="modal-footer">
            <button @click="addSelectedItems" class="btn btn-primary">Add Selected</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue'
import axios from 'axios'
import { showAlert } from '@/Utils/bootbox'
import { initSelect2, destroySelect2 } from '@/Utils/select2'

// -------------------- Props & Emits --------------------
const props = defineProps({ initialData: { type: Object, default: () => ({}) } })
const emit = defineEmits(['submitted'])

// -------------------- Reactive State --------------------
const isEditMode = ref(!!props.initialData?.id)
const isSubmitting = ref(false)
const isImporting = ref(false)

const form = ref({
  warehouse_id: null,
  beginning_date: '',
  items: [],
  approvals: []
})

const fileInput = ref(null)
const itemsModal = ref(null)
const warehouseSelect = ref(null)
let approvalUsers = { review: [], check: [], approve: [] }

// -------------------- Helpers --------------------
const goToIndex = () => window.location.href = '/inventory/stock-beginnings'

// -------------------- Fetch Data --------------------
const fetchApprovalUsers = async () => {
  try {
    const { data } = await axios.get('/api/inventory/stock-beginnings/users')
    approvalUsers = {
      review: data.review || [],
      check: data.check || [],
      approve: data.approve || []
    }
  } catch {
    showAlert('Error', 'Failed to load approval users', 'danger')
  }
}

const fetchWarehouses = async () => {
  const { data } = await axios.get('/api/main-value-lists/get-warehouses')
  return (data.data || data).map(w => ({ id: w.id, text: w.name || w.text }))
}

// -------------------- DatePicker --------------------
const initDatepicker = () => {
  $('#beginning_date').datepicker({
    format: 'yyyy-mm-dd',
    autoclose: true,
    todayHighlight: true
  }).on('changeDate', e => { form.value.beginning_date = e.format() })
}

// -------------------- Select2 --------------------
const initWarehouseSelect2 = async () => {
  const warehouses = await fetchWarehouses()
  initSelect2(warehouseSelect.value, { placeholder: 'Select Warehouse', width: '100%', data: warehouses }, val => {
    form.value.warehouse_id = val ? Number(val) : null
  })
  if (form.value.warehouse_id) $(warehouseSelect.value).val(form.value.warehouse_id).trigger('change')
}

const initApprovalSelect2 = async () => {
  await nextTick()
  $('.approval-type-select').each(function () {
    const i = $(this).data('index')
    const approval = form.value.approvals[i]

    initSelect2(this, {
      placeholder: 'Select Type',
      width: '100%',
      data: [
        { id: 'review', text: 'Review' },
        { id: 'check', text: 'Check' },
        { id: 'approve', text: 'Approve' }
      ],
      allowClear: !approval.isDefault
    }, val => { form.value.approvals[i].request_type = val || ''; updateUserSelect(i) })

    $(this).val(approval.request_type).trigger('change')
  })
  updateAllUserSelects()
}

const updateAllUserSelects = () => $('.user-select').each(function () { updateUserSelect($(this).data('index')) })

const updateUserSelect = i => nextTick(() => {
  const el = document.querySelector(`.user-select[data-index="${i}"]`)
  if (!el) return
  const users = approvalUsers[form.value.approvals[i].request_type] || []
  destroySelect2(el)
  initSelect2(el, { placeholder: 'Select User', width: '100%', data: users.map(u => ({ id: u.id, text: u.name })) },
    val => form.value.approvals[i].user_id = val ? Number(val) : null
  )
  $(el).val(form.value.approvals[i].user_id || '').trigger('change')
})

// -------------------- Approvals --------------------
const addApproval = () => { form.value.approvals.push({ request_type: '', user_id: null, isDefault: false }); nextTick(initApprovalSelect2) }
const removeApproval = i => {
  if (form.value.approvals[i].isDefault) return
  destroySelect2(document.querySelector(`.approval-type-select[data-index="${i}"]`))
  destroySelect2(document.querySelector(`.user-select[data-index="${i}"]`))
  form.value.approvals.splice(i, 1)
}
const validateApprovals = () => {
  const types = form.value.approvals.map(a => a.request_type)
  const hasAll = ['review', 'check', 'approve'].every(t => types.includes(t))
  const hasUsers = form.value.approvals.every(a => a.user_id)
  if (!hasAll || !hasUsers) { showAlert('Error', 'Please assign Review, Check, and Approve users.', 'danger'); return false }
  return true
}

// -------------------- Products Modal --------------------
const openProductsModal = async () => {
  await nextTick()
  if (!form.value.warehouse_id || !form.value.beginning_date) { showAlert('Warning', 'Please select Warehouse and Beginning Date first.', 'warning'); return }

  nextTick(() => {
    const tableEl = $(itemsModal.value).find('table')
    if ($.fn.DataTable.isDataTable(tableEl)) tableEl.DataTable().destroy()

    tableEl.DataTable({
      serverSide: true,
      processing: true,
      ajax: {
        url: '/api/inventory/stock-beginnings/get-products',
        type: 'GET',
        data: function (d) {
          // Add reactive Vue values dynamically
          d.warehouse_id = form.value.warehouse_id
          d.cutoff_date = form.value.beginning_date
        }
      },
      columns: [
        { data: 'id', orderable: false, render: id => `<input type="checkbox" class="select-item" value="${id}">` },
        { data: 'item_code' },
        { data: 'product_name' },
        { data: 'description' },
        { data: 'unit_name' }
      ]
    })

    $(itemsModal.value).modal('show')
  })
}
const addSelectedItems = () => {
  const table = $(itemsModal.value).find('table').DataTable()
  table.rows().every(function () {
    if ($(this.node()).find('.select-item').is(':checked')) {
      const p = this.data()
      if (!form.value.items.find(i => i.product_id === p.id)) form.value.items.push({ product_id: p.id, item_code: p.item_code, product_name: p.product_name || '', description: p.description || '', unit_name: p.unit_name || '', quantity: 0, unit_price: 0, remarks: '' })
    }
  })
  $(itemsModal.value).find('.select-item').prop('checked', false)
  $(itemsModal.value).modal('hide')
}
const removeItem = i => form.value.items.splice(i, 1)
const closeItemsModal = () => $(itemsModal.value).modal('hide')
const toggleAll = e => $(itemsModal.value).find('.select-item').prop('checked', e.target.checked)

// -------------------- File Import --------------------
const triggerFileInput = () => fileInput.value.click()
const handleFileUpload = e => { if (e.target.files[0]) importFile() }
const importFile = async () => {
  const file = fileInput.value.files[0]
  if (!file || !form.value.warehouse_id || !form.value.beginning_date) { showAlert('Warning', 'Please select Warehouse and Date first.', 'warning'); return }

  isImporting.value = true
  const fd = new FormData(); fd.append('file', file)

  try {
    const { data } = await axios.post('/api/inventory/stock-beginnings/import', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    if (data.errors?.length) { showAlert('Error', data.errors.join('<br>'), 'danger', { html: true }); return }

    (data.data?.items || []).forEach(r => {
      if (!form.value.items.find(x => x.product_id === r.product_id)) form.value.items.push({
        product_id: r.product_id, item_code: r.item_code, product_name: r.product_name || '', description: r.description || '',
        unit_name: r.unit_name || '', quantity: parseFloat(r.quantity) || 0, unit_price: parseFloat(r.unit_price) || 0, remarks: r.remarks || ''
      })
    })
    showAlert('Success', `Imported ${(data.data?.items || []).length} items`, 'success')
    fileInput.value.value = ''
  } catch (err) { showAlert('Error', err.response?.data?.message || 'Import failed', 'danger') }
  finally { isImporting.value = false }
}

// -------------------- Submit --------------------
const submitForm = async () => {
  if (!form.value.warehouse_id || !form.value.beginning_date || !form.value.items.length) { showAlert('Error', 'Please fill all required fields and add items.', 'danger'); return }
  if (!validateApprovals()) return

  isSubmitting.value = true
  try {
    const payload = { warehouse_id: form.value.warehouse_id, beginning_date: form.value.beginning_date,
      items: form.value.items.map(i => ({ product_id: i.product_id, quantity: parseFloat(i.quantity), unit_price: parseFloat(i.unit_price), remarks: i.remarks || null })),
      approvals: form.value.approvals.map(a => ({ user_id: a.user_id, request_type: a.request_type }))
    }
    const url = isEditMode.value ? `/api/inventory/stock-beginnings/${props.initialData.id}` : '/api/inventory/stock-beginnings'
    await axios[isEditMode.value ? 'put' : 'post'](url, payload)
    showAlert('Success', 'Stock beginning saved successfully!', 'success')
    emit('submitted'); goToIndex()
  } catch (err) { showAlert('Error', err.response?.data?.message || 'Failed to save', 'danger') }
  finally { isSubmitting.value = false }
}

// -------------------- Edit Mode --------------------
const loadEditData = async () => {
  const d = props.initialData
  form.value.warehouse_id = Number(d.warehouse_id)
  form.value.beginning_date = d.beginning_date
  $('#beginning_date').datepicker('setDate', d.beginning_date)
  form.value.items = d.items.map(i => ({ product_id: i.product_id, item_code: i.item_code, product_name: i.product_name || '', description: i.description || '', unit_name: i.unit_name || '', quantity: parseFloat(i.quantity), unit_price: parseFloat(i.unit_price), remarks: i.remarks || '' }))
  form.value.approvals = d.approvals.map(a => ({ request_type: a.request_type, user_id: a.user_id, isDefault: true }))
  await nextTick(); await initWarehouseSelect2(); await initApprovalSelect2()
}

// -------------------- Mounted & Unmounted --------------------
onMounted(async () => {
  await fetchApprovalUsers()
  if (!isEditMode.value) form.value.approvals = [{ request_type: 'review', user_id: null, isDefault: true }, { request_type: 'check', user_id: null, isDefault: true }, { request_type: 'approve', user_id: null, isDefault: true }]
  initDatepicker()
  await initWarehouseSelect2(); await initApprovalSelect2()
  if (isEditMode.value) await loadEditData()
})

onUnmounted(() => {
  $('#beginning_date').datepicker('destroy')
  if (warehouseSelect.value) destroySelect2(warehouseSelect.value)
  $('.approval-type-select, .user-select').each(function () { destroySelect2(this) })
})
</script>

