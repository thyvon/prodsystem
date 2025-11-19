<template>
  <div class="container-fluid">
    <form @submit.prevent="submitForm">
      <div class="card border mb-0 shadow">
        <div class="card-header d-flex justify-content-between align-items-center bg-light py-2">
          <h4 class="mb-0">{{ isEditMode ? 'Edit Stock In' : 'Create Stock In' }}</h4>
          <button type="button" class="btn btn-outline-primary btn-sm" @click="goToIndex"><i class="fal fa-backward"></i></button>
        </div>

        <div class="card-body">
          <!-- Header fields -->
          <div class="border rounded p-3 mb-4">
            <div class="form-row">
              <div class="form-group col-md-3">
                <label>Transaction Date *</label>
                <input id="transaction_date" v-model="form.transaction_date" type="text" class="form-control" required />
              </div>

              <div class="form-group col-md-3">
                <label>Transaction Type *</label>
                <select ref="transactionTypeSelect" v-model="form.transaction_type" class="form-control">
                  <option value=""></option>
                </select>
              </div>

              <div class="form-group col-md-3">
                <label>Reference No</label>
                <input v-model="form.reference_no" type="text" class="form-control" />
              </div>

              <div class="form-group col-md-3">
                <label>Invoice No</label>
                <input v-model="form.invoice_no" type="text" class="form-control" />
              </div>
            </div>

            <div class="form-row">
              <div class="form-group col-md-4">
                <label>Warehouse *</label>
                <select id="warehouseSelect" v-model="form.warehouse_id" class="form-control">
                  <option value="">Select Warehouse</option>
                  <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.text }}</option>
                </select>
              </div>

              <div class="form-group col-md-4">
                <label>Supplier *</label>
                <select id="supplierSelect" v-model="form.supplier_id" class="form-control">
                  <option value="">Select Supplier</option>
                  <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.text }}</option>
                </select>
              </div>

              <div class="form-group col-md-4">
                <label>Payment Terms</label>
                <select ref="paymentTermsSelect" v-model="form.payment_terms" class="form-control">
                  <option value=""></option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label>Remarks</label>
              <textarea v-model="form.remarks" class="form-control" rows="2"></textarea>
            </div>
          </div>

          <!-- Items -->
          <div class="border rounded p-3 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h5>Items</h5>
              <button type="button" class="btn btn-sm btn-success" @click="openProductsModal">Add Items</button>
            </div>

            <div class="table-responsive">
              <table class="table table-bordered table-striped mb-0">
                <thead>
                  <tr>
                    <th style="min-width:100px">Code</th>
                    <th style="min-width:350px">Description</th>
                    <th style="min-width:60px">UoM</th>
                    <th style="min-width:100px">Qty</th>
                    <th style="min-width:120px">Unit Price</th>
                    <th style="min-width:130px">VAT</th>
                    <th style="min-width:120px">Discount</th>
                    <th style="min-width:120px">Delivery Fee</th>
                    <th style="min-width:120px">Total Price</th>
                    <th style="min-width:150px">Remarks</th>
                    <th style="min-width:80px">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, i) in form.items" :key="item.id ?? i">
                    <td>{{ item.product_code }}</td>
                    <td>{{ item.description }}</td>
                    <td>{{ item.unit_name }}</td>
                    <td><input type="number" v-model.number="item.quantity" class="form-control" min="0.0000000001" step="0.0000000001" /></td>
                    <td><input type="number" v-model.number="item.unit_price" class="form-control" min="0" step="0.0000000001" /></td>
                    <td><input type="number" v-model.number="item.vat" class="form-control" min="0" step="0.0000000001" /></td>
                    <td><input type="number" v-model.number="item.discount" class="form-control" min="0" step="0.0000000001" /></td>
                    <td><input type="number" v-model.number="item.delivery_fee" class="form-control" min="0" step="0.0000000001" /></td>
                    <td><input type="number" :value="computeTotal(item)" class="form-control" readonly /></td>
                    <td><textarea v-model="item.remarks" class="form-control" rows="1"></textarea></td>
                    <td><button type="button" class="btn btn-sm btn-danger" @click="removeItem(i)"><i class="fal fa-trash"></i></button></td>
                  </tr>
                  <tr v-if="form.items.length === 0"><td colspan="11" class="text-center text-muted">No items added</td></tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="text-right">
            <button type="submit" class="btn btn-primary btn-sm" :disabled="isSubmitting || form.items.length === 0">
              <span v-if="isSubmitting" class="spinner-border spinner-border-sm mr-1"></span>
              {{ isEditMode ? 'Update' : 'Create' }}
            </button>
            <button type="button" class="btn btn-secondary btn-sm" @click="goToIndex">Cancel</button>
          </div>
        </div>
      </div>
    </form>

    <!-- Products modal -->
    <div ref="itemsModal" class="modal fade" tabindex="-1" role="dialog">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <div class="modal-header"><h5 class="modal-title">{{ modalTitle }}</h5><button type="button" class="close" @click="closeItemsModal">&times;</button></div>
          <div class="modal-body"><table ref="modalItemsTable" class="table table-bordered table-sm"><thead><tr><th>Select</th><th>Code</th><th>Description</th><th>UoM</th><th>Qty On Hand</th><th>Unit Price</th></tr></thead><tbody></tbody></table></div>
          <div class="modal-footer"><button class="btn btn-secondary btn-sm" @click="closeItemsModal">Cancel</button><button class="btn btn-success btn-sm" @click="addSelectedItems">Add Selected</button></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue'
import axios from 'axios'
import { showAlert } from '@/Utils/bootbox'
import { initSelect2 } from '@/Utils/select2'

const props = defineProps({ initialId: { type: [String, Number], default: null } })
const emit = defineEmits(['submitted'])

const isEditMode = ref(false)
const isSubmitting = ref(false)
const modalTitle = ref('Select Products')

const form = ref({
  id: null, transaction_date: '', reference_no: '', transaction_type: '', invoice_no: '',
  payment_terms: '', supplier_id: null, warehouse_id: null, remarks: '', items: []
})

const suppliers = ref([])     // {id,text}
const warehouses = ref([])    // {id,text}
const transactionTypes = ref([{ value: 'Purchase', text: 'Purchase' }, { value: 'Transfer', text: 'Transfer' }])
const paymentTerms = ref([
  { value: 'NonCredit', text: 'Non-Credit' },
  { value: 'Credit1week', text: 'Credit 1 Week' },
  { value: 'Credit2weeks', text: 'Credit 2 Weeks' },
  { value: 'Credit1month', text: 'Credit 1 Month' }
])

const transactionTypeSelect = ref(null)
const paymentTermsSelect = ref(null)
const itemsModal = ref(null)

const goToIndex = () => window.location.href = '/inventory/stock-ins'
const removeItem = i => form.value.items.splice(i, 1)
const computeTotal = it => (Number(it.quantity || 0) * Number(it.unit_price || 0) + Number(it.vat || 0) - Number(it.discount || 0) + Number(it.delivery_fee || 0)).toFixed(10)

// initial lists
const fetchInitialData = async () => {
  try {
    const [{ data: s }, { data: w }] = await Promise.all([
      axios.get('/api/inventory/stock-ins/get-suppliers'),
      axios.get('/api/inventory/stock-ins/get-warehouses')
    ])
    suppliers.value = (s.data ?? s) || []
    warehouses.value = (w.data ?? w) || []
  } catch {
    showAlert('Error', 'Failed to fetch suppliers or warehouses.', 'danger')
  }
}

// load edit payload (server should return stock_in, items and optionally lists)
const loadEditData = async (id) => {
  try {
    const { data: payload } = await axios.get(`/api/inventory/stock-ins/${id}/edit`)
    if (!payload?.stock_in) return
    isEditMode.value = true
    form.value = { ...form.value, ...payload.stock_in }
    form.value.items = payload.items ?? []

    if (payload.suppliers) suppliers.value = payload.suppliers
    if (payload.warehouses) warehouses.value = payload.warehouses
    if (payload.transaction_types) transactionTypes.value = payload.transaction_types.map(t => ({ value: t.id, text: t.text }))
    if (payload.payment_terms) paymentTerms.value = payload.payment_terms.map(p => ({ value: p.id, text: p.text }))

    // ensure the selected supplier/warehouse/terms exist in lists (so selects show label)
    if (form.value.supplier_id && !suppliers.value.find(s => s.id == form.value.supplier_id)) suppliers.value.unshift({ id: form.value.supplier_id, text: payload.stock_in.supplier_name ?? `#${form.value.supplier_id}` })
    if (form.value.warehouse_id && !warehouses.value.find(w => w.id == form.value.warehouse_id)) warehouses.value.unshift({ id: form.value.warehouse_id, text: payload.stock_in.warehouse_name ?? `#${form.value.warehouse_id}` })
    if (form.value.transaction_type && !transactionTypes.value.find(t => t.value == form.value.transaction_type)) transactionTypes.value.unshift({ value: form.value.transaction_type, text: form.value.transaction_type })
    if (form.value.payment_terms && !paymentTerms.value.find(p => p.value == form.value.payment_terms)) paymentTerms.value.unshift({ value: form.value.payment_terms, text: form.value.payment_terms })

    await nextTick()
    // set UI widgets
    $('#transaction_date').datepicker('setDate', form.value.transaction_date)
    const supplierEl = document.querySelector('#supplierSelect'); if (supplierEl) $(supplierEl).val(form.value.supplier_id).trigger('change')
    const warehouseEl = document.querySelector('#warehouseSelect'); if (warehouseEl) $(warehouseEl).val(form.value.warehouse_id).trigger('change')
    if (transactionTypeSelect.value) $(transactionTypeSelect.value).val(form.value.transaction_type).trigger('change')
    if (paymentTermsSelect.value) $(paymentTermsSelect.value).val(form.value.payment_terms).trigger('change')
  } catch {
    showAlert('Error', 'Failed to load edit data.', 'danger')
  }
}

// modal DataTable for products
const initModalDataTable = () => {
  nextTick(() => {
    const tableEl = $(itemsModal.value).find('table')
    if ($.fn.DataTable.isDataTable(tableEl)) tableEl.DataTable().clear().destroy()
    tableEl.DataTable({
      serverSide: true,
      processing: true,
      ajax: {
        url: '/api/inventory/stock-ins/get-products',
        type: 'GET',
        data: function (d) {
          // attach current warehouse id and return the params object
          d.warehouse_id = form.value.warehouse_id
          return d
        },
        error: function (xhr, status, err) {
          console.error('Products DataTable AJAX error:', status, err, xhr)
          showAlert('Error', 'Failed to load products. Check console/network for details.', 'danger')
        }
      },
      columns: [
        { data: 'id', render: id => `<div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input select-product" id="select-item-${id}"><label class="custom-control-label" for="select-item-${id}"></label></div>`, orderable: false },
        { data: 'item_code' },
        { data: null, render: (d, t, r) => r.description || '' },
        { data: 'unit_name' },
        { data: 'stock_on_hand', render: $.fn.dataTable.render.number(',', '.', 0, '') },
        { data: 'average_price', render: $.fn.dataTable.render.number(',', '.', 2, '') }
      ],
      pageLength: 10, order: [[1, 'asc']]
    })
  })
}

const openProductsModal = async () => { modalTitle.value = 'Select Products'; await nextTick(); initModalDataTable(); $(itemsModal.value).modal('show') }
const closeItemsModal = () => $(itemsModal.value).modal('hide')

const addSelectedItems = () => {
  const table = $(itemsModal.value).find('table').DataTable()
  const toAdd = []
  table.rows().every(function() {
    const node = this.node(); if ($(node).find('.select-product').is(':checked')) toAdd.push(this.data())
  })
  toAdd.forEach(p => form.value.items.push({
    id: null, product_id: p.id, product_code: p.item_code, description: p.description, unit_name: p.unit_name,
    quantity: 1, unit_price: Number(p.average_price) || 0, vat: 0, discount: 0, delivery_fee: 0, remarks: ''
  }))
  table.rows().every(function(){ $(this.node()).find('.select-product').prop('checked', false) })
  closeItemsModal()
}

// submit
const submitForm = async () => {
  if (isSubmitting.value || form.value.items.length === 0) return
  isSubmitting.value = true
  try {
    const url = isEditMode.value ? `/api/inventory/stock-ins/${form.value.id}` : '/api/inventory/stock-ins'
    const method = isEditMode.value ? 'put' : 'post'
    const resp = await axios[method](url, form.value)
    await showAlert('Success', resp.data.message ?? 'Saved.', 'success')
    emit('submitted', resp.data); goToIndex()
  } catch (err) {
    const msg = err.response?.data?.message ?? err.response?.data?.error ?? err.message ?? 'Failed to save'
    showAlert('Error', msg, 'danger')
  } finally { isSubmitting.value = false }
}

const initWidgets = () => {
  $('#transaction_date').datepicker({ format: 'yyyy-mm-dd', autoclose: true, todayHighlight: true })
    .on('changeDate', () => form.value.transaction_date = $('#transaction_date').val())

  const supplierEl = document.querySelector('#supplierSelect'); if (supplierEl) initSelect2(supplierEl, { placeholder: 'Select Supplier', width: '100%' }, val => form.value.supplier_id = val)
  const warehouseEl = document.querySelector('#warehouseSelect'); if (warehouseEl) initSelect2(warehouseEl, { placeholder: 'Select Warehouse', width: '100%' }, val => form.value.warehouse_id = val)

  if (transactionTypeSelect.value) initSelect2(transactionTypeSelect.value, { placeholder: 'Select Transaction Type', width: '100%', allowClear: true, data: transactionTypes.value.map(t => ({ id: t.value, text: t.text })) }, val => form.value.transaction_type = val || '')
  if (paymentTermsSelect.value) initSelect2(paymentTermsSelect.value, { placeholder: 'Select Payment Terms', width: '100%', allowClear: true, data: paymentTerms.value.map(p => ({ id: p.value, text: p.text })) }, val => form.value.payment_terms = val || '')
}

onMounted(async () => {
  await fetchInitialData()
  initWidgets()
  if (props.initialId) await loadEditData(props.initialId)
})
</script>

<style scoped>
/* minimal helpers */
.table td, .table th { vertical-align: middle; }
</style>