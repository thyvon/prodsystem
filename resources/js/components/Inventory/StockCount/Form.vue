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
                    {{ w.text }}
                  </option>
                </select>
              </div>

              <div class="form-group col-md-4">
                <label class="font-weight-bold">Reference No</label>
                <input v-model="form.reference_no" type="text" class="form-control" readonly placeholder="Auto-generated"/>
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
              <div class="d-flex align-items-center gap-2">
                
                <!-- Download Sample Excel Button -->
                <button
                  type="button"
                  class="btn btn-sm btn-outline-secondary d-flex align-items-center"
                  @click="downloadSampleExcel"
                >
                  <i class="fal fa-file-excel mr-1"></i>
                  Download Sample Excel
                </button>

                <!-- Import Excel Button -->
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

                <!-- Hidden File Input -->
                <input 
                  type="file" 
                  ref="fileInput" 
                  class="d-none" 
                  accept=".xlsx,.xls,.csv" 
                  @change="handleFileUpload" 
                />

                <!-- Add Items Button -->
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
                    <th style="min-width: 90px;">Code</th>
                    <th>Description</th>
                    <th>UoM</th>
                    <th>Stock Ending</th>
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
            <!-- <button type="button" class="btn btn-sm btn-outline-primary mt-2" @click="addApproval">
              Add Approval
            </button> -->
          </div>

          <!-- Submit -->
          <div class="text-right">
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="isSubmitting || !form.items.length || !validateApprovals()"
            >
              <span v-if="isSubmitting" class="spinner-border spinner-border-sm mr-2"></span>
              {{ isEditMode ? form.actionButtonText : 'Create Stock Count' }}
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
            <div class="table-responsive">
              <table ref="modalTable" class="table table-bordered table-sm table-hover table-striped">
                <thead class="thead-light">
                  <tr>
                    <th>
                      <div class="custom-control custom-checkbox">
                        <input 
                          type="checkbox" 
                          class="custom-control-input" 
                          id="select-all" 
                          @change="toggleAll($event)"
                        />
                        <label class="custom-control-label" for="select-all"></label>
                      </div>
                    </th>
                    <th>Code</th>
                    <th>Description</th>
                    <th>UoM</th>
                    <th>Stock Ending</th>
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
import { showAlert } from '@/Utils/bootbox'
import { initSelect2, destroySelect2 } from '@/Utils/select2'
import axios from 'axios'

// ==================== Props & Emits ====================
const props = defineProps({ initialData: Object })
const emit = defineEmits(['submitted'])

// ==================== State ====================
const isEditMode = ref(!!props.initialData?.id)
const isSubmitting = ref(false)
const isImporting = ref(false)

const form = ref({
  transaction_date: '',
  warehouse_id: null,
  reference_no: '',
  remarks: '',
  items: [],
  approvals: [],
  actionButtonText: 'Submit'
})

const warehouses = ref([])
const fileInput = ref(null)
const itemsModal = ref(null)
const warehouseSelect = ref(null)
let productsTable = null
let approvalUsers = ref({ initial: [], approve: [] })

// ==================== Navigation ====================
const goToIndex = () => window.location.href = '/inventory/stock-counts'

// ==================== Fetch Warehouses ====================
const fetchWarehouses = async () => {
  const { data } = await axios.get('/api/main-value-lists/get-warehouses')
  warehouses.value = data.data || data
}

// ==================== Fetch Approval Users ====================
const fetchApprovalUsers = async () => {
  const { data } = await axios.get('/api/inventory/stock-counts/get-approval-users')
  approvalUsers.value = { initial: data.initial || [], approve: data.approve || [] }
}

// ==================== Datepicker ====================
const initDatepicker = () => {
  $('#transaction_date').datepicker({
    format: 'yyyy-mm-dd',
    autoclose: true,
    todayHighlight: true,
    orientation: 'bottom left'
  }).on('changeDate', e => {
    form.value.transaction_date = e.format()
  })
}

// ==================== Warehouse Select2 ====================
const initWarehouseSelect2 = () => {
  if (!warehouseSelect.value) return
  initSelect2(warehouseSelect.value, {
    placeholder: 'Select Warehouse',
    width: '100%',
    allowClear: false
  }, val => form.value.warehouse_id = val)

  nextTick(() => {
    if (form.value.warehouse_id) {
      $(warehouseSelect.value).val(form.value.warehouse_id).trigger('change')
    }
  })
}

// ==================== Approval Select2 ====================
const initApprovalSelect2 = () => {
  document.querySelectorAll('.approval-type-select').forEach((el, index) => {
    initSelect2(el, { placeholder: 'Select Type', width: '100%' }, val => {
      form.value.approvals[index].request_type = val
      updateUserSelect(index)
    })
    $(el).val(form.value.approvals[index].request_type || '').trigger('change')
  })

  document.querySelectorAll('.user-select').forEach((el, index) => {
    updateUserSelect(index)
    const selectedUserId = form.value.approvals[index].user_id
    if (selectedUserId) $(el).val(selectedUserId).trigger('change')
  })
}

const updateUserSelect = (index) => {
  const select = document.querySelector(`.user-select[data-index="${index}"]`)
  if (!select) return
  const type = form.value.approvals[index].request_type
  const users = approvalUsers.value[type] || []
  const data = users.map(u => ({ id: u.id, text: u.name }))

  if ($(select).hasClass('select2-hidden-accessible')) {
    $(select).empty().select2({ data, placeholder: 'Select User', width: '100%' })
  } else {
    initSelect2(select, { data, placeholder: 'Select User', width: '100%' }, val => {
      form.value.approvals[index].user_id = val ? Number(val) : null
    })
  }

  if (form.value.approvals[index].user_id) {
    $(select).val(form.value.approvals[index].user_id).trigger('change')
  }
}

const addApproval = () => {
  form.value.approvals.push({ request_type: '', user_id: null, isDefault: false })
  const index = form.value.approvals.length - 1
  nextTick(() => updateUserSelect(index))
}

const removeApproval = (i) => {
  if (form.value.approvals[i].isDefault) return
  ['.approval-type-select', '.user-select'].forEach(sel => {
    const el = document.querySelector(`${sel}[data-index="${i}"]`)
    if (el) destroySelect2(el)
  })
  form.value.approvals.splice(i, 1)
}

const validateApprovals = () => {
  const types = form.value.approvals.map(a => a.request_type)
  return types.includes('initial') && types.includes('approve') && new Set(types).size === 2
}

// ==================== Load Edit Data ====================
const loadEditDataFromProps = async () => {
  const d = props.initialData
  if (!d) return

  form.value.transaction_date = d.transaction_date
  form.value.warehouse_id = d.warehouse_id
  form.value.reference_no = d.reference_no
  form.value.remarks = d.remarks
  form.value.actionButtonText = d.buttonSubmitText || 'Update'

  form.value.items = d.items.map(i => ({
    id: i.id,
    product_id: i.product_id,
    item_code: i.product_code,
    product_name: (i.description || '').split(' ')[0] || '',
    description: i.description || '',
    unit_name: i.unit_name || '',
    ending_quantity: parseFloat(i.ending_quantity ?? 0),
    counted_quantity: parseFloat(i.counted_quantity ?? 0),
    remarks: i.remarks || '',
    stock_on_hand: parseFloat(i.stock_on_hand ?? 0),
    average_price: parseFloat(i.average_price ?? 0)
  }))

  form.value.approvals = d.approvals.map(a => ({
    id: a.id,
    request_type: a.request_type,
    user_id: a.user_id,
    isDefault: true
  }))

  await nextTick()
  initWarehouseSelect2()
  await fetchApprovalUsers()
  nextTick(() => initApprovalSelect2())
}

// ==================== Form Submission ====================
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
      approvals: form.value.approvals.map(a => ({ user_id: a.user_id, request_type: a.request_type }))
    }

    const url = isEditMode.value
      ? `/api/inventory/stock-counts/${props.initialData.id}`
      : '/api/inventory/stock-counts'

    await axios[isEditMode.value ? 'put' : 'post'](url, payload)
    await showAlert('Success', 'Stock count saved successfully!', 'success')
    emit('submitted')
    goToIndex()
  } catch (err) {
    await showAlert('Error', err.response?.data?.message || 'Failed to save', 'danger')
  } finally {
    isSubmitting.value = false
  }
}

// ==================== Lifecycle ====================
onMounted(async () => {
  await fetchWarehouses()
  initDatepicker()
  initWarehouseSelect2()
  await fetchApprovalUsers()

  if (isEditMode.value) {
    await loadEditDataFromProps()
  } else {
    form.value.approvals = [
      { request_type: 'initial', user_id: null, isDefault: true },
      { request_type: 'approve', user_id: null, isDefault: true }
    ]
    nextTick(() => initApprovalSelect2())
  }
})

onUnmounted(() => {
  $('#transaction_date').datepicker('destroy')
  if (warehouseSelect.value) destroySelect2(warehouseSelect.value)
  document.querySelectorAll('.approval-type-select, .user-select').forEach(destroySelect2)
})
</script>



<style scoped>
  .table td, .table th { vertical-align: middle; }
</style>