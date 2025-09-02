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
              <div class="form-group col-md-4">
                <label for="transaction_date" class="font-weight-bold">Issue Date <span class="text-danger">*</span></label>
                <input v-model="form.transaction_date" type="text" class="form-control datepicker" id="transaction_date" required />
              </div>

              <div class="form-group col-md-4">
                <label for="stock_request_id" class="font-weight-bold">Stock Request <span class="text-danger">*</span></label>
                <select ref="stockRequestSelect" v-model="form.stock_request_id" class="form-control" id="stock_request_id" required>
                  <option value="">Select Stock Request</option>
                  <option v-for="sr in stockRequests" :key="sr.id" :value="sr.id">{{ sr.request_number }}</option>
                </select>
              </div>

              <div class="form-group col-md-4">
                <label class="font-weight-bold">Warehouse</label>
                <input v-model="currentWarehouseName" type="text" class="form-control" readonly />
              </div>
            </div>

            <div class="form-group">
              <label for="remarks" class="font-weight-bold">Remarks</label>
              <textarea v-model="form.remarks" class="form-control" id="remarks" rows="2"></textarea>
            </div>
          </div>

          <!-- Issue Items Table -->
          <div class="border rounded p-3 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h5 class="font-weight-bold text-primary">📦 Issue Items <span class="text-danger">*</span></h5>
              <button type="button" class="btn btn-sm btn-success" @click="openItemsModal">Add Items</button>
            </div>
            <div class="table-responsive">
              <table class="table table-bordered table-sm table-hover">
                <thead class="thead-light">
                  <tr>
                    <th>Code</th>
                    <th>Description</th>
                    <th>UoM</th>
                    <th>Qty On Hand</th>
                    <th>Issue Qty</th>
                    <th>Unit Price</th>
                    <th>Total Price</th>
                    <th>Remarks</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, index) in form.items" :key="item.id || index">
                    <td>{{ item.product_code }}</td>
                    <td>{{ item.product_name }} {{ item.description }}</td>
                    <td>{{ item.unit_name }}</td>
                    <td><input type="number" class="form-control" :value="item.stock_on_hand" readonly /></td>
                    <td><input type="number" class="form-control" v-model.number="item.quantity" min="0.0001" step="0.0001" /></td>
                    <td><input type="number" class="form-control" :value="item.unit_price" readonly /></td>
                    <td><input type="number" class="form-control" :value="(item.quantity * item.unit_price).toFixed(4)" readonly /></td>
                    <td><textarea class="form-control" rows="1" v-model="item.remarks"></textarea></td>
                    <td>
                      <button type="button" class="btn btn-sm btn-danger" @click="removeItem(index)">
                        <i class="fal fa-trash"></i>
                      </button>
                    </td>
                  </tr>
                  <tr v-if="form.items.length === 0">
                    <td colspan="9" class="text-center text-muted">No items added</td>
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

    <!-- Inline Modal -->
    <div v-if="isItemsModalOpen" class="modal fade show" tabindex="-1" style="display: block;" role="dialog">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Select Stock Request Items</h5>
            <button type="button" class="close" @click="closeItemsModal">&times;</button>
          </div>
          <div class="modal-body">
            <table class="table table-bordered table-sm">
              <thead>
                <tr>
                  <th>Select</th>
                  <th>Code</th>
                  <th>Description</th>
                  <th>Qty On Hand</th>
                  <th>Unit Price</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in modalItems" :key="item.id">
                  <td>
                    <div class="custom-control custom-checkbox">
                      <input
                        type="checkbox"
                        class="custom-control-input"
                        :id="'select-item-' + item.id"
                        v-model="item.selected"
                      />
                      <label class="custom-control-label" :for="'select-item-' + item.id"></label>
                    </div>
                  </td>
                  <td>{{ item.item_code }}</td>
                  <td>{{ item.product_name }} {{ item.description }}</td>
                  <td>{{ item.stock_on_hand }}</td>
                  <td>{{ item.unit_price }}</td>
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
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import axios from 'axios'
import { showAlert } from '@/Utils/bootbox'
import { initSelect2 } from '@/Utils/select2'

const props = defineProps({ initialData: { type: Object, default: () => ({}) }})
const emit = defineEmits(['submitted'])

const isEditMode = computed(() => !!props.initialData.id)
const isSubmitting = ref(false)
const isItemsModalOpen = ref(false)

const stockRequests = ref([])
const stockRequestSelect = ref(null)
const currentWarehouseName = ref('')

const form = ref({
  stock_request_id: null,
  transaction_date: '',
  remarks: '',
  items: []
})

const stockRequestItemsCache = ref({})
const modalItems = ref([])

const mapItem = item => ({
  id: null,
  stock_request_item_id: item.id,
  product_id: item.product_id,
  product_code: item.item_code,
  product_name: item.product_name,
  description: item.description || '',
  unit_name: item.unit_name,
  quantity: parseFloat(item.quantity) || 1,
  unit_price: parseFloat(item.unit_price) || 0,
  stock_on_hand: parseFloat(item.stock_on_hand) || 0,
  total_price: parseFloat((item.quantity * item.unit_price).toFixed(4)),
  remarks: ''
})

const goToIndex = () => (window.location.href = '/inventory/stock-issues')
const removeItem = index => form.value.items.splice(index, 1)

// Fetch stock requests
const fetchStockRequests = async () => {
  try {
    const { data } = await axios.get('/api/inventory/stock-issues/get-stock-requests')
    stockRequests.value = Array.isArray(data) ? data : data.data
  } catch { showAlert('Error', 'Failed to fetch stock requests.', 'danger') }
}

// Modal Methods
const openItemsModal = async () => {
  if (!form.value.stock_request_id) return showAlert('Error', 'Please select a Stock Request.', 'danger')
  try {
    const { data } = await axios.get(`/api/inventory/stock-issues/get-stock-request-items/${form.value.stock_request_id}`, { params: { cutoff_date: form.value.transaction_date } })
    modalItems.value = (data.items ?? []).map(i => ({ ...i, selected: false }))
    isItemsModalOpen.value = true
  } catch { showAlert('Error', 'Failed to fetch items.', 'danger') }
}

const closeItemsModal = () => isItemsModalOpen.value = false

const addSelectedItems = () => {
  const duplicates = []

  modalItems.value.forEach(item => {
    if (!item.selected) return
    const exists = form.value.items.some(i => i.stock_request_item_id === item.id)
    if (!exists) form.value.items.push(mapItem(item))
    else duplicates.push(item.item_code || item.product_name)
  })

  if (duplicates.length) showAlert('Warning', `Skipped duplicates: ${duplicates.join(', ')}`, 'warning')

  stockRequestItemsCache.value[form.value.stock_request_id] = JSON.parse(JSON.stringify(form.value.items))
  closeItemsModal()
}

// Datepicker
const initDatepicker = async () => {
  await nextTick()
  $('#transaction_date').datepicker({
    format: 'yyyy-mm-dd',
    autoclose: true,
    todayHighlight: true,
    orientation: 'bottom left'
  }).on('changeDate', () => form.value.transaction_date = $('#transaction_date').val())
}

// Watchers
watch(() => form.value.stock_request_id, async newId => {
  if (!newId) {
    form.value.items = []
    currentWarehouseName.value = ''
    return
  }

  const selectedSR = stockRequests.value.find(sr => sr.id === Number(newId))
  currentWarehouseName.value = selectedSR?.warehouse_name || ''

  if (stockRequestItemsCache.value[newId]) {
    form.value.items = JSON.parse(JSON.stringify(stockRequestItemsCache.value[newId]))
    return
  }

  try {
    const { data } = await axios.get(`/api/inventory/stock-issues/get-stock-request-items/${newId}`, { params: { cutoff_date: form.value.transaction_date } })
    form.value.items = (data.items ?? []).map(mapItem)
    stockRequestItemsCache.value[newId] = JSON.parse(JSON.stringify(form.value.items))
  } catch { showAlert('Error', 'Failed to fetch stock request items.', 'danger') }
})

watch([() => form.value.stock_request_id, () => form.value.transaction_date], async ([newId, newDate]) => {
  if (!newId || !newDate) return
  try {
    const { data } = await axios.get(`/api/inventory/stock-issues/get-stock-request-items/${newId}`, { params: { cutoff_date: newDate } })
    const latestItems = (data.items ?? []).map(mapItem)

    form.value.items.forEach(item => {
      const updated = latestItems.find(i => i.stock_request_item_id === item.stock_request_item_id)
      if (updated) {
        item.stock_on_hand = updated.stock_on_hand
        item.unit_price = updated.unit_price
        item.total_price = parseFloat((item.quantity * item.unit_price).toFixed(4))
      }
    })
  } catch { showAlert('Error', 'Failed to refresh stock and price.', 'danger') }
})

// Submit
const submitForm = async () => {
  if (isSubmitting.value || !form.value.stock_request_id || !form.value.items.length) return
  isSubmitting.value = true

  try {
    const payload = {
      stock_request_id: form.value.stock_request_id,
      transaction_date: form.value.transaction_date,
      remarks: form.value.remarks,
      items: form.value.items.map(item => ({
        id: item.id,
        stock_request_item_id: item.stock_request_item_id,
        product_id: item.product_id,
        quantity: parseFloat(item.quantity),
        unit_price: parseFloat(item.unit_price),
        total_price: parseFloat((item.quantity * item.unit_price).toFixed(4)),
        remarks: item.remarks
      }))
    }

    const url = isEditMode.value ? `/api/inventory/stock-issues/${props.initialData.id}` : '/api/inventory/stock-issues'
    const method = isEditMode.value ? 'put' : 'post'
    await axios[method](url, payload)

    await showAlert('Success', 'Stock issue saved successfully.', 'success')
    emit('submitted')
    goToIndex()
  } catch { showAlert('Error', 'Failed to save stock issue.', 'danger') }
  finally { isSubmitting.value = false }
}

// Lifecycle
onMounted(async () => {
  await fetchStockRequests()
  await initDatepicker()

  if (stockRequestSelect.value) {
    initSelect2(stockRequestSelect.value, { placeholder: 'Select Stock Request', width: '100%' }, val => form.value.stock_request_id = val)
  }

  if (isEditMode.value) {
    form.value.stock_request_id = props.initialData.stock_request_id
    form.value.transaction_date = props.initialData.transaction_date
    form.value.remarks = props.initialData.remarks
    form.value.items = JSON.parse(JSON.stringify(props.initialData.items))
    stockRequestItemsCache.value[props.initialData.stock_request_id] = JSON.parse(JSON.stringify(form.value.items))
    currentWarehouseName.value = props.initialData.warehouse_name || ''

    if (stockRequestSelect.value) $(stockRequestSelect.value).val(form.value.stock_request_id).trigger('change.select2')
    if (form.value.transaction_date) $('#transaction_date').datepicker('setDate', form.value.transaction_date)
  }
})
</script>
