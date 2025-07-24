<template>
  <div class="container-fluid mt-3">
    <form @submit.prevent="submitForm">
      <div class="card border mb-0">
        <!-- Header -->
        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
          <h4 class="mb-0 font-weight-bold">Create Stock Beginning</h4>
          <a :href="indexRoute" class="btn btn-secondary btn-sm">
            <i class="fal fa-arrow-left"></i> Back to List
          </a>
        </div>

        <div class="card-body">
          <!-- Section 1: Basic Info -->
          <div class="border rounded p-3 mb-4">
            <h5 class="font-weight-bold mb-3 text-primary">🏷️ Stock Beginning Details</h5>
            <div class="form-row">
              <!-- Warehouse Select -->
              <div class="form-group col-md-4">
                <label for="warehouse_id" class="font-weight-bold">Warehouse</label>
                <select
                  ref="warehouseSelect"
                  v-model="form.warehouse_id"
                  class="form-control"
                  id="warehouse_id"
                  required
                >
                  <option value="">Select Warehouse</option>
                  <option
                    v-for="warehouse in warehouses"
                    :key="warehouse.id"
                    :value="warehouse.id"
                  >
                    {{ warehouse.name }}
                  </option>
                </select>
              </div>

              <!-- Beginning Date -->
              <div class="form-group col-md-4">
                <label for="beginning_date" class="font-weight-bold">Beginning Date</label>
                <input
                  v-model="form.beginning_date_display"
                  type="text"
                  class="form-control datepicker"
                  id="beginning_date"
                  required
                  placeholder="MMM DD, YYYY (e.g., Jul 25, 2025)"
                />
                <small class="form-text text-muted">
                  Format: MMM DD, YYYY (e.g., Jul 25, 2025)
                </small>
              </div>

              <!-- Import File -->
              <div class="form-group col-md-4">
                <label for="import_file" class="font-weight-bold">Choose Excel File</label>
                <div class="input-group">
                  <div class="custom-file">
                    <input
                      type="file"
                      class="custom-file-input"
                      id="import_file"
                      accept=".xlsx,.xls,.csv"
                      ref="fileInput"
                      @change="handleFileUpload"
                    />
                    <label class="custom-file-label" for="import_file">
                      {{ selectedFileName || 'Choose file...' }}
                    </label>
                  </div>
                  <div class="input-group-append">
                    <button
                      type="button"
                      class="btn btn-primary"
                      @click="importFile"
                      :disabled="isImporting"
                    >
                      <span v-if="isImporting" class="spinner-border spinner-border-sm mr-1"></span>
                      <i class="fal fa-upload"></i> Import
                    </button>
                    <a
                      class="btn btn-secondary"
                      href="/sampleExcel/stock_beginnings_sample.xlsx"
                      download="stock_beginnings_sample.xlsx"
                    >
                      <i class="fal fa-file-excel"></i> Download Sample
                    </a>
                  </div>
                </div>
                <small class="form-text text-muted mt-1">
                  Supported formats: .xlsx, .xls, .csv<br />
                  Columns: <code>beginning_date, warehouse_name, item_code, quantity, unit_price, remarks</code>
                </small>
              </div>
            </div>
          </div>

          <!-- Section 2: Line Items -->
          <div class="border rounded p-3 mb-4">
            <h5 class="font-weight-bold mb-3 text-primary">📦 Stock Items</h5>
            <div class="table-responsive">
              <table class="table table-bordered table-sm">
                <thead class="thead-light">
                  <tr>
                    <th style="min-width: 800px;">Product</th>
                    <th style="min-width: 100px;">Quantity</th>
                    <th style="min-width: 120px;">Unit Price</th>
                    <th style="min-width: 120px;">Total Value</th>
                    <th style="min-width: 200px;">Remarks</th>
                    <th style="min-width: 100px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, index) in form.items" :key="index">
                    <td>
                      <select
                        v-model="item.product_id"
                        class="form-control"
                        :id="'product_id_' + index"
                        required
                      >
                        <option value="">Select Product</option>
                        <option
                          v-for="product in products"
                          :key="product.id"
                          :value="product.id"
                        >
                          ({{ product.item_code }}) - {{ product.product_name }} {{ product.description }}
                        </option>
                      </select>
                    </td>
                    <td>
                      <input
                        v-model.number="item.quantity"
                        type="number"
                        class="form-control"
                        min="0.0001"
                        step="0.0001"
                        required
                      />
                    </td>
                    <td>
                      <input
                        v-model.number="item.unit_price"
                        type="number"
                        class="form-control"
                        min="0"
                        step="0.0001"
                        required
                      />
                    </td>
                    <td>
                      {{ (item.quantity * item.unit_price).toFixed(4) }}
                    </td>
                    <td>
                      <textarea
                        v-model="item.remarks"
                        class="form-control"
                        rows="2"
                        maxlength="1000"
                      ></textarea>
                    </td>
                    <td>
                      <button
                        type="button"
                        class="btn btn-danger btn-sm"
                        @click="removeItem(index)"
                      >
                        <i class="fal fa-trash-alt"></i> Remove
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <button
              type="button"
              class="btn btn-success btn-sm mt-2"
              @click="addItem"
            >
              + Add Item
            </button>
          </div>

          <!-- Section 3: Submit/Cancel -->
          <div class="text-right">
            <button
              type="submit"
              class="btn btn-primary btn-sm mr-2"
              :disabled="isSubmitting"
            >
              <span
                v-if="isSubmitting"
                class="spinner-border spinner-border-sm mr-1"
              ></span>
              Create
            </button>
            <a :href="indexRoute" class="btn btn-secondary btn-sm">Cancel</a>
          </div>
        </div>
      </div>
    </form>
  </div>
</template>


<script setup>
import { ref, onMounted, onUnmounted, nextTick, watch } from 'vue'
import axios from 'axios'
import { confirmAction, showAlert } from '@/Utils/bootbox'
import { initSelect2, destroySelect2 } from '@/Utils/select2'

// Refs and state
const isSubmitting = ref(false)
const isImporting = ref(false)
const products = ref([])
const warehouses = ref([])
const warehouseSelect = ref(null)
const fileInput = ref(null)
const indexRoute = ref(window.route('stockBeginnings.index'))
const selectedFileName = ref('')

const form = ref({
  warehouse_id: null,
  beginning_date: '', // Stores YYYY-MM-DD for backend
  beginning_date_display: '', // Stores MMM DD, YYYY for display
  items: [
    {
      product_id: null,
      quantity: 1,
      unit_price: 0,
      remarks: '',
    },
  ],
})

// Fetch data
const fetchProducts = async () => {
  try {
    const response = await axios.get('/api/product-variants-stock')
    products.value = Array.isArray(response.data) ? response.data : response.data.data
  } catch (err) {
    console.error('Failed to load products:', err)
    showAlert('Error', 'Failed to load products.', 'danger')
  }
}

const fetchWarehouses = async () => {
  try {
    const response = await axios.get('/api/warehouses')
    warehouses.value = Array.isArray(response.data) ? response.data : response.data.data
  } catch (err) {
    console.error('Failed to load warehouses:', err)
    showAlert('Error', 'Failed to load warehouses.', 'danger')
  }
}

// Form manipulation
const addItem = async () => {
  form.value.items.push({
    product_id: null,
    quantity: 1,
    unit_price: 0,
    remarks: '',
  })

  await nextTick()
  const newIndex = form.value.items.length - 1
  const select = document.getElementById(`product_id_${newIndex}`)
  if (select) {
    initSelect2(select, {
      placeholder: 'Select Product',
      width: '100%',
      allowClear: true,
    }, (v) => (form.value.items[newIndex].product_id = v))
  }
}

const removeItem = (index) => {
  if (form.value.items.length > 1) {
    const select = document.getElementById(`product_id_${index}`)
    if (select) destroySelect2(select)
    form.value.items.splice(index, 1)
  } else {
    showAlert('Error', 'At least one item is required.', 'danger')
  }
}

// File handling
const handleFileUpload = (event) => {
  const file = event.target.files[0]
  const validMimeTypes = [
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
    'application/vnd.ms-excel', // .xls
    'text/csv', // .csv
    'application/csv', // Alternative .csv MIME type
    'text/plain' // Some systems use this for .csv
  ]
  if (file) {
    selectedFileName.value = file.name
    if (!validMimeTypes.includes(file.type)) {
      showAlert('Error', 'Please upload a valid Excel or CSV file (.xlsx, .xls, or .csv).', 'danger')
      fileInput.value.value = ''
      selectedFileName.value = ''
    }
  }
}

const importFile = async () => {
  if (!fileInput.value.files[0]) {
    showAlert('Error', 'Please select a file to import.', 'danger')
    return
  }

  if (isImporting.value) return
  isImporting.value = true

  const formData = new FormData()
  formData.append('file', fileInput.value.files[0])

  try {
    const response = await axios.post('/api/stock-beginnings/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    // Check for success (status 200) and presence of data
    if (response.status === 200 && response.data.data) {
      // Destroy existing Select2 instances for items
      form.value.items.forEach((_, index) => {
        const select = document.getElementById(`product_id_${index}`)
        if (select) destroySelect2(select)
      })

      // Populate form with imported data
      const importedData = response.data.data
      form.value.items = importedData.items.map(item => ({
        product_id: item.product_id,
        quantity: parseFloat(item.quantity) || 1,
        unit_price: parseFloat(item.unit_price) || 0,
        remarks: item.remarks || '',
      }))

      // Update datepicker with imported date
      if (importedData.beginning_date) {
        const [year, month, day] = importedData.beginning_date.split('-')
        if (year && month && day) {
          const date = new Date(year, month - 1, day)
          const formattedDate = date.toLocaleDateString('en-US', {
            month: 'short',
            day: '2-digit',
            year: 'numeric'
          })
          form.value.beginning_date_display = formattedDate
          $('#beginning_date').datepicker('setDate', formattedDate)
        }
      }

      // Reinitialize Select2 for warehouse
      await nextTick()
      if (warehouseSelect.value) {
        destroySelect2(warehouseSelect.value)
        initSelect2(warehouseSelect.value, {
          placeholder: 'Select Warehouse',
          width: '100%',
          allowClear: true,
        }, (v) => (form.value.warehouse_id = v))
        $(warehouseSelect.value).val(form.value.warehouse_id).trigger('change')
      }

      // Reinitialize Select2 for product selects
      await nextTick()
      form.value.items.forEach((item, index) => {
        const select = document.getElementById(`product_id_${index}`)
        if (select) {
          initSelect2(select, {
            placeholder: 'Select Product',
            width: '100%',
            allowClear: true,
          }, (v) => (form.value.items[index].product_id = v))
          $(select).val(item.product_id).trigger('change')
        }
      })

      showAlert('Success', 'Stock beginnings data loaded into the form.', 'success')
      fileInput.value.value = ''
      selectedFileName.value = ''
      return
    }

    // Handle errors (status 422 or 500) with errors array
    const errors = response.data.errors || [response.data.message || 'Unknown error occurred']
    if (errors.length > 0) {
      const errorList = errors.map((error, index) => `${index + 1}. ${error}`).join('<br>')
      showAlert('Import Errors', `The following errors were found in the Excel file:<br><br>${errorList}`, 'danger')
      return
    }

    showAlert('Error', 'Unexpected response from server.', 'danger')
  } catch (err) {
    console.error('Import error:', err.response?.data || err)
    const errors = err.response?.data?.errors || [err.response?.data?.message || 'Failed to import stock beginning.']
    const errorList = errors.map((error, index) => `${index + 1}. ${error}`).join('<br>')
    showAlert('Error', `Import failed:<br><br>${errorList}`, 'danger')
  } finally {
    isImporting.value = false
  }
}

const submitForm = async () => {
  if (isSubmitting.value) return
  isSubmitting.value = true
  try {
    const payload = {
      warehouse_id: form.value.warehouse_id,
      beginning_date: form.value.beginning_date, // Send YYYY-MM-DD to backend
      items: form.value.items.map(item => ({
        product_id: item.product_id,
        quantity: parseFloat(item.quantity),
        unit_price: parseFloat(item.unit_price),
        remarks: item.remarks?.toString().trim() || null,
      })),
    }

    await axios.post('/api/stock-beginnings', payload)
    showAlert('Success', 'Stock beginning created successfully.', 'success')
    window.location.href = indexRoute.value
  } catch (err) {
    console.error('Submit error:', err.response?.data || err)
    showAlert('Error', err.response?.data?.message || err.message || 'Failed to save stock beginning.', 'danger')
  } finally {
    isSubmitting.value = false
  }
}

// Initialize bootstrap-datepicker
const initDatepicker = async () => {
  await nextTick()
  $('#beginning_date').datepicker({
    format: 'M dd, yyyy',
    autoclose: true,
    todayHighlight: true,
    orientation: "bottom left",  // <-- add this line
  }).on('changeDate', (e) => {
    if (e.date) {
      const date = new Date(e.date)
      const year = date.getFullYear()
      const month = String(date.getMonth() + 1).padStart(2, '0')
      const day = String(date.getDate()).padStart(2, '0')
      form.value.beginning_date = `${year}-${month}-${day}`
      form.value.beginning_date_display = date.toLocaleDateString('en-US', {
        month: 'short',
        day: '2-digit',
        year: 'numeric'
      })
    } else {
      form.value.beginning_date = ''
      form.value.beginning_date_display = ''
    }
  })
}


// Watch beginning_date_display for manual input or programmatic updates
watch(() => form.value.beginning_date_display, (newDisplayDate) => {
  if (newDisplayDate) {
    try {
      const date = new Date(newDisplayDate)
      if (!isNaN(date.getTime())) {
        const year = date.getFullYear()
        const month = String(date.getMonth() + 1).padStart(2, '0')
        const day = String(date.getDate()).padStart(2, '0')
        form.value.beginning_date = `${year}-${month}-${day}`
        $('#beginning_date').datepicker('setDate', newDisplayDate)
      } else {
        form.value.beginning_date = ''
        $('#beginning_date').datepicker('setDate', null)
      }
    } catch (err) {
      form.value.beginning_date = ''
      $('#beginning_date').datepicker('setDate', null)
    }
  } else {
    form.value.beginning_date = ''
    $('#beginning_date').datepicker('setDate', null)
  }
})

onMounted(async () => {
  await Promise.all([fetchProducts(), fetchWarehouses()])
  await nextTick()
  initSelect2(warehouseSelect.value, {
    placeholder: 'Select Warehouse',
    width: '100%',
    allowClear: true,
  }, (v) => (form.value.warehouse_id = v))
  form.value.items.forEach((_, index) => {
    const select = document.getElementById(`product_id_${index}`)
    if (select) {
      initSelect2(select, {
        placeholder: 'Select Product',
        width: '100%',
        allowClear: true,
      }, (v) => (form.value.items[index].product_id = v))
    }
  })
  initDatepicker()
})

onUnmounted(() => {
  if (warehouseSelect.value) destroySelect2(warehouseSelect.value)
  form.value.items.forEach((_, index) => {
    const select = document.getElementById(`product_id_${index}`)
    if (select) destroySelect2(select)
  })
  $('#beginning_date').datepicker('destroy')
})
</script>