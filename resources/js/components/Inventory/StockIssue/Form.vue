<template>
  <div class="container-fluid">
    <form @submit.prevent="submitForm">
      <div class="card border mb-0 shadow">
        <!-- Header -->
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
                <input
                  v-model="form.transaction_date_display"
                  type="text"
                  class="form-control datepicker"
                  id="transaction_date"
                  required
                  placeholder="Enter issue date"
                />
              </div>
              <div class="form-group col-md-8">
                <label for="stock_request_id" class="font-weight-bold">Stock Request <span class="text-danger">*</span></label>
                <select
                  ref="stockRequestSelect"
                  v-model="form.stock_request_id"
                  class="form-control"
                  id="stock_request_id"
                  required
                >
                  <option value="">Select Stock Request</option>
                  <option v-for="sr in stockRequests" :key="sr.id" :value="sr.id">
                    {{ sr.request_number }}
                  </option>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group col-md-12">
                <label for="remarks" class="font-weight-bold">Remarks</label>
                <textarea v-model="form.remarks" class="form-control" id="remarks" rows="2"></textarea>
              </div>
            </div>
          </div>

          <!-- Issue Items -->
          <div class="border rounded p-3 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h5 class="font-weight-bold text-primary">📦 Issue Items <span class="text-danger">*</span></h5>
              <button type="button" class="btn btn-sm btn-success" @click="openItemsModal">
                Add Items
              </button>
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
                    <td>{{ item.product_description }} {{ item.description }}</td>
                    <td>{{ item.unit_name }}</td>
                    <td><input type="number" class="form-control" :value="item.stock_on_hand" readonly /></td>
                    <td>
                      <input
                        type="number"
                        class="form-control"
                        v-model.number="item.quantity"
                        min="0.0001"
                        step="0.0001"
                      />
                    </td>
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
            <button type="submit" class="btn btn-primary btn-sm mr-2" :disabled="isSubmitting || form.items.length === 0">
              <span v-if="isSubmitting" class="spinner-border spinner-border-sm mr-1"></span>
              {{ isEditMode ? 'Update' : 'Create' }}
            </button>
            <button type="button" class="btn btn-secondary btn-sm" @click="goToIndex">Cancel</button>
          </div>
        </div>
      </div>
    </form>

    <!-- Modal for selecting items -->
    <div class="modal fade" id="stockRequestItemsModal" tabindex="-1" role="dialog">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Select Items</h5>
            <button type="button" class="close" @click="closeItemsModal">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div class="table-responsive">
              <table class="table table-bordered table-sm table-hover">
                <thead class="thead-light">
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
                      <input type="checkbox" v-model="item.selected" />
                    </td>
                    <td>{{ item.item_code }}</td>
                    <td>{{ item.product_name }} {{ item.description }}</td>
                    <td>{{ item.unit_name }}</td>
                    <td>{{ item.stock_on_hand }}</td>
                    <td>{{ item.unit_price }}</td>
                  </tr>
                  <tr v-if="modalItems.length === 0">
                    <td colspan="6" class="text-center text-muted">No items available</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="closeItemsModal">Close</button>
            <button type="button" class="btn btn-primary" @click="addSelectedItems">Add Selected</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, nextTick, watch } from 'vue'
import axios from 'axios'
import { showAlert } from '@/Utils/bootbox'
import { initSelect2 } from '@/Utils/select2'

const props = defineProps({ initialData: { type: Object, default: () => ({}) } })
const emit = defineEmits(['submitted'])

const isSubmitting = ref(false)
const stockRequests = ref([])
const stockRequestSelect = ref(null)
const isEditMode = computed(() => !!props.initialData?.id)
const originalStockRequestId = ref(props.initialData?.stock_request_id ?? null)

const form = ref({
  stock_request_id: null,
  transaction_date: '',
  transaction_date_display: '',
  remarks: '',
  items: []
})

// Modal data
const modalItems = ref([])

const goToIndex = () => (window.location.href = `/inventory/stock-issues`)
const removeItem = (index) => form.value.items.splice(index, 1)

// Fetch stock requests
const fetchStockRequests = async () => {
  try {
    const response = await axios.get('/api/inventory/stock-issues/get-stock-requests')
    stockRequests.value = Array.isArray(response.data) ? response.data : response.data.data
  } catch (err) {
    console.error('Failed to fetch stock requests:', err)
    showAlert('Error', 'Failed to fetch stock requests.', 'danger')
  }
}

// Open modal and fetch items
const openItemsModal = async () => {
  if (!form.value.stock_request_id) return showAlert('Error', 'Please select a Stock Request.', 'danger')
  try {
    const response = await axios.get(`/api/inventory/stock-issues/get-stock-request-items/${form.value.stock_request_id}`, {
      params: { cutoff_date: form.value.transaction_date }
    })
    modalItems.value = (response.data.items ?? []).map(item => ({ ...item, selected: false }))
    $('#stockRequestItemsModal').modal('show')
  } catch (err) {
    console.error('Failed to fetch items for modal:', err)
    showAlert('Error', 'Failed to fetch items.', 'danger')
  }
}

// Close modal
const closeItemsModal = () => $('#stockRequestItemsModal').modal('hide')

// Add selected items from modal to table
const addSelectedItems = () => {
  modalItems.value.forEach(item => {
    if (item.selected && !form.value.items.find(i => i.stock_request_item_id === item.id)) {
      form.value.items.push({
        id: null,
        stock_request_item_id: item.id,
        product_id: item.product_id,
        product_code: item.item_code,
        product_description: item.product_description,
        description: item.description || '',
        unit_name: item.unit_name,
        quantity: parseFloat(item.quantity) || 1,
        unit_price: parseFloat(item.unit_price) || 0,
        stock_on_hand: parseFloat(item.stock_on_hand) || 0,
        total_price: parseFloat((item.quantity * item.unit_price).toFixed(4)),
        remarks: ''
      })
    }
  })
  closeItemsModal()
}

// Initialize datepicker
const initDatepicker = async () => {
  await nextTick()
  $('#transaction_date').datepicker({
    format: 'M dd, yyyy',
    autoclose: true,
    todayHighlight: true,
    orientation: 'bottom left'
  }).on('changeDate', (e) => {
    if (e.date) {
      const date = new Date(e.date)
      form.value.transaction_date = `${date.getFullYear()}-${(date.getMonth()+1).toString().padStart(2,'0')}-${date.getDate().toString().padStart(2,'0')}`
      form.value.transaction_date_display = date.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' })
    } else {
      form.value.transaction_date = ''
      form.value.transaction_date_display = ''
    }
  })
}

// Watch stock_request_id to populate items automatically in Create mode
watch(
  () => form.value.stock_request_id,
  async (newVal, oldVal) => {
    if (!isEditMode.value && newVal) {
      try {
        const response = await axios.get(`/api/inventory/stock-issues/get-stock-request-items/${newVal}`, {
          params: { cutoff_date: form.value.transaction_date }
        })
        form.value.items = (response.data.items ?? []).map(item => ({
          id: null,
          stock_request_item_id: item.id,
          product_id: item.product_id,
          product_code: item.item_code,
          product_description: item.product_description,
          description: item.description || '',
          unit_name: item.unit_name,
          quantity: parseFloat(item.quantity) || 1,
          unit_price: parseFloat(item.unit_price) || 0,
          stock_on_hand: parseFloat(item.stock_on_hand) || 0,
          total_price: parseFloat((item.quantity * item.unit_price).toFixed(4)),
          remarks: ''
        }))
      } catch (err) {
        console.error('Failed to fetch stock request items:', err)
        showAlert('Error', 'Failed to populate stock request items.', 'danger')
      }
    }
  }
)

// Submit form
const submitForm = async () => {
  if (isSubmitting.value) return
  if (!form.value.stock_request_id) return showAlert('Error', 'Please select a Stock Request.', 'danger')
  if (!form.value.items.length) return showAlert('Error', 'No items to issue.', 'danger')

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

    const url = isEditMode.value
      ? `/api/inventory/stock-issues/${props.initialData.id}`
      : '/api/inventory/stock-issues'
    const method = isEditMode.value ? 'put' : 'post'

    await axios[method](url, payload)
    await showAlert('Success', 'Stock issue saved successfully.', 'success')
    emit('submitted')
    goToIndex()
  } catch (err) {
    console.error('Failed to save stock issue:', err)
    await showAlert('Error', 'Failed to save stock issue.', 'danger')
  } finally {
    isSubmitting.value = false
  }
}

// Component mounted
onMounted(async () => {
  await fetchStockRequests()
  await initDatepicker()

  if (stockRequestSelect.value) {
    initSelect2(stockRequestSelect.value, { placeholder: 'Select Stock Request', width: '100%' }, value => {
      form.value.stock_request_id = value
    })
  }

  // Prefill edit mode
  if (isEditMode.value) {
    form.value.stock_request_id = props.initialData.stock_request_id
    form.value.transaction_date = props.initialData.transaction_date
    form.value.transaction_date_display = props.initialData.transaction_date_display
    form.value.remarks = props.initialData.remarks

    if (stockRequestSelect.value) {
      $(stockRequestSelect.value).val(form.value.stock_request_id).trigger('change.select2')
    }

    if (form.value.transaction_date) {
      const dateParts = form.value.transaction_date.split('-')
      const dateObj = new Date(dateParts[0], dateParts[1]-1, dateParts[2])
      $('#transaction_date').datepicker('setDate', dateObj)
    }

    form.value.items = props.initialData.items.map(item => ({
      id: item.id,
      stock_request_item_id: item.stock_request_item_id,
      product_id: item.product_id,
      product_code: item.product_code ?? '',
      product_description: item.product_description,
      description: item.variant ?? item.description ?? '',
      unit_name: item.unit ?? item.unit_name ?? '',
      quantity: parseFloat(item.quantity),
      unit_price: parseFloat(item.unit_price),
      stock_on_hand: parseFloat(item.stock_on_hand ?? 0),
      total_price: parseFloat(item.total_price ?? (item.quantity * item.unit_price).toFixed(4)),
      remarks: item.remarks ?? ''
    }))
  }
})
</script>
