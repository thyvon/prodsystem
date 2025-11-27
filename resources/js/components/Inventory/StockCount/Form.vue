<template>
  <div class="container-fluid">
    <form @submit.prevent="submitForm">
      <div class="card border mb-0 shadow">
        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
          <h4 class="mb-0 font-weight-bold">{{ isEditMode ? 'Edit Stock Count' : 'Create Stock Count' }}</h4>
          <button type="button" class="btn btn-outline-primary btn-sm" @click="goToIndex">
            Back
          </button>
        </div>

        <div class="card-body">
          <!-- Stock Count Details -->
          <div class="border rounded p-3 mb-4">
            <h5 class="font-weight-bold mb-3 text-primary">Stock Count Details</h5>
            <div class="form-row">
              <div class="form-group col-md-4">
                <label for="transaction_date" class="font-weight-bold">Count Date <span class="text-danger">*</span></label>
                <input v-model="form.transaction_date" type="text" class="form-control datepicker" id="transaction_date" required />
              </div>
              <div class="form-group col-md-4">
                <label for="warehouse_id" class="font-weight-bold">Warehouse <span class="text-danger">*</span></label>
                <select ref="warehouseSelect" v-model="form.warehouse_id" class="form-control" id="warehouse_id" required>
                  <option value="">Select Warehouse</option>
                  <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">
                    {{ warehouse.name }}
                  </option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label for="remarks" class="font-weight-bold">Remarks</label>
              <textarea v-model="form.remarks" class="form-control" id="remarks" rows="2"></textarea>
            </div>
          </div>

          <!-- Import & Add Items -->
          <div class="border rounded p-3 mb-4">
            <div class="form-row mb-3">
              <div class="form-group col-md-4">
                <label for="import_file" class="font-weight-bold">Import Count Sheet</label>
                <div class="input-group">
                  <input type="file" class="d-none" id="import_file" accept=".xlsx,.xls,.csv" ref="fileInput" @change="handleFileUpload" />
                  <button type="button" class="btn btn-outline-secondary" @click="triggerFileInput">
                    {{ selectedFileName || 'Choose file...' }}
                  </button>
                  <button type="button" class="btn btn-outline-primary ml-2" @click="importFile" :disabled="isImporting">
                    <span v-if="isImporting" class="spinner-border spinner-border-sm mr-1"></span>
                    Import
                  </button>
                  <a class="btn btn-outline-danger ml-2" href="/sampleExcel/stock_counts_sample.xlsx" download>
                    Sample Excel
                  </a>
                </div>
              </div>
              <div class="form-group col-md-8">
                <label for="product_select" class="font-weight-bold">Add Product Manually</label>
                <select ref="productSelect" class="form-control" id="product_select">
                  <option value="">Select Product</option>
                  <option v-for="product in products" :key="product.id" :value="product.id">
                    {{ product.item_code }} - {{ product.product_name }} (On Hand: {{ product.stock_on_hand }})
                  </option>
                </select>
              </div>
            </div>

            <h5 class="font-weight-bold mb-3 text-primary">Counted Items <span class="text-danger">*</span></h5>
            <div class="table-responsive">
              <table class="table table-bordered table-sm table-hover">
                <thead class="thead-light">
                  <tr>
                    <th>Code</th>
                    <th>Description</th>
                    <th>UoM</th>
                    <th>System Qty</th>
                    <th>Counted Qty <span class="text-danger">*</span></th>
                    <th>Variance</th>
                    <th>Remarks</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, index) in form.items" :key="item.product_id || index">
                    <td>{{ item.item_code }}</td>
                    <td>{{ item.product_name }} {{ item.description }}</td>
                    <td>{{ item.unit_name }}</td>
                    <td><input type="number" class="form-control" :value="item.ending_quantity.toFixed(4)" readonly /></td>
                    <td>
                      <input
                        type="number"
                        class="form-control"
                        v-model.number="item.counted_quantity"
                        min="0"
                        step="0.0001"
                        required
                        @input="calculateVariance(index)"
                      />
                    </td>
                    <td>
                      <input
                        type="number"
                        class="form-control"
                        :value="(item.counted_quantity - item.ending_quantity).toFixed(4)"
                        :class="{ 'text-danger': item.counted_quantity < item.ending_quantity, 'text-success': item.counted_quantity > item.ending_quantity }"
                        readonly
                      />
                    </td>
                    <td><textarea class="form-control" rows="1" v-model="item.remarks"></textarea></td>
                    <td>
                      <button type="button" class="btn btn-danger btn-sm" @click="removeItem(index)">
                        Remove
                      </button>
                    </td>
                  </tr>
                  <tr v-if="form.items.length === 0">
                    <td colspan="8" class="text-center text-muted">No items added yet</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Approval Assignments -->
          <div class="border rounded p-3 mb-4">
            <h5 class="font-weight-bold mb-3 text-primary">Approval Assignments</h5>
            <div class="table-responsive">
              <table class="table table-bordered table-sm">
                <thead class="thead-light">
                  <tr>
                    <th>Type</th>
                    <th>Assigned User</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(approval, index) in form.approvals" :key="index">
                    <td>
                      <select v-model="approval.request_type" class="form-control" :disabled="approval.isDefault" required>
                        <option value="initial">Initial</option>
                        <option value="approve">Approve</option>
                      </select>
                    </td>
                    <td>
                      <select v-model="approval.user_id" class="form-control user-select" required>
                        <option value="">Select User</option>
                        <option v-for="user in approval.availableUsers" :key="user.id" :value="user.id">
                          {{ user.name }}
                        </option>
                      </select>
                    </td>
                    <td>
                      <button type="button" class="btn btn-danger btn-sm" @click="removeApproval(index)" :disabled="approval.isDefault">
                        Remove
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm mt-2" @click="addApproval">Add Approval</button>
          </div>

          <!-- Submit Buttons -->
          <div class="text-right">
            <button
              type="submit"
              class="btn btn-primary btn-sm mr-2"
              :disabled="isSubmitting || form.items.length === 0 || !validateApprovals()"
            >
              <span v-if="isSubmitting" class="spinner-border spinner-border-sm mr-1"></span>
              {{ isEditMode ? 'Update' : 'Create Stock Count' }}
            </button>
            <button type="button" class="btn btn-secondary btn-sm" @click="goToIndex">Cancel</button>
          </div>
        </div>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import axios from 'axios'
import { showAlert } from '@/Utils/bootbox'
import { initSelect2, destroySelect2 } from '@/Utils/select2'

const props = defineProps({
  initialData: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['submitted'])

const isSubmitting = ref(false)
const isImporting = ref(false)
const selectedFileName = ref('')
const products = ref([])
const warehouses = ref([])
const users = ref({ initial: [], approve: [] })

const warehouseSelect = ref(null)
const productSelect = ref(null)
const fileInput = ref(null)

const isEditMode = computed(() => !!props.initialData.id)

const form = ref({
  transaction_date: '',
  warehouse_id: '',
  remarks: '',
  items: [], // { product_id, ending_quantity, counted_quantity, remarks }
  approvals: [],
})

const goToIndex = () => {
  window.location.href = '/inventory/stock-counts'
}

const mapItem = (product, existing = {}) => ({
  product_id: Number(product.id),
  item_code: product.item_code || 'N/A',
  product_name: product.product_name || '',
  description: product.description || '',
  unit_name: product.unit_name || 'N/A',
  ending_quantity: parseFloat(product.stock_on_hand) || 0,
  counted_quantity: existing.counted_quantity !== undefined ? parseFloat(existing.counted_quantity) : parseFloat(product.stock_on_hand) || 0,
  remarks: existing.remarks || '',
})

const fetchProducts = async (warehouseId = '', cutoffDate = '') => {
  if (!warehouseId || !cutoffDate) {
    products.value = []
    return
  }
  try {
    const { data } = await axios.get('/api/inventory/stock-counts/get-products', {
      params: { warehouse_id: warehouseId, cutoff_date: cutoffDate }
    })
    products.value = Array.isArray(data) ? data : data.data
  } catch (err) {
    showAlert('Error', 'Failed to load products for selected warehouse and date.', 'danger')
  }
}

const fetchWarehouses = async () => {
  try {
    const { data } = await axios.get('/api/inventory/stock-counts/get-warehouses')
    warehouses.value = Array.isArray(data) ? data : data.data
  } catch (err) {
    showAlert('Error', 'Failed to load warehouses.', 'danger')
  }
}

const fetchUsersForApproval = async (type) => {
  if (users.value[type]?.length) return
  try {
    const { data } = await axios.get('/api/inventory/stock-counts/get-users-for-approval', { params: { request_type: type } })
    users.value[type] = data.data || []
  } catch (err) {
    showAlert('Error', `Failed to load ${type} users.`, 'danger')
  }
}

const addItem = async (productId) => {
  if (!productId || !form.value.warehouse_id || !form.value.transaction_date) return

  const product = products.value.find(p => p.id == productId)
  if (!product) return

  if (!form.value.items.some(i => i.product_id === Number(productId))) {
    form.value.items.push(mapItem(product))
  }
  $(productSelect.value).val(null).trigger('change.select2')
}

const removeItem = (index) => form.value.items.splice(index, 1)
const calculateVariance = () => {} // Already computed in template

const handleFileUpload = (e) => {
  const file = e.target.files[0]
  selectedFileName.value = file?.name || ''
}

const triggerFileInput = () => fileInput.value?.click()

const importFile = async () => {
  if (!fileInput.value.files[0] || !form.value.warehouse_id || !form.value.transaction_date) {
    showAlert('Error', 'Please select warehouse, date, and file.', 'danger')
    return
  }

  isImporting.value = true
  const formData = new FormData()
  formData.append('file', fileInput.value.files[0])

  try {
    await fetchProducts(form.value.warehouse_id, form.value.transaction_date)
    const { data } = await axios.post('/api/inventory/stock-counts/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    form.value.items = data.data.items
      .map(item => {
        const product = products.value.find(p => p.id == item.product_id)
        return product ? mapItem(product, item) : null
      })
      .filter(Boolean)

    showAlert('Success', 'Stock count items imported successfully.', 'success')
    fileInput.value.value = ''
    selectedFileName.value = ''
  } catch (err) {
    const msg = err.response?.data?.message || 'Import failed'
    showAlert('Import Failed', msg, 'danger')
  } finally {
    isImporting.value = false
  }
}

const validateApprovals = () => {
  const types = form.value.approvals.map(a => a.request_type)
  return types.includes('initial') && types.includes('approve') && new Set(types).size === types.length
}

const submitForm = async () => {
  if (!form.value.transaction_date || !form.value.warehouse_id || form.value.items.length === 0) {
    showAlert('Error', 'Please fill all required fields and add at least one item.', 'danger')
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
        remarks: i.remarks || null,
      })),
      approvals: form.value.approvals.map(a => ({
        user_id: a.user_id,
        request_type: a.request_type,
      })),
    }

    const url = isEditMode.value
      ? `/api/inventory/stock-counts/${props.initialData.id}`
      : '/api/inventory/stock-counts'

    await axios[isEditMode.value ? 'put' : 'post'](url, payload)

    showAlert('Success', 'Stock count saved successfully!', 'success')
    emit('submitted')
    goToIndex()
  } catch (err) {
    showAlert('Error', err.response?.data?.message || 'Failed to save stock count.', 'danger')
  } finally {
    isSubmitting.value = false
  }
}

// Watchers
watch(() => form.value.warehouse_id, () => { if (form.value.transaction_date) fetchProducts(form.value.warehouse_id, form.value.transaction_date) })
watch(() => form.value.transaction_date, () => { if (form.value.warehouse_id) fetchProducts(form.value.warehouse_id, form.value.transaction_date) })

onMounted(async () => {
  await Promise.all([
    fetchWarehouses(),
    fetchUsersForApproval('initial'),
    fetchUsersForApproval('approve'),
  ])

  form.value.approvals = [
    { request_type: 'initial', user_id: null, isDefault: true, availableUsers: users.value.initial },
    { request_type: 'approve', user_id: null, isDefault: true, availableUsers: users.value.approve },
  ]

  await nextTick()

  initSelect2(warehouseSelect.value, { placeholder: 'Select Warehouse' }, val => form.value.warehouse_id = val)
  initSelect2(productSelect.value, { placeholder: 'Search Product...' })
  $(productSelect.value).on('select2:select', e => addItem(e.params.data.id))

  $('#transaction_date').datepicker({
    format: 'yyyy-mm-dd',
    autoclose: true,
    todayHighlight: true,
  }).on('changeDate', e => form.value.transaction_date = e.format())

  if (isEditMode.value && props.initialData.id) {
    // Load edit data if needed (similar to your original fetchEditData)
  }
})

onUnmounted(() => {
  destroySelect2(warehouseSelect.value)
  destroySelect2(productSelect.value)
  $('#transaction_date').datepicker('destroy')
})
</script>