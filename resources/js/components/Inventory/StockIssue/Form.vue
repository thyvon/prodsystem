<template>
  <div class="container-fluid">
    <form @submit.prevent="submitForm">
      <!-- Header -->
      <div class="card border mb-0 shadow">
        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
          <h4 class="mb-0 font-weight-bold">{{ isEditMode ? 'Edit Stock Issue' : 'Create Stock Issue' }}</h4>
          <button type="button" class="btn btn-outline-primary btn-sm" @click="goToIndex">
            <i class="fal fa-backward"></i>
          </button>
        </div>

        <!-- Body -->
        <div class="card-body">
          <!-- Stock Issue Header -->
          <div class="border rounded p-3 mb-4">
            <h5 class="font-weight-bold mb-3 text-primary">🏷️ Stock Issue Details</h5>

            <div class="form-row">
              <div class="form-group col-md-3">
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

              <div class="form-group col-md-3">
                <label class="font-weight-bold">Transaction Type <span class="text-danger">*</span></label>
                <select ref="transactionTypeSelect" v-model="form.transaction_type" class="form-control"></select>
              </div>

              <div class="form-group col-md-3">
                <label class="font-weight-bold">Account Code <span class="text-danger">*</span></label>
                <input v-model="form.account_code" type="text" class="form-control" required />
              </div>

              <div class="form-group col-md-3">
                <label class="font-weight-bold">Reference No (IO)</label>
                <input v-model="form.reference_no" type="text" class="form-control" placeholder="Auto-generated if empty" />
              </div>
            </div>

            <div class="form-row">
              <div class="form-group col-md-4">
                <label class="font-weight-bold">Stock Request</label>
                <select ref="stockRequestSelect" v-model="form.stock_request_id" class="form-control">
                  <option value="">Select Stock Request</option>
                  <option v-for="sr in stockRequests" :key="sr.id" :value="sr.id">{{ sr.request_number }}</option>
                </select>
              </div>

              <div class="form-group col-md-4">
                <label class="font-weight-bold">Requested By <span class="text-danger">*</span></label>
                <select id="requestedBySelect" v-model="form.requested_by" class="form-control">
                  <option value="">Select User</option>
                  <option v-for="user in users" :key="user.id" :value="user.id">{{ user.text }}</option>
                </select>
              </div>

              <div class="form-group col-md-4">
                <label class="font-weight-bold">Warehouse <span class="text-danger">*</span></label>
                <select id="warehouseSelect" v-if="!form.stock_request_id" v-model="form.warehouse_id" class="form-control">
                  <option value="">Select Warehouse</option>
                  <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">{{ wh.text }}</option>
                </select>
                <input v-else type="text" class="form-control" :value="currentWarehouseName" readonly />
              </div>
            </div>

            <div class="form-group">
              <label class="font-weight-bold">Remarks</label>
              <textarea v-model="form.remarks" class="form-control" rows="2"></textarea>
            </div>
          </div>

          <!-- Issue Items Table -->
          <div class="border rounded p-3 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h5 class="font-weight-bold text-primary">📦 Issue Items <span class="text-danger">*</span></h5>
              <button type="button" class="btn btn-sm btn-success" @click="openItemsSelection">Add Items</button>
            </div>
            <div class="table-responsive">
              <table class="table table-bordered table-striped mb-0">
                <thead class="thead-light" style="position: sticky; top: 0; z-index: 10; background: #f8f9fa;">
                  <tr>
                    <th style="min-width: 80px;">Code</th>
                    <th style="min-width: 150px;">Description</th>
                    <th style="min-width: 60px;">UoM</th>
                    <th style="min-width: 100px;">Qty On Hand</th>
                    <th style="min-width: 100px;">Avg Price</th>
                    <th style="min-width: 100px;">Issue Qty</th>
                    <th style="min-width: 120px;">Unit Price</th>
                    <th style="min-width: 130px;">Total Price</th>
                    <th style="min-width: 120px;">Campus</th>
                    <th style="min-width: 130px;">Department</th>
                    <th style="min-width: 150px;">Remarks</th>
                    <th style="min-width: 80px;">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, index) in form.items" :key="item.id || index">
                    <td>{{ item.product_code }}</td>
                    <td>{{ item.description }}</td>
                    <td>{{ item.unit_name }}</td>
                    <td>{{ item.stock_on_hand }}</td>
                    <td>{{ item.average_price }}</td>
                    <td><input type="number" class="form-control" v-model.number="item.quantity" min="0.0000000001" step="0.0000000001" /></td>
                    <td><input type="number" class="form-control" v-model.number="item.unit_price" min="0" step="0.0000000001" /></td>
                    <td><input type="number" class="form-control" :value="(item.quantity * item.unit_price).toFixed(10)" readonly /></td>
                    <td>
                      <select class="campusSelect" v-model="item.campus_id">
                        <option v-for="campus in campuses" :key="campus.id" :value="campus.id">{{ campus.text }}</option>
                      </select>
                    </td>
                    <td>
                      <select class="departmentSelect" v-model="item.department_id">
                        <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.text }}</option>
                      </select>
                    </td>
                    <td><textarea class="form-control" rows="1" v-model="item.remarks"></textarea></td>
                    <td>
                      <button type="button" class="btn btn-sm btn-danger" @click="removeItem(index)">
                        <i class="fal fa-trash"></i>
                      </button>
                    </td>
                  </tr>
                  <tr v-if="form.items.length === 0">
                    <td colspan="10" class="text-center text-muted">No items added</td>
                  </tr>
                </tbody>
              </table>
            </div>

          </div>

          <!-- Buttons -->
          <div class="text-right">
            <button type="submit" class="btn btn-primary btn-sm mr-2" :disabled="isSubmitting || !form.items.length">
              <span v-if="isSubmitting" class="spinner-border spinner-border-sm mr-1"></span>
              {{ isEditMode ? 'Update' : 'Create' }}
            </button>
            <button type="button" class="btn btn-secondary btn-sm" @click="goToIndex">Cancel</button>
          </div>
        </div>
      </div>
    </form>

    <!-- Items Modal -->
    <div ref="itemsModal" class="modal fade" tabindex="-1" role="dialog">
      <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ modalTitle }}</h5>
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
                    <th>Unit Price</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in modalItems" :key="item.id">
                    <td>
                      <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" :id="'select-item-' + item.id" v-model="item.selected" />
                        <label class="custom-control-label" :for="'select-item-' + item.id"></label>
                      </div>
                    </td>
                    <td>{{ item.item_code || item.product_code }}</td>
                    <td>{{ item.description }}</td>
                    <td>{{ item.unit_name }}</td>
                    <td>{{ item.stock_on_hand }}</td>
                    <td>{{ item.average_price }}</td>
                  </tr>
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
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import axios from 'axios'
import { showAlert } from '@/Utils/bootbox'
import { initSelect2 } from '@/Utils/select2'

const props = defineProps({ initialData: { type: Object, default: () => ({}) }})
const emit = defineEmits(['submitted'])

const isEditMode = computed(() => !!props.initialData.id)
const isSubmitting = ref(false)
const modalTitle = ref('Select Items')

const transactionTypes = ref([
  { value: 'Issue', text: 'Issue' },
  { value: 'Transfer', text: 'Transfer' }
])

const modalItemsTable = ref(null)
const stockRequests = ref([])
const users = ref([])
const campuses = ref([])
const departments = ref([])
const products = ref([])
const warehouses = ref([])
const stockRequestSelect = ref(null)
const transactionTypeSelect = ref(null)
const currentWarehouseName = ref('')

const form = ref({
  stock_request_id: null,
  warehouse_id: null,
  transaction_date: '',
  transaction_type: '',
  account_code: '',
  reference_no: '',
  requested_by: null,
  remarks: '',
  items: []
})

const modalItems = ref([])
const itemsModal = ref(null)

// Map item for form
const mapItem = (item, source = 'product') => ({
  id: null,
  stock_request_item_id: item.id || null,
  product_id: item.product_id || item.id,
  product_code: item.item_code || item.product_code,
  description: item.description || '',
  unit_name: item.unit_name,
  quantity: parseFloat(item.quantity) || 1,
  unit_price: parseFloat(item.unit_price) || 0,
  stock_on_hand: parseFloat(item.stock_on_hand) || 0,
  average_price: parseFloat(item.average_price) || 0,
  total_price: parseFloat(((item.quantity || 1) * (item.unit_price || 0)).toFixed(10)),
  remarks: '',
  campus_id: item.campus_id || null,
  department_id: item.department_id || null,
  source
})

// Navigation
const goToIndex = () => window.location.href = '/inventory/stock-issues'
const removeItem = index => form.value.items.splice(index, 1)

// Fetch initial data
const fetchInitialData = async () => {
  try {
    const [{data: sr}, {data: u}, {data: c}, {data: d}, {data: p}, {data: w}] = await Promise.all([
      axios.get('/api/inventory/stock-issues/get-stock-requests'),
      axios.get('/api/inventory/stock-issues/get-requesters'),
      axios.get('/api/inventory/stock-issues/get-campuses'),
      axios.get('/api/inventory/stock-issues/get-departments'),
      axios.get('/api/inventory/stock-issues/get-products'),
      axios.get('/api/inventory/stock-issues/get-warehouses')
    ])
    stockRequests.value = sr.data ?? sr
    users.value = u.data ?? u
    campuses.value = c.data ?? c
    departments.value = d.data ?? d
    products.value = p.data ?? p
    warehouses.value = w.data ?? w
  } catch {
    showAlert('Error', 'Failed to fetch initial data.', 'danger')
  }
}

// Watch warehouse change

watch(() => form.value.warehouse_id, (newWhId) => {
  modalItems.value = modalItems.value.map(item => {
    const stockEntry = item.stock_by_campus?.find(s => s.warehouse_id === newWhId) || {}
    return {
      ...item,
      stock_on_hand: stockEntry.stock_on_hand || 0,
      unit_price: stockEntry.average_price || parseFloat(item.estimated_price || 0)
    }
  })
})

// Watch total price
watch(form, (newForm) => {
  newForm.items.forEach(item => {
    item.total_price = parseFloat((item.quantity * item.unit_price).toFixed(10))
  })
}, { deep: true })

// Watch stock request change
watch(() => form.value.stock_request_id, newId => {
  if (!newId) {
    currentWarehouseName.value = ''
    form.value.warehouse_id = null
    return
  }
  const selectedSR = stockRequests.value.find(sr => sr.id === Number(newId))
  currentWarehouseName.value = selectedSR?.warehouse_name || ''
  form.value.warehouse_id = selectedSR?.warehouse_id || null
})

// Open modal selection
const openItemsSelection = async () => {
  if (form.value.stock_request_id) {
    await openStockRequestItemsModal()
  } else {
    await openProductsModal()
  }
  $(itemsModal.value).modal('show')
}

const openStockRequestItemsModal = async () => {
  try {
    if (!form.value.stock_request_id) 
      return showAlert('Error', 'Please select a Stock Request.', 'danger')

    const { data } = await axios.get(
      `/api/inventory/stock-issues/get-stock-request-items/${form.value.stock_request_id}`,
      { 
        params: { 
          cutoff_date: form.value.transaction_date, 
          warehouse_id: form.value.warehouse_id // pass warehouse_id
        } 
      }
    )

    modalItems.value = (data.items ?? []).map(i => ({ ...i, selected: false }))
    modalTitle.value = 'Select Stock Request Items'
    await nextTick()
    initModalDataTable()
  } catch {
    showAlert('Error', 'Failed to fetch stock request items.', 'danger')
  }
}

const openProductsModal = async () => {
  modalTitle.value = 'Select Products';
  await nextTick();
  
  // Re-initialize DataTable to send warehouse_id and transaction_date
  initModalDataTable()

  $(itemsModal.value).modal('show')
}




// Close modal
const closeItemsModal = () => $(itemsModal.value).modal('hide')

// Add selected items (allow duplicates)
const addSelectedItems = async () => {
  const table = $(itemsModal.value).find('table').DataTable();
  const selectedRows = [];

  table.rows().every(function () {
    const rowNode = this.node();
    const $checkbox = $(rowNode).find('.select-product');
    if ($checkbox.is(':checked')) {
      selectedRows.push(this.data());
    }
  });

  const source = form.value.stock_request_id ? 'stock_request' : 'product';
  selectedRows.forEach(item => form.value.items.push(mapItem(item, source)));

  await nextTick();
  initRowSelect2();
  closeItemsModal();
}

// Datepicker
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

// Initialize Select2 for table rows
const initRowSelect2 = () => {
  document.querySelectorAll('.campusSelect').forEach(el => {
    initSelect2(el, { placeholder: 'Select Campus', width: '100%' }, val => {
      const index = Array.from(document.querySelectorAll('.campusSelect')).indexOf(el)
      if (index !== -1) form.value.items[index].campus_id = val
    })
  })
  document.querySelectorAll('.departmentSelect').forEach(el => {
    initSelect2(el, { placeholder: 'Select Department', width: '100%' }, val => {
      const index = Array.from(document.querySelectorAll('.departmentSelect')).indexOf(el)
      if (index !== -1) form.value.items[index].department_id = val
    })
  })
}

// Submit
const submitForm = async () => {
  if (isSubmitting.value || !form.value.items.length) return
  isSubmitting.value = true
  try {
    const payload = {
      stock_request_id: form.value.stock_request_id,
      warehouse_id: form.value.warehouse_id,
      transaction_date: form.value.transaction_date,
      transaction_type: form.value.transaction_type,
      account_code: form.value.account_code,
      reference_no: form.value.reference_no || null,
      requested_by: form.value.requested_by || null,
      remarks: form.value.remarks,
      items: form.value.items.map(item => ({
        id: item.id,
        stock_request_item_id: item.stock_request_item_id,
        product_id: item.product_id,
        quantity: parseFloat(item.quantity),
        unit_price: parseFloat(item.unit_price),
        total_price: parseFloat((item.quantity * item.unit_price).toFixed(10)),
        remarks: item.remarks,
        campus_id: item.campus_id,
        department_id: item.department_id
      }))
    }
    const url = isEditMode.value ? `/api/inventory/stock-issues/${props.initialData.id}` : '/api/inventory/stock-issues'
    const method = isEditMode.value ? 'put' : 'post'
    await axios[method](url, payload)
    await showAlert('Success', 'Stock issue saved successfully.', 'success')
    emit('submitted')
    goToIndex()
  } catch {
    showAlert('Error', 'Failed to save stock issue.', 'danger')
  } finally {
    isSubmitting.value = false
  }
}

const initModalDataTable = () => {
  nextTick(() => {
    const table = $(itemsModal.value).find('table');

    // Destroy previous instance if exists
    if ($.fn.DataTable.isDataTable(table)) {
      table.DataTable().destroy();
    }

    table.DataTable({
      serverSide: true,
      processing: true,
      responsive: true,
      autoWidth: false,
      ajax: {
        url: '/api/inventory/stock-issues/get-products',
        type: 'GET',
        data: function (d) {
        d.warehouse_id = form.value.warehouse_id
        d.cutoff_date = form.value.transaction_date // pass transaction date
        }
      },
      columns: [
        {
          data: 'id',
          render: function (data) {
            return `<div class="custom-control custom-checkbox">
                      <input type="checkbox" class="custom-control-input select-product" id="select-item-${data}">
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
        { data: 'stock_on_hand', render: $.fn.dataTable.render.number(',', '.', 0, '') },
        { data: 'average_price', render: $.fn.dataTable.render.number(',', '.', 2, '') }
      ],
      paging: true,
      lengthChange: true,
      pageLength: 10,           // default rows
      searching: true,
      info: true,               // ✅ show footer info
      ordering: true,
      order: [[1, 'asc']],      // default sort
      language: {
        info: "Showing _START_ to _END_ of _TOTAL_ entries",
        infoEmpty: "No entries to show",
        infoFiltered: "(filtered from _MAX_ total entries)",
        lengthMenu: "Show _MENU_ entries"
      }
    });
  });
}


// Lifecycle
onMounted(async () => {
  await fetchInitialData()
  await initDatepicker()

  // Stock Request select
  if (stockRequestSelect.value) {
    initSelect2(stockRequestSelect.value, {
      placeholder: 'Select Stock Request',
      width: '100%',
      allowClear: true // this enables the clear button
    }, val => {
      form.value.stock_request_id = val || null // clear sets null
    })
  }
    if (transactionTypeSelect.value) {
    initSelect2(transactionTypeSelect.value, {
      placeholder: 'Select Transaction Type',
      width: '100%',
      allowClear: true,
      data: transactionTypes.value.map(tt => ({ id: tt.value, text: tt.text }))
    }, val => {
      form.value.transaction_type = val || ''
    })
  }

  // Other selects
  await nextTick()
  const requestedBy = document.querySelector('#requestedBySelect')
  const warehouse = document.querySelector('#warehouseSelect')
  if (requestedBy) initSelect2(requestedBy, { placeholder: 'Select User', width: '100%' }, val => form.value.requested_by = val)
  if (warehouse) initSelect2(warehouse, { placeholder: 'Select Warehouse', width: '100%' }, val => form.value.warehouse_id = val)

  // Edit mode
  if (isEditMode.value) {
    Object.assign(form.value, { ...props.initialData, items: JSON.parse(JSON.stringify(props.initialData.items)) })
    currentWarehouseName.value = props.initialData.warehouse_name || ''

    // Set Select2 values
    if (stockRequestSelect.value) {
      $(stockRequestSelect.value).val(form.value.stock_request_id).trigger('change.select2')
    }
    if (requestedBy) {
      $(requestedBy).val(form.value.requested_by).trigger('change.select2')
    }
    if (warehouse) {
      $(warehouse).val(form.value.warehouse_id).trigger('change.select2')
    }

    if (form.value.transaction_date) $('#transaction_date').datepicker('setDate', form.value.transaction_date)
    await nextTick()
    initRowSelect2()
  }
})
</script>

<style scoped>

</style>
