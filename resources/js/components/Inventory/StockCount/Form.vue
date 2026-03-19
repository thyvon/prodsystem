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

          <!-- Header Fields -->
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
                <select ref="warehouseSelect" v-model="form.warehouse_id" class="form-control" required>
                  <option value="">Select Warehouse</option>
                  <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.text }}</option>
                </select>
              </div>
              <div class="form-group col-md-4">
                <label class="font-weight-bold">Reference No</label>
                <input v-model="form.reference_no" type="text" class="form-control" readonly placeholder="Auto-generated" />
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
              <h5 class="font-weight-bold text-primary">📦 Items <span class="text-danger">*</span></h5>
              <div class="d-flex flex-wrap align-items-center">

                <button type="button" class="btn btn-sm btn-outline-secondary mr-2 mb-2" @click="downloadSampleExcel" title="Download Sample Excel">
                  <i class="fal fa-file-export"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary mr-2 mb-2" @click="triggerFileInput" :disabled="isImporting" title="Import Excel">
                  <span v-if="isImporting" class="spinner-border spinner-border-sm mr-1"></span>
                  <i v-else class="fal fa-file-excel"></i>
                </button>
                <input type="file" ref="fileInput" class="d-none" accept=".xlsx,.xls,.csv" @change="handleFileUpload" />

                <!-- Search Box (keyword priority + general fallback) -->
                <div class="position-relative mr-2 mb-2" style="min-width: 220px;" v-click-outside="clearSearch">
                  <div class="input-group input-group-sm">
                    <input
                      type="text"
                      class="form-control"
                      placeholder="Search product or keyword..."
                      v-model="productSearch"
                      @input="onSearchInput"
                      @keydown.escape="clearSearch"
                      autocomplete="off"
                      style="font-size: 16px;"
                    />
                    <div class="input-group-append">
                      <span class="input-group-text">
                        <span v-if="isSearching" class="spinner-border spinner-border-sm"></span>
                        <i v-else class="fal fa-search"></i>
                      </span>
                    </div>
                  </div>
                  <!-- Dropdown results -->
                  <div
                    v-if="showSearchDropdown"
                    class="position-absolute bg-white border rounded shadow-sm w-100"
                    style="z-index: 1050; top: 100%; max-height: 260px; overflow-y: auto;"
                  >
                    <div v-if="isSearching" class="text-center text-muted py-2 small">Searching...</div>
                    <div v-else-if="!productSearchResults.length" class="text-center text-muted py-2 small">No results found</div>
                    <button
                      v-else
                      v-for="item in productSearchResults"
                      :key="item.id"
                      type="button"
                      class="d-block w-100 text-left px-3 py-2 border-0 bg-transparent search-result-item"
                      @click="selectSearchResult(item)"
                    >
                      <span class="font-weight-bold text-primary small">{{ item.item_code }}</span>
                      <span class="text-muted small ml-1">{{ item.description }}</span>
                      <span class="float-right text-muted small">{{ item.stock_on_hand }}</span>
                    </button>
                  </div>
                </div>

                <button type="button" class="btn btn-sm btn-primary mb-2" @click="scanBarcode" title="Scan Barcode">
                  <i class="fal fa-barcode"></i>
                </button>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-4 mb-2 mb-md-0">
                <div class="border rounded bg-light h-100 px-3 py-2">
                  <div class="small text-muted">Total Item to Count</div>
                  <div class="h4 mb-0 font-weight-bold text-primary">{{ totalItemsToCount }}</div>
                </div>
              </div>
              <div class="col-md-4 mb-2 mb-md-0">
                <div class="border rounded bg-light h-100 px-3 py-2">
                  <div class="small text-muted">Total Item Counted</div>
                  <div class="h4 mb-0 font-weight-bold text-success">{{ countedItems }}</div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="border rounded bg-light h-100 px-3 py-2">
                  <div class="small text-muted">Remaining Item</div>
                  <div class="h4 mb-0 font-weight-bold" :class="remainingItems > 0 ? 'text-warning' : 'text-success'">
                    {{ remainingItems }}
                  </div>
                </div>
              </div>
            </div>

            <!-- Variance Filter Toggle -->
            <div v-if="form.items.length" class="mb-2">
              <button
                type="button"
                class="btn btn-sm"
                :class="varianceFilter === 'any' ? 'btn-warning' : 'btn-outline-warning'"
                @click="varianceFilter = varianceFilter === 'any' ? 'all' : 'any'"
              >
                <i class="fal fa-filter mr-1"></i>
                Has Variance
                <span class="badge badge-light ml-1">{{ varianceCount }}</span>
              </button>
            </div>

            <div class="table-responsive">
              <table class="table table-bordered table-sm table-hover mb-0">
                <thead class="thead-light">
                  <tr>
                    <th style="min-width: 90px;">Code</th>
                    <th style="min-width: 300px;">Description</th>
                    <th style="min-width: 60px;">UoM</th>
                    <th style="min-width: 100px;">Stock Ending</th>
                    <th style="min-width: 100px;">Counted Qty *</th>
                    <th style="min-width: 100px;">Variance</th>
                    <th style="min-width: 150px;">Remarks</th>
                    <th style="min-width: 100px;">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in filteredItems" :key="item.product_id">
                    <td>{{ item.item_code }}</td>
                    <td>{{ item.product_name }} {{ item.description }}</td>
                    <td>{{ item.unit_name }}</td>
                    <td>
                      <input type="number" :value="item.ending_quantity.toFixed(2)" class="form-control" readonly />
                    </td>
                    <td>
                      <input type="number" v-model.number="item.counted_quantity" class="form-control" min="0" step="0.01" required />
                    </td>
                    <td>
                      <input
                        type="number"
                        :value="(item.counted_quantity - item.ending_quantity).toFixed(2)"
                        class="form-control"
                        :class="{
                          'text-danger font-weight-bold': item.counted_quantity < item.ending_quantity,
                          'text-success font-weight-bold': item.counted_quantity > item.ending_quantity
                        }"
                        readonly
                      />
                    </td>
                    <td><textarea v-model="item.remarks" class="form-control" rows="1"></textarea></td>
                    <td>
                      <button type="button" class="btn btn-sm btn-danger" @click="removeItem(form.items.indexOf(item))">Remove</button>
                    </td>
                  </tr>
                  <tr v-if="!filteredItems.length">
                    <td colspan="8" class="text-center text-muted">
                      {{ form.items.length ? 'No items match this filter' : 'No items added yet' }}
                    </td>
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
                    <select v-model="app.request_type" class="form-control approval-type-select" :data-index="i" :disabled="app.isDefault" required>
                      <option value="initial">Initial</option>
                      <option value="approve">Approve</option>
                    </select>
                  </td>
                  <td>
                    <select v-model="app.user_id" class="form-control user-select" :data-index="i" required>
                      <option value="">Select User</option>
                      <option v-for="u in app.availableUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                  </td>
                  <td>
                    <button type="button" class="btn btn-sm btn-danger" @click="removeApproval(i)" :disabled="app.isDefault">
                      Remove
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Submit -->
          <div class="text-right">
            <button type="submit" class="btn btn-primary" :disabled="isSubmitting || !form.items.length || !validateApprovals()">
              <span v-if="isSubmitting" class="spinner-border spinner-border-sm mr-2"></span>
              {{ isEditMode ? form.actionButtonText : 'Create Stock Count' }}
            </button>
            <button type="button" class="btn btn-secondary ml-2" @click="goToIndex">Cancel</button>
          </div>

        </div>
      </div>
    </form>

    <!-- Barcode Scanner Modal -->
    <div ref="scannerModal" class="modal fade">
      <div class="modal-dialog modal-lg modal-dialog-topped">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Scan Barcode or QR Code</h5>
            <button class="close" @click="stopScanner">×</button>
          </div>
          <div class="modal-body text-center">
            <div id="scanner-container">
              <div id="scanner"></div>
            </div>
            <p class="text-muted">Point camera at barcode</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Quantity Modal -->
    <div ref="qtyModal" class="modal fade">
      <div class="modal-dialog modal-dialog-topped modal-lg">
        <div class="modal-content shadow">
          <div class="modal-header">
            <h5 class="modal-title font-weight-bold">{{ scannedItem.item_code }}</h5>
            <button class="close" @click="closeQtyModal">&times;</button>
          </div>

          <div class="modal-body" v-if="scannedItem">
            <div class="border rounded p-3 mb-3 text-center font-weight-bold text-wrap">
              {{ scannedItem.description }}
            </div>

            <div class="form-row">
              <div
                class="form-group col-12 mb-3"
                :class="scannedItem.server_counted_qty > 0 ? 'col-md-3' : 'col-md-4'"
              >
                <label class="font-weight-bold">Stock Ending</label>
                <input
                  type="text"
                  class="form-control form-control-lg text-center font-weight-bold"
                  :value="scannedItem.stock_on_hand.toFixed(2) + ' ' + scannedItem.unit_name"
                  readonly
                />
              </div>
              <div
                v-if="scannedItem.server_counted_qty > 0"
                class="form-group col-12 col-md-3 mb-3"
              >
                <label class="font-weight-bold">Already Counted</label>
                <input
                  type="text"
                  class="form-control form-control-lg text-center font-weight-bold text-info"
                  :value="scannedItem.server_counted_qty.toFixed(2)"
                  readonly
                />
              </div>
              <div
                class="form-group col-12 mb-3"
                :class="scannedItem.server_counted_qty > 0 ? 'col-md-3' : 'col-md-4'"
              >
                <label class="font-weight-bold">Actual Qty</label>
                <input
                  type="text"
                  inputmode="decimal"
                  pattern="[0-9]*"
                  :value="scanQty || ''"
                  @input="scanQty = parseFloat($event.target.value) || 0"
                  class="form-control form-control-lg text-center font-weight-bold"
                  placeholder="Enter qty"
                />
                <small v-if="scannedItem.server_counted_qty > 0" class="text-muted">
                  Total: {{ (scannedItem.server_counted_qty + (scanQty || 0)).toFixed(2) }}
                </small>
              </div>
              <div
                class="form-group col-12 mb-3"
                :class="scannedItem.server_counted_qty > 0 ? 'col-md-3' : 'col-md-4'"
              >
                <label class="font-weight-bold">Variance</label>
                <input
                  type="text"
                  class="form-control form-control-lg text-center font-weight-bold"
                  :class="{
                    'text-success': variance > 0,
                    'text-danger': variance < 0,
                    'text-secondary': variance === 0
                  }"
                  :value="variance.toFixed(2)"
                  readonly
                />
              </div>
            </div>

            <div class="form-group mb-0">
              <label class="font-weight-bold">Remarks</label>
              <textarea v-model="scannedItem.remarks" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
            </div>
          </div>

          <div class="modal-body text-center py-5" v-else-if="isLoadingItem">
            <div class="spinner-border text-primary"></div>
            <div class="mt-2 text-muted">Fetching live stock...</div>
          </div>

          <div class="modal-footer" v-if="scannedItem">
            <button class="btn btn-outline-secondary" @click="closeQtyModal">Cancel</button>
            <button class="btn btn-success px-4" @click="saveItemQty">Save Quantity</button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick, watch, computed } from 'vue'
import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode'
import { showAlert, confirmAction } from '@/Utils/bootbox'
import { initSelect2, destroySelect2 } from '@/Utils/select2'
import axios from 'axios'

// ==================== Props & Emits ====================
const props = defineProps({ initialData: Object })
const emit = defineEmits(['submitted'])

// ==================== State ====================
const isEditMode = ref(!!props.initialData?.id)
const isSubmitting = ref(false)
const isImporting = ref(false)
const isLoadingItem = ref(false)
const totalItemsToCount = ref(0)

// Template refs
const warehouseSelect = ref(null)
const scannerModal = ref(null)
const qtyModal = ref(null)
const fileInput = ref(null)

// Scanner state
let scanner = null
let lastBarcode = null
let lastScanTime = 0

// Form state
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
const approvalUsers = ref({ initial: [], approve: [] })

// Search state
const productSearch = ref('')
const productSearchResults = ref([])
const isSearching = ref(false)
const showSearchDropdown = ref(false)
let searchTimeout = null

// Scanned item state
const EMPTY_SCANNED_ITEM = {
  product_id: null,
  item_code: '',
  description: '',
  unit_name: '',
  counted_quantity: 0,
  server_counted_qty: 0,
  stock_on_hand: 0,
  unit_price: 0,
  remarks: ''
}
const scannedItem = ref({ ...EMPTY_SCANNED_ITEM })
const scanQty = ref(0)

// ==================== Computed ====================
const variance = computed(() => {
  const total = parseFloat(scannedItem.value?.server_counted_qty || 0) + parseFloat(scanQty.value || 0)
  return total - parseFloat(scannedItem.value?.stock_on_hand || 0)
})

const varianceFilter = ref('all')

const varianceCount = computed(() =>
  form.value.items.filter(i => i.counted_quantity !== i.ending_quantity).length
)

const countedItems = computed(() => {
  const countedProductIds = new Set(
    form.value.items
      .filter(i => Number(i.ending_quantity || 0) > 0 && i.product_id)
      .map(i => i.product_id)
  )

  return countedProductIds.size
})

const remainingItems = computed(() =>
  Math.max(totalItemsToCount.value - countedItems.value, 0)
)

const filteredItems = computed(() =>
  varianceFilter.value === 'any'
    ? form.value.items.filter(i => i.counted_quantity !== i.ending_quantity)
    : form.value.items
)

// ==================== Watchers ====================
watch(scanQty, val => {
  if (scannedItem.value) {
    scannedItem.value.counted_quantity = parseFloat(scannedItem.value.server_counted_qty || 0) + parseFloat(val || 0)
  }
})

watch(
  [() => form.value.transaction_date, () => form.value.warehouse_id],
  ([newTransactionDate, newWarehouseId], [oldTransactionDate, oldWarehouseId]) => {
    if (newTransactionDate === oldTransactionDate && newWarehouseId === oldWarehouseId) return
    refreshStock()
    fetchCountSummary()
  }
)

// ==================== Helpers ====================
const resetScannedItem = () => {
  scannedItem.value = { ...EMPTY_SCANNED_ITEM }
  scanQty.value = 0
}

const cleanupScanner = async () => {
  if (!scanner) return
  try {
    await scanner.stop()
    scanner.clear()
  } catch (e) {
    console.warn('Scanner cleanup error:', e)
  } finally {
    scanner = null
  }
}

const goToIndex = () => { window.location.href = '/inventory/stock-counts' }

// ==================== API Calls ====================
const fetchWarehouses = async () => {
  const { data } = await axios.get('/api/main-value-lists/get-warehouses')
  warehouses.value = data.data || data
}

const fetchApprovalUsers = async () => {
  const { data } = await axios.get('/api/inventory/stock-counts/get-approval-users')
  approvalUsers.value = {
    initial: data.initial || [],
    approve: data.approve || []
  }
}

const fetchCountSummary = async () => {
  if (!form.value.warehouse_id || !form.value.transaction_date) {
    totalItemsToCount.value = 0
    return
  }

  try {
    const { data } = await axios.get('/api/inventory/stock-counts/count-summary', {
      params: {
        warehouse_id: form.value.warehouse_id,
        transaction_date: form.value.transaction_date
      }
    })

    totalItemsToCount.value = Number(data.data?.total_items_to_count || 0)
  } catch (err) {
    console.error('Failed to fetch stock count summary', err)
    totalItemsToCount.value = 0
  }
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
  }, val => { form.value.warehouse_id = val })

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
  const currentValue = form.value.approvals[index].user_id
    ? String(form.value.approvals[index].user_id)
    : ''

  if ($(select).hasClass('select2-hidden-accessible')) {
    $(select).empty().select2({ data, placeholder: 'Select User', width: '100%', allowClear: true })
  } else {
    initSelect2(select, { data, placeholder: 'Select User', width: '100%', allowClear: true, value: currentValue }, val => {
      form.value.approvals[index].user_id = val ? Number(val) : null
    })
  }

  $(select).val(currentValue).trigger('change')
}

const validateApprovals = () => {
  const types = form.value.approvals.map(a => a.request_type)
  return types.includes('initial') && types.includes('approve') && new Set(types).size === 2
}

const removeApproval = (i) => {
  if (form.value.approvals[i].isDefault) return
  ;['.approval-type-select', '.user-select'].forEach(sel => {
    const el = document.querySelector(`${sel}[data-index="${i}"]`)
    if (el) destroySelect2(el)
  })
  form.value.approvals.splice(i, 1)
}

// ==================== Live Stock Fetch ====================
const fetchLiveItem = async (itemCode) => {
  const { data } = await axios.post('/api/inventory/stock-counts/get-product-by-barcode', {
    barcode:          itemCode,
    warehouse_id:     form.value.warehouse_id,
    transaction_date: form.value.transaction_date,
    stock_count_id:   props.initialData?.id ?? null
  })
  return data
}

const openQtyModal = async (itemCode) => {
  isLoadingItem.value = true
  try {
    const data = await fetchLiveItem(itemCode)

    const serverCountedQty = parseFloat(data.counted_quantity || 0)

    scannedItem.value = {
      product_id:         data.product_id,
      item_code:          data.item_code,
      description:        data.description,
      unit_name:          data.unit_name,
      stock_on_hand:      parseFloat(data.stock_on_hand || 0),
      server_counted_qty: serverCountedQty,
      counted_quantity:   serverCountedQty,
      unit_price:         parseFloat(data.average_price || 0),
      remarks:            data.remarks ?? ''
    }
    scanQty.value = 0

    const existingIndex = form.value.items.findIndex(i => i.product_id === data.product_id)
    if (existingIndex >= 0) {
      form.value.items[existingIndex].counted_quantity = serverCountedQty
    }

    $(qtyModal.value).modal('show')
  } catch (err) {
    console.error('[Stock] Failed to fetch live item:', err)
    showAlert('Error', err.response?.data?.message || 'Failed to fetch stock data', 'danger')
  } finally {
    isLoadingItem.value = false
  }
}

// ==================== Search ====================
const searchProducts = async () => {
  const q = productSearch.value.trim()
  if (!q) {
    productSearchResults.value = []
    showSearchDropdown.value = false
    return
  }

  if (!form.value.warehouse_id || !form.value.transaction_date) {
    showAlert('Warning', 'Please select Warehouse and Count Date first.', 'warning')
    return
  }

  isSearching.value = true
  showSearchDropdown.value = true
  try {
    const { data } = await axios.get('/api/inventory/stock-counts/get-products', {
      params: {
        'search[value]': q,
        keyword:         q,
        warehouse_id:    form.value.warehouse_id,
        cutoff_date:     form.value.transaction_date,
        start:           0,
        length:          10,
        draw:            1
      }
    })
    productSearchResults.value = data.data ?? []
  } catch (err) {
    console.error('Product search error:', err)
    productSearchResults.value = []
  } finally {
    isSearching.value = false
  }
}

const onSearchInput = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(searchProducts, 300)
}

const selectSearchResult = (item) => {
  productSearch.value = ''
  productSearchResults.value = []
  showSearchDropdown.value = false
  openQtyModal(item.item_code)
}

const clearSearch = () => {
  productSearch.value = ''
  productSearchResults.value = []
  showSearchDropdown.value = false
}

const removeItem = i => form.value.items.splice(i, 1)

// ==================== Quantity Modal ====================
const closeQtyModal = () => {
  $(qtyModal.value).modal('hide')
  resetScannedItem()
  scanner?.resume()
}

const saveItemQty = async () => {
  if (!scannedItem.value) return

  const newQty = parseFloat(scanQty.value || 0)

  if (isEditMode.value) {
    try {
      await axios.post('/api/inventory/stock-counts/scan-update', {
        stock_count_id:   props.initialData?.id,
        product_id:       scannedItem.value.product_id,
        counted_quantity: newQty,
        remarks:          scannedItem.value.remarks || ''
      })
      showAlert('Success', 'Item quantity saved!', 'success')
    } catch (err) {
      console.error('[Scanner] Failed to save quantity:', err)
      if (err.response?.status === 409) {
        const excess  = err.response.data.excess?.toFixed(2) ?? '?'
        const total   = err.response.data.total?.toFixed(2) ?? '?'
        const ending  = err.response.data.ending?.toFixed(2) ?? '?'
        const proceed = await confirmAction(
          'Warning',
          `Total counted (${total}) will exceed stock ending (${ending})!<br>Excess: ${excess}<br><br>Do you want to continue saving?`,
          'warning'
        )
        if (!proceed) return
        try {
          await axios.post('/api/inventory/stock-counts/scan-update', {
            stock_count_id:   props.initialData?.id,
            product_id:       scannedItem.value.product_id,
            counted_quantity: newQty,
            remarks:          scannedItem.value.remarks || '',
            force:            true
          })
          showAlert('Success', 'Item quantity saved!', 'success')
        } catch (forceErr) {
          showAlert('Error', forceErr.response?.data?.message || 'Failed to save quantity', 'danger')
          return
        }
      } else {
        showAlert('Error', err.response?.data?.message || 'Failed to save quantity', 'danger')
        return
      }
    }
  }

  scannedItem.value.counted_quantity = parseFloat(scannedItem.value.server_counted_qty || 0) + newQty
  updateLocalFormItems()
}

const updateLocalFormItems = () => {
  const existingIndex = form.value.items.findIndex(i => i.product_id === scannedItem.value.product_id)

  if (existingIndex >= 0) {
    form.value.items[existingIndex] = {
      ...form.value.items[existingIndex],
      counted_quantity: scannedItem.value.counted_quantity,
      remarks:          scannedItem.value.remarks
    }
  } else {
    form.value.items.push({
      ...scannedItem.value,
      ending_quantity: scannedItem.value.stock_on_hand
    })
  }

  $(qtyModal.value).modal('hide')
  resetScannedItem()
}

// ==================== Barcode Scanner ====================
const scanBarcode = async () => {
  if ($(scannerModal.value).hasClass('show')) return

  $(scannerModal.value).modal('show')

  $(scannerModal.value)
    .off('shown.bs.modal')
    .on('shown.bs.modal', async () => {
      if (scanner) return
      try {
        const container = document.getElementById('scanner-container')
        const containerWidth = container.clientWidth
        const qrWidth = Math.floor(containerWidth * 0.9)
        const qrHeight = 80

        const overlay = container.querySelector('.barcode-frame')
        if (overlay) {
          Object.assign(overlay.style, {
            width: `${qrWidth}px`,
            height: `${qrHeight}px`,
            top: '50%',
            left: '50%',
            transform: 'translate(-50%, -50%)',
            border: '2px solid #00FF00',
            borderRadius: '4px',
            boxSizing: 'border-box'
          })
        }

        scanner = new Html5Qrcode('scanner')

        await scanner.start(
          { facingMode: { exact: 'environment' } },
          {
            fps: 20,
            qrbox: { width: qrWidth, height: qrHeight },
            aspectRatio: 2.5,
            videoConstraints: {
              facingMode: 'environment',
              width: { ideal: 1920 },
              height: { ideal: 1080 }
            },
            formatsToSupport: [
              Html5QrcodeSupportedFormats.CODE_128,
              Html5QrcodeSupportedFormats.CODE_39,
              Html5QrcodeSupportedFormats.EAN_13,
              Html5QrcodeSupportedFormats.EAN_8,
              Html5QrcodeSupportedFormats.UPC_A,
              Html5QrcodeSupportedFormats.UPC_E
            ],
            experimentalFeatures: { useBarCodeDetectorIfSupported: true },
            disableFlip: false
          },
          async (decodedText) => {
            await cleanupScanner()
            $(scannerModal.value).modal('hide')
            handleBarcodeScan(decodedText)
          },
          () => {}
        )
      } catch (err) {
        console.error('Scanner start error:', err)
        showAlert('Error', 'Failed to start scanner', 'danger')
      }
    })

  $(scannerModal.value)
    .off('hidden.bs.modal')
    .on('hidden.bs.modal', cleanupScanner)
}

const stopScanner = async () => {
  await cleanupScanner()
  lastBarcode = null
  lastScanTime = 0
  $(scannerModal.value).modal('hide')
}

const handleBarcodeScan = async (decodedText) => {
  const now = Date.now()
  if (decodedText === lastBarcode && now - lastScanTime < 1500) return
  lastBarcode = decodedText
  lastScanTime = now

  navigator.vibrate?.(120)

  scanner?.pause()
  await openQtyModal(decodedText)
}

// ==================== File Import ====================
const triggerFileInput = () => fileInput.value.click()
const handleFileUpload = e => { if (e.target.files[0]) importFile() }

const importFile = async () => {
  const file = fileInput.value.files[0]
  if (!file) return

  if (!form.value.warehouse_id || !form.value.transaction_date) {
    showAlert('Warning', 'Please select Warehouse and Count Date first.', 'warning')
    return
  }

  isImporting.value = true
  const formData = new FormData()
  formData.append('file', file)

  try {
    const { data } = await axios.post('/api/inventory/stock-counts/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    if (data.errors?.length) {
      showAlert('Error', `Errors:<br>${data.errors.join('<br>')}`, 'danger', { html: true })
      return
    }

    const rows = data.data?.items || []
    if (!rows.length) {
      showAlert('Warning', 'No valid rows found in Excel.', 'warning')
      return
    }

    const existingIds = new Set(form.value.items.map(i => i.product_id))
    rows.forEach(r => {
      if (!existingIds.has(r.product_id)) {
        form.value.items.push({
          product_id:       r.product_id,
          item_code:        r.product_code,
          product_name:     r.product_name || '',
          description:      r.description || '',
          unit_name:        r.unit_name || '',
          ending_quantity:  0,
          stock_on_hand:    0,
          counted_quantity: parseFloat(r.counted_quantity ?? 0),
          unit_price:       parseFloat(r.unit_price || 0),
          remarks:          r.remark || ''
        })
      }
    })

    await refreshStockForProducts(rows.map(r => r.product_id))
    showAlert('Success', `Imported ${rows.length} items and refreshed stock successfully`, 'success')
  } catch (err) {
    showAlert('Error', err.response?.data?.message || 'Failed to import', 'danger')
  } finally {
    isImporting.value = false
    if (fileInput.value) fileInput.value.value = ''
  }
}

// ==================== Stock Refresh ====================
const refreshStockForProducts = async (productIds) => {
  if (!productIds.length) return

  const { data } = await axios.patch('/api/inventory/stock-counts/refresh-stock', {
    warehouse_id:     form.value.warehouse_id,
    transaction_date: form.value.transaction_date,
    product_ids:      productIds
  })

  const updatedMap = Object.fromEntries((data.data || []).map(u => [u.product_id, u]))
  form.value.items = form.value.items.map(item => {
    const updated = updatedMap[item.product_id]
    return updated
      ? {
          ...item,
          ending_quantity: parseFloat(updated.stock_on_hand || 0),
          stock_on_hand:   parseFloat(updated.stock_on_hand || 0),
          unit_price: item.unit_price > 0
          ? item.unit_price
          : parseFloat(updated.average_price || 0)
        }
      : item
  })
}

const refreshStock = async () => {
  if (!form.value.warehouse_id || !form.value.transaction_date) return

  const productIds = form.value.items.map(i => i.product_id).filter(Boolean)

  try {
    if (productIds.length) await refreshStockForProducts(productIds)
  } catch (err) {
    console.error('Failed to refresh stock', err)
  }
}

// ==================== Sample Excel ====================
const downloadSampleExcel = () => {
  const link = document.createElement('a')
  link.href = '/sampleExcel/stock_count_sample.xlsx'
  link.download = 'stock_count_sample.xlsx'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

// ==================== Load Edit Data ====================
const loadEditDataFromProps = async () => {
  const d = props.initialData
  if (!d) return

  Object.assign(form.value, {
    transaction_date: d.transaction_date,
    warehouse_id:     d.warehouse_id,
    reference_no:     d.reference_no,
    remarks:          d.remarks,
    actionButtonText: d.buttonSubmitText || 'Update'
  })

  form.value.items = d.items.map(i => ({
    id:               i.id,
    product_id:       i.product_id,
    item_code:        i.product_code,
    product_name:     (i.description || '').split(' ')[0] || '',
    description:      i.description || '',
    unit_name:        i.unit_name || '',
    ending_quantity:  parseFloat(i.ending_quantity ?? 0),
    counted_quantity: parseFloat(i.counted_quantity ?? 0),
    remarks:          i.remarks || '',
    stock_on_hand:    parseFloat(i.stock_on_hand ?? 0),
    unit_price:       parseFloat(i.unit_price ?? 0)
  }))

  form.value.approvals = d.approvals.map(a => ({
    id:           a.id,
    request_type: a.request_type,
    user_id:      a.user_id,
    isDefault:    true
  }))

  await nextTick()
  initWarehouseSelect2()
  await fetchApprovalUsers()
  await fetchCountSummary()
  nextTick(() => initApprovalSelect2())
}

// ==================== Form Submit ====================
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
      warehouse_id:     form.value.warehouse_id,
      remarks:          form.value.remarks || null,
      items: form.value.items.map(i => ({
        product_id:       i.product_id,
        ending_quantity:  i.ending_quantity,
        counted_quantity: i.counted_quantity,
        unit_price:       i.unit_price,
        remarks:          i.remarks || null
      })),
      approvals: form.value.approvals.map(a => ({
        user_id:      a.user_id,
        request_type: a.request_type
      }))
    }

    const url = isEditMode.value
      ? `/api/inventory/stock-counts/${props.initialData.id}`
      : '/api/inventory/stock-counts'
    const method = isEditMode.value ? 'put' : 'post'

    const { data } = await axios[method](url, payload)
    await showAlert('Success', 'Stock count saved successfully!', 'success')

    const redirectId = data.data?.id
    window.location.href = redirectId
      ? `/inventory/stock-counts/${redirectId}/show`
      : '/inventory/stock-counts'

    emit('submitted')
  } catch (err) {
    await showAlert('Error', err.response?.data?.message || 'Failed to save', 'danger')
  } finally {
    isSubmitting.value = false
  }
}

// ==================== Click Outside Directive ====================
const vClickOutside = {
  mounted(el, binding) {
    el._clickOutside = (e) => { if (!el.contains(e.target)) binding.value() }
    document.addEventListener('click', el._clickOutside)
  },
  unmounted(el) {
    document.removeEventListener('click', el._clickOutside)
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
  cleanupScanner()
})
</script>

<style>
.search-result-item:hover {
  background-color: #f0f4ff !important;
  cursor: pointer;
}

#scanner-container {
  position: relative;
  width: 100%;
  max-width: 600px;
  aspect-ratio: 3 / 1;
  margin: auto;
}
</style>
