<template>
  <div class="container-fluid">
    <form @submit.prevent="submitForm">

      <!-- Header -->
      <div class="card border mb-0 shadow">
        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
          <h4 class="mb-0 font-weight-bold">{{ isEditMode ? 'Edit Stock In' : 'Create Stock In' }}</h4>
          <button type="button" class="btn btn-outline-primary btn-sm" @click="goToIndex">
            <i class="fal fa-backward"></i>
          </button>
        </div>

        <!-- Body -->
        <div class="card-body">

          <!-- Stock In Header -->
          <div class="border rounded p-3 mb-4">
            <h5 class="font-weight-bold mb-3 text-primary">📋 Stock In Details</h5>

            <div class="form-row">
              <div class="form-group col-md-3">
                <label class="font-weight-bold">Transaction Date <span class="text-danger">*</span></label>
                <input id="transaction_date" v-model="form.transaction_date" type="text" class="form-control" required />
              </div>

              <div class="form-group col-md-3">
                <label class="font-weight-bold">Transaction Type <span class="text-danger">*</span></label>
                <input v-model="form.transaction_type" type="text" class="form-control" required />
              </div>

              <div class="form-group col-md-3">
                <label class="font-weight-bold">Reference No</label>
                <input v-model="form.reference_no" type="text" class="form-control" placeholder="Auto-generated if empty" />
              </div>

              <div class="form-group col-md-3">
                <label class="font-weight-bold">Invoice No</label>
                <input v-model="form.invoice_no" type="text" class="form-control" />
              </div>
            </div>

            <div class="form-row">
              <div class="form-group col-md-6">
                <label class="font-weight-bold">Supplier <span class="text-danger">*</span></label>
                <select id="supplierSelect" v-model="form.supplier_id" class="form-control">
                  <option value="">Select Supplier</option>
                  <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
              </div>

              <div class="form-group col-md-6">
                <label class="font-weight-bold">Warehouse <span class="text-danger">*</span></label>
                <select id="warehouseSelect" v-model="form.warehouse_id" class="form-control">
                  <option value="">Select Warehouse</option>
                  <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label class="font-weight-bold">Payment Terms</label>
              <input v-model="form.payment_terms" type="text" class="form-control" />
            </div>

            <div class="form-group">
              <label class="font-weight-bold">Remarks</label>
              <textarea v-model="form.remarks" class="form-control" rows="2"></textarea>
            </div>
          </div>

          <!-- Items Table -->
          <div class="border rounded p-3 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h5 class="font-weight-bold text-primary">📦 Items <span class="text-danger">*</span></h5>
              <button type="button" class="btn btn-sm btn-success" @click="openProductsModal">Add Items</button>
            </div>

            <div class="table-responsive">
              <table class="table table-bordered table-striped mb-0">
                <thead class="thead-light">
                  <tr>
                    <th>Code</th>
                    <th>Description</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>VAT</th>
                    <th>Discount</th>
                    <th>Delivery Fee</th>
                    <th>Total Price</th>
                    <th>Remarks</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, index) in form.items" :key="item.id || index">
                    <td>{{ item.product_code }}</td>
                    <td>{{ item.description }}</td>
                    <td><input type="number" class="form-control" v-model.number="item.quantity" min="0.0000000001" step="0.0000000001" /></td>
                    <td><input type="number" class="form-control" v-model.number="item.unit_price" min="0" step="0.0000000001" /></td>
                    <td><input type="number" class="form-control" v-model.number="item.vat" min="0" step="0.0000000001" /></td>
                    <td><input type="number" class="form-control" v-model.number="item.discount" min="0" step="0.0000000001" /></td>
                    <td><input type="number" class="form-control" v-model.number="item.delivery_fee" min="0" step="0.0000000001" /></td>
                    <td><input type="number" class="form-control" :value="computeTotal(item)" readonly /></td>
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

    <!-- Products Modal -->
    <div ref="itemsModal" class="modal fade" tabindex="-1" role="dialog">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ modalTitle }}</h5>
            <button type="button" class="close" @click="closeItemsModal">&times;</button>
          </div>
          <div class="modal-body">
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
                <tr v-for="product in modalItems" :key="product.id">
                  <td><input type="checkbox" v-model="product.selected" /></td>
                  <td>{{ product.item_code }}</td>
                  <td>{{ product.description }}</td>
                  <td>{{ product.unit_name }}</td>
                  <td>{{ product.stock_on_hand }}</td>
                  <td>{{ product.average_price }}</td>
                </tr>
              </tbody>
            </table>
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
import { ref, onMounted, nextTick } from 'vue'
import axios from 'axios'
import { showAlert } from '@/Utils/bootbox'
import { initSelect2 } from '@/Utils/select2'

const isEditMode = ref(false)
const isSubmitting = ref(false)
const modalTitle = ref('Select Products')

const form = ref({
  transaction_date: '',
  reference_no: '',
  transaction_type: '',
  invoice_no: '',
  payment_terms: '',
  supplier_id: null,
  warehouse_id: null,
  remarks: '',
  items: []
})

const suppliers = ref([])
const warehouses = ref([])
const modalItems = ref([])
const itemsModal = ref(null)

const goToIndex = () => window.location.href = '/inventory/stock-in'
const removeItem = index => form.value.items.splice(index, 1)
const computeTotal = item => (parseFloat(item.quantity || 0) * parseFloat(item.unit_price || 0) + parseFloat(item.vat || 0) - parseFloat(item.discount || 0) + parseFloat(item.delivery_fee || 0)).toFixed(10)

const fetchInitialData = async () => {
  try {
    const [{data: s}, {data: w}] = await Promise.all([
      axios.get('/api/inventory/stock-ins/get-suppliers'),
      axios.get('/api/inventory/stock-ins/get-warehouses')
    ])
    suppliers.value = s.data ?? s
    warehouses.value = w.data ?? w
  } catch {
    showAlert('Error', 'Failed to fetch suppliers or warehouses.', 'danger')
  }
}

const openProductsModal = async () => {
  // Load products if not already loaded
  if (!modalItems.value.length) {
    const { data } = await axios.get('/api/inventory/stock-ins/get-products')
    modalItems.value = data.map(p => ({ ...p, selected: false }))
  }
  $(itemsModal.value).modal('show')
}

const closeItemsModal = () => $(itemsModal.value).modal('hide')

const addSelectedItems = () => {
  modalItems.value.filter(p => p.selected).forEach(p => {
    form.value.items.push({
      id: null,
      product_id: p.id,
      product_code: p.item_code,
      description: p.description,
      unit_name: p.unit_name,
      quantity: 1,
      unit_price: p.average_price,
      vat: 0,
      discount: 0,
      delivery_fee: 0,
      remarks: ''
    })
    p.selected = false
  })
  closeItemsModal()
}

const initDatepicker = () => {
  $('#transaction_date').datepicker({ format: 'yyyy-mm-dd', autoclose: true, todayHighlight: true })
    .on('changeDate', () => form.value.transaction_date = $('#transaction_date').val())
}

const submitForm = async () => {
  if (isSubmitting.value || !form.value.items.length) return
  isSubmitting.value = true
  try {
    const url = isEditMode.value ? `/api/inventory/stock-in/${form.value.id}` : '/api/inventory/stock-in'
    const method = isEditMode.value ? 'put' : 'post'
    await axios[method](url, form.value)
    await showAlert('Success', 'Stock In saved successfully.', 'success')
    goToIndex()
  } catch {
    showAlert('Error', 'Failed to save Stock In.', 'danger')
  } finally {
    isSubmitting.value = false
  }
}

onMounted(async () => {
  await fetchInitialData()
  initDatepicker()

  const supplierEl = document.querySelector('#supplierSelect')
  if (supplierEl) initSelect2(supplierEl, { placeholder: 'Select Supplier', width: '100%' }, val => form.value.supplier_id = val)

  const warehouseEl = document.querySelector('#warehouseSelect')
  if (warehouseEl) initSelect2(warehouseEl, { placeholder: 'Select Warehouse', width: '100%' }, val => form.value.warehouse_id = val)
})
</script>

<style scoped>

</style>
