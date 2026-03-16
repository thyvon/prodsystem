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
                <div class="d-flex flex-wrap align-items-center">

                <!-- Download Sample Excel -->
                <button
                    type="button"
                    class="btn btn-sm btn-outline-secondary mr-2 mb-2 d-flex align-items-center"
                    @click="downloadSampleExcel"
                >
                    <i class="fal fa-file-export"></i>
                </button>

                <!-- Import Excel -->
                <button
                    type="button"
                    class="btn btn-sm btn-outline-secondary mr-2 mb-2 d-flex align-items-center"
                    @click="triggerFileInput"
                    :disabled="isImporting"
                >
                    <span v-if="isImporting" class="spinner-border spinner-border-sm mr-1"></span>
                    <i v-else class="fal fa-file-excel"></i>
                </button>

                <!-- Hidden File Input -->
                <input
                    type="file"
                    ref="fileInput"
                    class="d-none"
                    accept=".xlsx,.xls,.csv"
                    @change="handleFileUpload"
                />

                <!-- Add Items -->
                <button
                    type="button"
                    class="btn btn-sm btn-success mr-2 mb-2 d-flex align-items-center"
                    @click="openProductsModal"
                >
                    <i class="fal fa-plus"></i>
                </button>

                <!-- Scan Barcode -->
                <button
                    type="button"
                    class="btn btn-sm btn-primary mb-2 d-flex align-items-center"
                    @click="scanBarcode"
                >
                    <i class="fal fa-barcode"></i>
                </button>

                </div>
            </div>
            <div class="table-responsive">
              <table class="table table-bordered table-sm table-hover">
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
                  <tr v-for="(item, i) in form.items" :key="i">
                    <td>{{ item.item_code }}</td>
                    <td>{{ item.product_name }} {{ item.description }}</td>
                    <td>{{ item.unit_name }}</td>
                    <td><input type="number" :value="item.ending_quantity.toFixed(2)" class="form-control" readonly /></td>
                    <td>
                      <input
                        type="number"
                        v-model.number="item.counted_quantity"
                        class="form-control"
                        min="0"
                        step="0.01"
                        required
                      />
                    </td>
                    <!-- <td>
                      <input
                        type="hidden"
                        v-model.number="item.unit_price"
                        min="0"
                        class="form-control"
                      />
                    </td> -->
                    <td>
                      <input
                        type="number"
                        :value="(item.counted_quantity - item.ending_quantity).toFixed(2)"
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

    <div ref="itemsModal" class="modal fade" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
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
                    <th>Code</th>
                    <th>Description</th>
                    <th>UoM</th>
                    <th>Stock Ending</th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="item in modalItems" :key="item.product_id"
                    @click="openQtyModal(item)"
                    style="cursor: pointer;">
                    <td>{{ item.item_code }}</td>
                    <td>{{ item.description }}</td>
                    <td>{{ item.unit_name }}</td>
                    <td>{{ item.stock_on_hand }}</td>
                </tr>
                </tbody>
            </table>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" @click="closeItemsModal">Cancel</button>
        </div>
        </div>
    </div>
    </div>

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

        <!-- Header -->
        <div class="modal-header">
            <h5 class="modal-title font-weight-bold">
            <h2>{{ scannedItem.item_code }}</h2>
            </h5>
            <button class="close" @click="closeQtyModal">&times;</button>
        </div>

        <!-- Body -->
        <div class="modal-body" v-if="scannedItem">

        <!-- Product Info Card -->
        <div class="border rounded p-3 mb-3">
            <div class="font-weight-bold text-center text-wrap">
            {{ scannedItem.description }}
            </div>
        </div>

        <!-- Stock Info, Quantity, Variance Row -->
        <div class="form-row">
            <!-- Stock Ending -->
            <div class="form-group col-12 col-md-4 mb-3">
            <label class="font-weight-bold">Stock Ending</label>
            <input
                type="text"
                class="form-control form-control-lg text-center font-weight-bold"
                :value="scannedItem.stock_on_hand.toFixed(2) + ' ' + scannedItem.unit_name"
                readonly
            />
            </div>

            <!-- Counted Quantity -->
            <div class="form-group col-12 col-md-4 mb-3">
            <label class="font-weight-bold">Counted Quantity</label>
            <input
                ref="qtyInput"
                type="text"
                inputmode="decimal"
                pattern="[0-9]*"
                v-model.number="scanQty"
                class="form-control form-control-lg text-center font-weight-bold"
                placeholder="Enter quantity"
            />
            </div>

            <!-- Variance -->
            <div class="form-group col-12 col-md-4 mb-3">
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

        <!-- Remarks -->
        <div class="form-group mb-0">
            <label class="font-weight-bold">Remarks</label>
            <textarea
            v-model="scannedItem.remarks"
            class="form-control"
            rows="2"
            placeholder="Optional notes..."
            ></textarea>
        </div>
        </div>

        <!-- Loading -->
        <div class="modal-body text-center py-5" v-else>
            <div class="spinner-border text-primary"></div>
            <div class="mt-2 text-muted">Loading item...</div>
        </div>

        <!-- Footer -->
        <div class="modal-footer" v-if="scannedItem">
            <button class="btn btn-outline-secondary" @click="closeQtyModal">
            Cancel
            </button>
            <button class="btn btn-success px-4" @click="saveItemQty">
            Save Quantity
            </button>
        </div>

        </div>
    </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick, watch, computed } from 'vue'
import { Html5Qrcode, Html5QrcodeSupportedFormats } from "html5-qrcode"
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


const scannerModal = ref(null)
const qtyModal = ref(null)
const scanQty = ref(0)
let scanner = null
let lastBarcode = null
let lastScanTime = 0

const scannedItem = ref({
  product_id: null,
  item_code: '',
  description: '',
  unit_name: '',
  counted_quantity: 0,
  stock_on_hand: 0,
  unit_price: 0,
  remarks: ''
})

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

const openQtyModal = (item) => {
  // Close items modal
  $(itemsModal.value).modal('hide');

  // Set scannedItem for quantity input
  scannedItem.value = {
    product_id: item.product_id,
    item_code: item.item_code,
    description: item.description,
    unit_name: item.unit_name,
    counted_quantity: 0,
    stock_on_hand: item.stock_on_hand || 0,
    unit_price: item.unit_price || 0,
    remarks: ''
  };

  // Set quantity input
  scanQty.value = scannedItem.value.counted_quantity;

  // Show qty modal
  $(qtyModal.value).modal('show');
};

const scanBarcode = async () => {

  if ($(scannerModal.value).hasClass('show')) return;

  $(scannerModal.value).modal('show');

  $(scannerModal.value)
    .off('shown.bs.modal')   // remove previous listeners
    .on('shown.bs.modal', async () => {

      try {

        if (scanner) return;

        const container = document.getElementById("scanner-container");
        const overlay = container.querySelector('.barcode-frame');

        const containerWidth = container.clientWidth;

        const qrWidth = Math.floor(containerWidth * 0.9);
        const qrHeight = 80;

        if (overlay) {
          overlay.style.width = `${qrWidth}px`;
          overlay.style.height = `${qrHeight}px`;
          overlay.style.top = '50%';
          overlay.style.left = '50%';
          overlay.style.transform = 'translate(-50%, -50%)';
          overlay.style.border = '2px solid #00FF00';
          overlay.style.borderRadius = '4px';
          overlay.style.boxSizing = 'border-box';
        }

        scanner = new Html5Qrcode("scanner");

        await scanner.start(
          { facingMode: { exact: "environment" } },
          {
            fps: 20,

            qrbox: {
              width: qrWidth,
              height: qrHeight
            },

            aspectRatio: 2.5,

            videoConstraints: {
              facingMode: "environment",
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

            experimentalFeatures: {
              useBarCodeDetectorIfSupported: true
            },

            disableFlip: false
          },

          async (decodedText) => {

            if (scanner) {
              await scanner.stop();
              scanner.clear();
              scanner = null;
            }

            $(scannerModal.value).modal('hide');

            handleBarcodeScan(decodedText);

          },

          () => {}
        );

      } catch (err) {
        console.error("Scanner start error:", err);
        showAlert("Error", "Failed to start scanner", "danger");
      }
    });

  // Proper cleanup
  $(scannerModal.value)
    .off('hidden.bs.modal')
    .on('hidden.bs.modal', async () => {

      if (scanner) {
        try {
          await scanner.stop();
          scanner.clear();
        } catch(e) {}

        scanner = null;
      }

    });
};
// ======================= Stop Scanner =======================
const stopScanner = async () => {
  try {
    if (scanner) {
      await scanner.stop()
      await scanner.clear()
      scanner = null
    }

    // Reset scanner state
    lastBarcode = null
    lastScanTime = 0

    // Close modal
    $(scannerModal.value).modal('hide')

  } catch (err) {
    console.error("Stop scanner error:", err)
  }
}

// ======================= Handle Barcode Scan =======================
const handleBarcodeScan = async (decodedText) => {
  const now = Date.now()

  // Prevent double-scan within 1.5s
  if (decodedText === lastBarcode && now - lastScanTime < 1500) return
  lastBarcode = decodedText
  lastScanTime = now

  // Vibrate for feedback
  navigator.vibrate?.(120)

  try {
    // Fetch product by barcode and stock count
    const { data } = await axios.post('/api/inventory/stock-counts/get-product-by-barcode', {
      stock_count_id: props.initialData?.id,
      barcode: decodedText,
      warehouse_id: form.value.warehouse_id,
      transaction_date: form.value.transaction_date
    })

    // Set scanned item
    scannedItem.value = {
      product_id: data.product_id ?? null,
      item_code: data.item_code ?? '',
      description: data.description ?? '',
      unit_name: data.unit_name ?? '',
      counted_quantity: parseFloat(data.counted_quantity || 0), // use existing counted qty
      stock_on_hand: parseFloat(data.stock_on_hand || 0),
      unit_price: parseFloat(data.average_price || 0),
      remarks: '' // user can type additional remarks
    }

    // Set quantity input to current counted quantity
    scanQty.value = scannedItem.value.counted_quantity

    // Show quantity modal
    $(qtyModal.value).modal('show')

    // Pause scanner while entering quantity
    scanner?.pause()

  } catch (err) {
    console.error('[Scanner] Product fetch error:', err)
    showAlert(
      "Item Not Found",
      err.response?.data?.message || `Barcode ${decodedText} not found`,
      "danger"
    )
  }
}

const saveItemQty = async () => {
  if (!scannedItem.value) return

  // Parse user input
  scannedItem.value.counted_quantity = parseFloat(scanQty.value || 0)

  if (isEditMode.value) {
    let response
    try {
      response = await axios.post('/api/inventory/stock-counts/scan-update', {
        stock_count_id: props.initialData?.id,
        product_id: scannedItem.value.product_id,
        counted_quantity: scannedItem.value.counted_quantity,
        remarks: scannedItem.value.remarks || '',
      })
    } catch (err) {
      console.error('[Scanner] Failed to save quantity:', err)
      showAlert("Error", err.response?.data?.message || "Failed to save quantity", "danger")
      return
    }

    // --- Handle backend warning using reusable confirmAction ---
    if (response.data.warning) {
      const proceed = await confirmAction(
        'Warning',
        `${response.data.warning}<br>Excess: ${response.data.excess_amount}<br>Do you want to continue saving?`,
        'warning'
      )

      if (!proceed) {
        // User canceled, do nothing
        return
      }

      // User confirmed
      showAlert("Info", "Item saved despite exceeding ending quantity.", "info")
    } else {
      showAlert("Success", "Item quantity saved!", "success")
    }
  }

  // --- Both modes: update local form items ---
  updateLocalFormItems()
}

// --- Helper to update local form items ---
function updateLocalFormItems() {
  const existingIndex = form.value.items.findIndex(i => i.product_id === scannedItem.value.product_id)
  if (existingIndex >= 0) {
    form.value.items[existingIndex] = {
      ...form.value.items[existingIndex],
      counted_quantity: scannedItem.value.counted_quantity,
      remarks: scannedItem.value.remarks
    }
  } else {
    form.value.items.push({
      ...scannedItem.value,
      ending_quantity: scannedItem.value.stock_on_hand
    })
  }

  // Close qty modal
  $(qtyModal.value).modal('hide')

  // Reset scannedItem
  scannedItem.value = {
    product_id: null,
    item_code: '',
    description: '',
    unit_name: '',
    counted_quantity: 0,
    stock_on_hand: 0,
    unit_price: 0,
    remarks: ''
  }
  scanQty.value = 0

  // Re-open scanner modal automatically only in edit mode
  if (isEditMode.value) {
    $(scannerModal.value).modal('show')
    scanner?.resume()
  }
}
// ======================= Close Quantity Modal =======================
const closeQtyModal = () => {
  $(qtyModal.value).modal('hide')
  scannedItem.value = {
    product_id: null,
    item_code: '',
    description: '',
    unit_name: '',
    counted_quantity: 0,
    stock_on_hand: 0,
    unit_price: 0,
    remarks: ''
  }
  scanQty.value = 0
  scanner?.resume()
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

// ==================== Computed Variance ====================
const variance = computed(() => {
  if (!scannedItem.value) return 0
  return parseFloat(scanQty.value || 0) - parseFloat(scannedItem.value.stock_on_hand || 0)
})

watch(scanQty, (val) => {
  if (scannedItem.value) scannedItem.value.counted_quantity = parseFloat(val || 0)
})

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

  // Always allow clearing selection and explicitly pass current value to avoid auto-selecting first item
  const currentValue = form.value.approvals[index].user_id ? String(form.value.approvals[index].user_id) : ''

  if ($(select).hasClass('select2-hidden-accessible')) {
    $(select).empty().select2({ data, placeholder: 'Select User', width: '100%', allowClear: true })
  } else {
    initSelect2(select, { data, placeholder: 'Select User', width: '100%', allowClear: true, value: currentValue }, val => {
      form.value.approvals[index].user_id = val ? Number(val) : null
    })
  }

  // Ensure the DOM select reflects the model (set empty string explicitly to prevent Select2 from choosing the first option)
  $(select).val(currentValue).trigger('change')
}

const openProductsModal = () => {
  if (!form.value.warehouse_id || !form.value.transaction_date) {
    showAlert('Warning', 'Please select Warehouse and Count Date first.', 'warning')
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
        url: '/api/inventory/stock-counts/get-products',
        type: 'GET',
        data: d => ({
          ...d,
          warehouse_id: form.value.warehouse_id,
          cutoff_date: form.value.transaction_date
        })
      },
      columns: [
        { data: 'item_code' },
        { data: 'description', defaultContent: '' },
        { data: 'unit_name' },
        { data: 'stock_on_hand', className: 'text-right' }
      ],
        rowCallback: (row, data) => {
        $(row).css('cursor', 'pointer')
        $(row).off('click').on('click', () => {
            // Check if item already exists in form items
            const existingItem = form.value.items?.find(i => i.product_id === data.id)

            scannedItem.value = {
            product_id: data.id,
            item_code: data.item_code,
            description: data.description,
            unit_name: data.unit_name,
            stock_on_hand: parseFloat(data.stock_on_hand),
            counted_quantity: existingItem ? parseFloat(existingItem.counted_quantity) : 0,
            unit_price: parseFloat(data.average_price || 0),
            remarks: existingItem ? existingItem.remarks || '' : ''
            }

            scanQty.value = scannedItem.value.counted_quantity || 0

            $(qtyModal.value).modal('show')
        })
        }
    })
  } else {
    productsTable.ajax.reload()
  }

  $(itemsModal.value).modal('show')
}

const removeItem = i => form.value.items.splice(i, 1)
const closeItemsModal = () => $(itemsModal.value).modal('hide')
// const toggleAll = e => $(itemsModal.value).find('.select-item').prop('checked', e.target.checked)

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
          product_id: r.product_id,
          item_code: r.product_code,
          product_name: r.product_name || '',
          description: r.description || '',
          unit_name: r.unit_name || '',
          ending_quantity: 0,
          stock_on_hand: 0,
          counted_quantity: parseFloat(r.counted_quantity ?? 0),
          unit_price: parseFloat(r.unit_price || 0),
          remarks: r.remark || ''
        })
      }
    })

    const productIds = rows.map(r => r.product_id)
    const { data: refreshed } = await axios.patch('/api/inventory/stock-counts/refresh-stock', {
      warehouse_id: form.value.warehouse_id,
      transaction_date: form.value.transaction_date,
      product_ids: productIds
    })

    const updatedMap = Object.fromEntries((refreshed.data || []).map(u => [u.product_id, u]))
    form.value.items = form.value.items.map(item => {
      const updated = updatedMap[item.product_id]
      return updated ? { ...item, ending_quantity: parseFloat(updated.stock_on_hand || 0), stock_on_hand: parseFloat(updated.stock_on_hand || 0) } : item
    })

    fileInput.value.value = ''
    showAlert('Success', `Imported ${rows.length} items and refreshed stock successfully`, 'success')
  } catch (err) {
    showAlert('Error', err.response?.data?.message || 'Failed to import', 'danger')
  } finally {
    isImporting.value = false
    if (fileInput.value) fileInput.value.value = ''
  }
}

// Refresh stock helper - triggers server refresh for current items and reloads products table
const refreshStock = async () => {
  if (!form.value.warehouse_id || !form.value.transaction_date) {
    if (productsTable) productsTable.ajax.reload()
    return
  }

  const productIds = form.value.items.map(i => i.product_id).filter(Boolean)
  if (!productIds.length) {
    if (productsTable) productsTable.ajax.reload()
    return
  }

  try {
    const { data } = await axios.patch('/api/inventory/stock-counts/refresh-stock', {
      warehouse_id: form.value.warehouse_id,
      transaction_date: form.value.transaction_date,
      product_ids: productIds
    })

    const updatedMap = Object.fromEntries((data.data || []).map(u => [u.product_id, u]))
    form.value.items = form.value.items.map(item => {
      const updated = updatedMap[item.product_id]
      return updated ? { ...item, ending_quantity: parseFloat(updated.stock_on_hand || 0), stock_on_hand: parseFloat(updated.stock_on_hand || 0), unit_price: parseFloat(updated.average_price || 0) } : item
    })

    if (productsTable) productsTable.ajax.reload()
  } catch (err) {
    console.error('Failed to refresh stock', err)
  }
}

// Watch transaction date and warehouse changes to refresh stock
watch(() => form.value.transaction_date, (nv, ov) => { if (nv !== ov) refreshStock() })
watch(() => form.value.warehouse_id, (nv, ov) => { if (nv !== ov) refreshStock() })

const downloadSampleExcel = () => {
  const link = document.createElement('a')
  link.href = '/sampleExcel/stock_count_sample.xlsx'
  link.download = 'stock_count_sample.xlsx'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
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
    unit_price: parseFloat(i.unit_price ?? 0)
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

const submitForm = async () => {
  // 1️⃣ Basic validation
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
    // 2️⃣ Prepare payload
    const payload = {
      transaction_date: form.value.transaction_date,
      warehouse_id: form.value.warehouse_id,
      remarks: form.value.remarks || null,
      items: form.value.items.map(i => ({
        product_id: i.product_id,
        ending_quantity: i.ending_quantity,
        counted_quantity: i.counted_quantity,
        unit_price: i.unit_price,
        remarks: i.remarks || null
      })),
      approvals: form.value.approvals.map(a => ({
        user_id: a.user_id,
        request_type: a.request_type
      }))
    }
    const url = isEditMode.value
      ? `/api/inventory/stock-counts/${props.initialData.id}`
      : '/api/inventory/stock-counts'
    const method = isEditMode.value ? 'put' : 'post'
    const { data } = await axios[method](url, payload)
    await showAlert('Success', 'Stock count saved successfully!', 'success')
    const stockCountId = data.data?.id
    if (stockCountId) {
      window.location.href = `/inventory/stock-counts/${stockCountId}/show`
    } else {
      window.location.href = '/inventory/stock-counts'
    }
    emit('submitted')

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



<style>
#scanner-container {
  position: relative;
  width: 100%;
  max-width: 600px;
  /* Maintain 3:1 ratio */
  aspect-ratio: 3 / 1;
  margin: auto;
}
</style>
