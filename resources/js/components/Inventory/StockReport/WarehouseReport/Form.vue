<template>
  <div class="container-fluid">
    <form @submit.prevent="submitForm">
      <div class="card border mb-0 shadow">
        <div class="card-header d-flex justify-content-between align-items-center bg-light py-2">
          <h4 class="mb-0 font-weight-bold">
            {{ isEditMode ? 'Edit Stock Report' : 'Create Stock Report' }}
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
                <label class="font-weight-bold">Report Date <span class="text-danger">*</span></label>
                <input
                  id="report_date"
                  v-model="form.report_date"
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
              <h5 class="mb-0 text-primary">Selected Items</h5>
              <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" @click="downloadSampleExcel">
                  <i class="fal fa-file-excel mr-1"></i>Download Sample Excel
                </button>

                <button type="button" class="btn btn-sm btn-outline-secondary" @click="triggerFileInput" :disabled="isImporting">
                  <span v-if="isImporting" class="spinner-border spinner-border-sm mr-1"></span>
                  <i v-else class="fal fa-file-excel mr-1"></i>Import Excel
                </button>
                <input type="file" ref="fileInput" class="d-none" accept=".xlsx,.xls,.csv" @change="handleFileUpload" />

                <button type="button" class="btn btn-sm btn-success" @click="openProductsModal">
                  <i class="fal fa-plus mr-1"></i>Add Items
                </button>
              </div>
            </div>

            <div class="table-responsive" style="overflow-x: auto;">
            <table class="table table-bordered table-sm table-hover" style="min-width: 2000px;">
                <thead class="thead-light">
                <tr>
                    <th style="min-width: 120px;">Item Code</th>
                    <th style="min-width: 200px;">Description</th>
                    <th style="min-width: 80px;">UoM</th>
                    <th style="min-width: 100px;">Unit Price</th>
                    <th style="min-width: 100px;">6-Month<br>Avg Usage</th>
                    <th style="min-width: 100px;">Last Month<br>Usage</th>
                    <th style="min-width: 100px;">Stock<br>Beginning</th>
                    <th style="min-width: 100px;">Order<br>Plan Qty</th>
                    <th style="min-width: 100px;">Demand<br>Forecast</th>
                    <th style="min-width: 100px;">Stock<br>Ending</th>
                    <th style="min-width: 100px;">Ending Stock<br>Cover Day</th>
                    <th style="min-width: 100px;">Target Safety<br>Stock Day</th>
                    <th style="min-width: 100px;">Stock<br>Value</th>
                    <th style="min-width: 100px;">Inventory<br>Reorder Qty</th>
                    <th style="min-width: 100px;">Reorder<br>Level Qty</th>
                    <th style="min-width: 100px;">Max Inventory<br>Level Qty</th>
                    <th style="min-width: 100px;">Max Inventory<br>Usage Day</th>
                    <th style="min-width: 200px;">Remarks</th>
                    <th style="min-width: 100px;">Action</th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="(item, i) in form.items" :key="i">
                    <td>{{ item.item_code }}</td>
                    <td>{{ item.product_name }} {{ item.description }}</td>
                    <td>{{ item.unit_name }}</td>
                    <td>{{ item.unit_price }}</td>
                    <td>{{ item.avg_6_month_usage }}</td>
                    <td>{{ item.last_month_usage }}</td>
                    <td>{{ item.stock_beginning }}</td>
                    <td>{{ item.order_plan_qty }}</td>
                    <td>{{ item.demand_forecast }}</td>
                    <td>{{ item.stock_ending }}</td>
                    <td>{{ item.stock_ending_cover_day }}</td>
                    <td>{{ item.target_safety_stock_day }}</td>
                    <td>{{ item.stock_value }}</td>
                    <td>{{ item.inv_reorder_qty }}</td>
                    <td>{{ item.reoder_level_qty }}</td>
                    <td>{{ item.max_inv_level_qty }}</td>
                    <td>{{ item.max_inv_usage_day }}</td>
                    <td>
                    <input type="text" v-model="item.remarks" class="form-control form-control-sm" placeholder="Enter remarks" />
                    </td>
                    <td>
                    <button type="button" class="btn btn-sm btn-danger" @click="removeItem(i)">
                        Remove
                    </button>
                    </td>
                </tr>
                <tr v-if="!form.items.length">
                    <td colspan="19" class="text-center text-muted">No items added yet</td>
                </tr>
                </tbody>
            </table>
            </div>
          </div>

          <!-- Approval Table -->
          <div class="border rounded p-4 bg-light">
            <h5 class="h5 fw-bold text-primary mb-3">Approval Assignments</h5>

            <div class="table-responsive">
              <table class="table table-bordered table-sm align-middle">
                <thead class="table-light">
                  <tr>
                    <th width="35%">Approval Type</th>
                    <th width="55%">Assigned User</th>
                    <th width="10%" class="text-center">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(approval, index) in form.approvals" :key="index">
                    <td>
                      <select
                        :ref="el => setTypeSelectRef(el, index)"
                        class="form-control form-control-sm"
                        v-model="approval.request_type"
                        :disabled="approval.isDefault"
                      >
                        <option value="">Select Type</option>
                        <option value="check">Check</option>
                        <option value="approve">Approve</option>
                      </select>
                    </td>
                    <td>
                      <select
                        :ref="el => setUserSelectRef(el, index)"
                        class="form-control form-control-sm"
                        :disabled="!approval.request_type"
                      >
                        <option value="">Select User</option>
                        <option v-for="user in approval.availableUsers" :key="user.id" :value="user.id">
                          {{ user.name }} ({{ user.card_number || user.email }})
                        </option>
                      </select>
                    </td>
                    <td class="text-center">
                      <button
                        type="button"
                        class="btn btn-danger btn-sm"
                        @click="removeApproval(index)"
                        :disabled="approval.isDefault"
                        title="Remove"
                      >
                        Remove
                      </button>
                    </td>
                  </tr>
                  <tr v-if="form.approvals.length === 0">
                    <td colspan="3" class="text-center text-muted py-3">No approval assignments</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Submit -->
          <div class="text-right">
            <button type="submit" class="btn btn-primary" :disabled="isSubmitting || !form.items.length">
              <span v-if="isSubmitting" class="spinner-border spinner-border-sm mr-2"></span>
              {{ isEditMode ? form.actionButtonText : 'Create Stock Report' }}
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
                    <th>Item Code</th>
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
import { ref, onMounted, onUnmounted, nextTick } from 'vue'
import axios from 'axios'
import { showAlert } from '@/Utils/bootbox'
import { initSelect2, destroySelect2 } from '@/Utils/select2'

const props = defineProps({ warehouseProductReportId: [String, Number] })
const emit = defineEmits(['submitted'])

const isEditMode = ref(false)
const isSubmitting = ref(false)
const isImporting = ref(false)

const form = ref({
  report_date: '',
  warehouse_id: null,
  reference_no: '',
  remarks: '',
  items: [], // { product_id }
  approvals: [], // keep approval data structure
  actionButtonText: 'Update Stock Report'
})

const warehouses = ref([])
const fileInput = ref(null)
const itemsModal = ref(null)
const warehouseSelect = ref(null)

// ================= Approval Section =================
const typeSelectRefs = ref([])
const userSelectRefs = ref([])
const approvalUsers = ref({})

const setTypeSelectRef = (el, index) => { typeSelectRefs.value[index] = el }
const setUserSelectRef = (el, index) => { userSelectRefs.value[index] = el }

const defaultApprovalTypes = ['check', 'approve']

const initApprovals = () => {
  if (!form.value.approvals.length) {
    defaultApprovalTypes.forEach(type => {
      form.value.approvals.push({
        id: null,
        request_type: type,   // <-- set default type here
        user_id: null,
        isDefault: true,
        availableUsers: [] // to be loaded from API
      })
    })
  } else {
    // Ensure all default types exist
    defaultApprovalTypes.forEach(type => {
      if (!form.value.approvals.some(a => a.request_type === type)) {
        form.value.approvals.push({
          id: null,
          request_type: type, // <-- default type
          user_id: null,
          isDefault: true,
          availableUsers: []
        })
      }
    })
  }
}

const fetchApprovalUsers = async () => {
  try {
    const { data } = await axios.get('/api/inventory/stock-reports/report/get-approval-users')
    approvalUsers.value = data || {}
    // Assign availableUsers to approvals
    form.value.approvals.forEach(a => {
      a.availableUsers = approvalUsers.value[a.request_type] || []
    })
  } catch (err) {
    showAlert('Error', 'Failed to load approval users', 'danger')
  }
}

const initApprovalSelect2 = async () => {
  form.value.approvals.forEach((approval, index) => {
    // Type select
    const typeEl = typeSelectRefs.value[index]
    if (typeEl && !typeEl.dataset.initialized) {
      $(typeEl).select2({
        placeholder: 'Select Type',
        width: '100%',
        allowClear: !approval.isDefault
      }).val(approval.request_type || null).trigger('change')
      .on('change', () => {
        approval.request_type = typeEl.value
        approval.availableUsers = approvalUsers.value[typeEl.value] || []
        refreshUserDropdown(index)
      })

      // Disable default type in Select2
      if (approval.isDefault) {
        $(typeEl).prop("disabled", true)  // disable the select
        $(typeEl).select2({ width: '100%' }) // refresh Select2 to apply disable
      }

      typeEl.dataset.initialized = true
    }

    // User select
    refreshUserDropdown(index)
  })
}

// Refresh user dropdown based on type
const refreshUserDropdown = async (index) => {
  const approval = form.value.approvals[index]
  const $select = userSelectRefs.value[index]
  if (!$select) return

  if ($($select).data('select2')) $($select).select2('destroy')

  $($select).select2({
    placeholder: 'Select User',
    width: '100%',
    allowClear: true,
    data: approval.availableUsers.map(u => ({ id: u.id, text: `${u.name} (${u.card_number || ''})` }))
  }).val(approval.user_id || null).trigger('change')
  .on('change', e => {
    approval.user_id = e.target.value ? Number(e.target.value) : null
  })
}

// Remove approval
const removeApproval = (index) => {
  if (form.value.approvals[index].isDefault) {
    showAlert('Cannot Remove', 'Default approval steps cannot be removed.', 'warning')
    return
  }
  form.value.approvals.splice(index, 1)
}
// =====================================================

let productsTable = null

const goToIndex = () => window.location.href = '/inventory/stock-reports/reports-list'

// ==================== Fetching Data ====================
const fetchWarehouses = async () => {
  const { data } = await axios.get('/api/main-value-lists/get-warehouses')
  warehouses.value = data.data || data
}

// ==================== Load Edit Data ====================
const loadEditData = async () => {
  if (!props.warehouseProductReportId) return

  isEditMode.value = true
  try {
    const { data } = await axios.get(`/api/inventory/stock-reports/${props.warehouseProductReportId}/get-report-edit-data`)
    const report = data.data || data

    form.value.report_date = report.report_date
    form.value.warehouse_id = report.warehouse_id
    form.value.reference_no = report.reference_no
    form.value.remarks = report.remarks || ''
    form.value.items = report.items || []
    form.value.approvals = report.approvals || []

    // Mark default types in edit mode
    form.value.approvals.forEach(a => {
      if (defaultApprovalTypes.includes(a.request_type)) a.isDefault = true
    })

    await nextTick()
    if (warehouseSelect.value) $(warehouseSelect.value).val(form.value.warehouse_id).trigger('change')
    $('#report_date').datepicker('update', form.value.report_date)
  } catch (err) {
    showAlert('Error', err.response?.data?.message || 'Failed to load report', 'danger')
  }
}

// ==================== Datepicker ====================
const initDatepicker = () => {
  $('#report_date').datepicker({
    format: 'yyyy-mm-dd',
    autoclose: true,
    todayHighlight: true,
    orientation: 'bottom left'
  }).on('changeDate', e => {
    form.value.report_date = e.format()
  })
}

// ==================== Warehouse Select ====================
const initWarehouseSelect2 = () => {
  if (!warehouseSelect.value) return
  initSelect2(warehouseSelect.value, {
    placeholder: 'Select Warehouse',
    width: '100%',
    allowClear: false
  }, val => form.value.warehouse_id = val)

  if (form.value.warehouse_id) {
    $(warehouseSelect.value).val(form.value.warehouse_id).trigger('change')
  }
}

// ==================== Products Modal ====================
const openProductsModal = () => {
  if (!form.value.warehouse_id || !form.value.report_date) {
    showAlert('Warning', 'Please select Warehouse and Report Date first.', 'warning')
    return
  }

  const table = $(itemsModal.value).find('table')
  if (!productsTable) {
    productsTable = table.DataTable({
      serverSide: true,
      processing: true,
      responsive: true,
      autoWidth: false,
      ajax: {
        url: '/api/inventory/stock-reports/get-products',
        type: 'GET',
        data: d => ({ ...d, warehouse_id: form.value.warehouse_id })
      },
      columns: [
        {
          data: 'id',
          orderable: false,
          render: id => `<div class="custom-control custom-checkbox">
                          <input type="checkbox" class="custom-control-input select-item" id="chk-${id}" value="${id}">
                          <label class="custom-control-label" for="chk-${id}"></label>
                        </div>`
        },
        { data: 'variant.item_code' },
        { data: 'variant.product.name' },
        { data: 'variant.product.unit.name' },
        { data: 'stock_onhand', className: 'text-right' }
      ]
    })
  } else productsTable.ajax.reload()

  $(itemsModal.value).modal('show')
}

const addSelectedItems = () => {
  const table = $(itemsModal.value).find('table').DataTable()
  const selected = table.rows().data().toArray().filter(r => $(`#chk-${r.id}`).is(':checked'))

  const existingIds = new Set(form.value.items.map(i => i.product_id))
  
  selected.forEach(p => {
    if (!existingIds.has(p.id)) {
      form.value.items.push({
        warehouse_product_id: p.id,
        product_id: p.product_id,
        item_code: p.variant?.item_code || '',
        product_name: p.variant?.product?.name || '',
        description: p.variant?.description || '',
        unit_name: p.variant?.product?.unit?.name || '',
        unit_price: p.avg_price || 0,                   
        avg_6_month_usage: p.avg_usage_6m || 0,
        last_month_usage: p.last_month_usage || 0,
        stock_beginning: p.stock_onhand || 0,
        order_plan_qty: p.order_plan_qty || 0,
        demand_forecast: p.demand_forecast || 0,
        stock_ending: p.ending_stock_qty || 0,
        stock_ending_cover_day: p.stock_ending_cover_day || 0,
        target_safety_stock_day: p.target_safety_stock_day || 0,
        stock_value: p.stock_value_usd || 0,
        inv_reorder_qty: p.inv_reorder_qty || 0,
        reoder_level_qty: p.reorder_level_qty || 0,
        max_inv_level_qty: p.max_inv_level_qty || 0,
        max_inv_usage_day: p.max_inv_usage_day || 0, 
      })
    }
  })

  $(itemsModal.value).find('.select-item').prop('checked', false)
  $(itemsModal.value).modal('hide')
}

const removeItem = i => form.value.items.splice(i, 1)
const closeItemsModal = () => $(itemsModal.value).modal('hide')
const toggleAll = e => $(itemsModal.value).find('.select-item').prop('checked', e.target.checked)

// ==================== File Import ====================
const triggerFileInput = () => fileInput.value.click()
const handleFileUpload = e => { if (e.target.files[0]) importFile() }

const importFile = async () => {
  const file = fileInput.value.files[0]
  if (!file) return
  if (!form.value.warehouse_id || !form.value.report_date) {
    showAlert('Warning', 'Please select Warehouse and Report Date first.', 'warning')
    return
  }

  isImporting.value = true
  const formData = new FormData()
  formData.append("file", file)

  try {
    const { data } = await axios.post("/api/inventory/stock-reports/import", formData, {
      headers: { "Content-Type": "multipart/form-data" }
    })

    const rows = data.data?.items || []
    const existingIds = new Set(form.value.items.map(i => i.product_id))
    rows.forEach(r => {
      if (!existingIds.has(r.product_id)) {
        form.value.items.push({ product_id: r.product_id })
      }
    })

    fileInput.value.value = ""
    showAlert("Success", `Imported ${rows.length} items`, "success")
  } catch (err) {
    showAlert('Error', err.response?.data?.message || 'Failed to import', 'danger')
  } finally {
    isImporting.value = false
    if (fileInput.value) fileInput.value.value = ""
  }
}

// ==================== Form Submission ====================
const submitForm = async () => {
  if (!form.value.report_date || !form.value.warehouse_id || !form.value.items.length) {
    showAlert('Error', 'Please complete all required fields.', 'danger')
    return
  }

  isSubmitting.value = true
  try {
    const payload = {
      warehouse_id: form.value.warehouse_id,
      report_date: form.value.report_date,
      reference_no: form.value.reference_no,
      remarks: form.value.remarks || null,
      items: form.value.items.map(i => ({
        warehouse_product_id: i.warehouse_product_id,
        remarks: i.remarks || null
      })),
      approvals: form.value.approvals.map(a => ({
        id: a.id,
        request_type: a.request_type,
        user_id: a.user_id
      }))
    }

    const url = isEditMode.value
      ? `/api/inventory/stock-reports/${props.warehouseProductReportId}/update-report`
      : '/api/inventory/stock-reports/store-report'

    await axios[isEditMode.value ? 'put' : 'post'](url, payload)
    await showAlert('Success', 'Stock report saved successfully!', 'success')
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
  await loadEditData()  // Load edit data
  initApprovals()        // Ensure default approvals
  await fetchApprovalUsers()
  await nextTick()
  initApprovalSelect2()  // Initialize Select2 for approvals
})

onUnmounted(() => {
  $('#report_date').datepicker('destroy')
  if (warehouseSelect.value) destroySelect2(warehouseSelect.value)
})
</script>



