<template>
  <div class="container-fluid mt-3">
    <form @submit.prevent="submitForm">
      <div class="card border mb-0">
        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
          <h4 class="mb-0 font-weight-bold">{{ isEditMode ? 'Edit Stock Beginning' : 'Create Stock Beginning' }}</h4>
          <a :href="indexRoute" class="btn btn-primary btn-sm">
            <i class="fal fa-backward"></i>
          </a>
        </div>

        <div class="card-body">
          <div class="border rounded p-3 mb-4">
            <h5 class="font-weight-bold mb-3 text-primary">🏷️ Stock Beginning Details</h5>
            <div class="form-row">
              <div class="form-group col-md-3">
                <label for="beginning_date" class="font-weight-bold">Beginning Date</label>
                <input
                  v-model="form.beginning_date_display"
                  type="text"
                  class="form-control datepicker"
                  id="beginning_date"
                  required
                  placeholder="MMM DD, YYYY (e.g., Jul 25, 2025)"
                />
              </div>
              <div class="form-group col-md-3">
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
            </div>
          </div>

          <div class="border rounded p-3 mb-4">
            <div class="form-row">
              <div class="form-group col-md-4">
                <label for="import_file" class="font-weight-bold">Import Stock Items</label>
                <div class="input-group">
                  <input
                    type="file"
                    class="d-none"
                    id="import_file"
                    accept=".xlsx,.xls,.csv"
                    ref="fileInput"
                    @change="handleFileUpload"
                  />
                  <button
                    type="button"
                    class="btn btn-outline-secondary"
                    @click="triggerFileInput"
                  >
                    <i class="fal fa-file-upload"></i>
                    {{ selectedFileName || 'Choose file...' }}
                  </button>
                  <button
                    type="button"
                    class="btn btn-outline-primary btn-lg btn-icon rounded-circle hover-effect-dot ml-2"
                    @click="importFile"
                    :disabled="isImporting"
                  >
                    <span v-if="isImporting" class="spinner-border spinner-border-sm mr-1"></span>
                    <i class="fal fa-upload"></i>
                  </button>
                  <a
                    class="btn btn-outline-danger btn-lg btn-icon rounded-circle hover-effect-dot ml-2"
                    href="/sampleExcel/stock_beginnings_sample.xlsx"
                    download="stock_beginnings_sample.xlsx"
                  >
                    <i class="fal fa-file-excel"></i>
                  </a>
                </div>
              </div>
              <div class="form-group col-md-8">
                <label for="product_select" class="font-weight-bold">Add Product</label>
                <select
                  ref="productSelect"
                  class="form-control"
                  id="product_select"
                >
                  <option value="">Select Product</option>
                  <option
                    v-for="product in products"
                    :key="product.id"
                    :value="product.id"
                  >
                    {{ product.item_code }} - {{ product.product_name }} {{ product.description }}
                  </option>
                </select>
              </div>
            </div>
            <h5 class="font-weight-bold mb-3 text-primary">📦 Stock Items</h5>
            <div class="table-responsive">
              <table id="stockItemsTable" class="table table-bordered table-sm">
                <thead class="thead-light">
                  <tr>
                    <th style="min-width: 150px;">Code</th>
                    <th style="min-width: 400px;">Description</th>
                    <th style="min-width: 70px;">UoM</th>
                    <th style="min-width: 100px;">Quantity</th>
                    <th style="min-width: 120px;">Unit Price</th>
                    <th style="min-width: 120px;">Total Value</th>
                    <th style="min-width: 200px;">Remarks</th>
                    <th style="min-width: 100px;">Actions</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>

          <div class="text-right">
            <button
              type="submit"
              class="btn btn-primary btn-sm mr-2"
              :disabled="isSubmitting || form.items.length === 0"
            >
              <span
                v-if="isSubmitting"
                class="spinner-border spinner-border-sm mr-1"
              ></span>
              {{ isEditMode ? 'Update' : 'Create' }}
            </button>
            <a :href="indexRoute" class="btn btn-secondary btn-sm">Cancel</a>
          </div>
        </div>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick, watch, computed } from 'vue'
import axios from 'axios'
import { showAlert } from '@/Utils/bootbox'
import { initSelect2, destroySelect2 } from '@/Utils/select2'

const isSubmitting = ref(false)
const isImporting = ref(false)
const products = ref([])
const warehouses = ref([])
const warehouseSelect = ref(null)
const productSelect = ref(null)
const fileInput = ref(null)
const indexRoute = ref(window.route('stock-beginnings.index'))
const selectedFileName = ref('')
const isEditMode = ref(false)
const stockBeginningId = ref(null)
const table = ref(null)
let isAddingItem = false // Debounce flag to prevent rapid calls

const form = ref({
  warehouse_id: null,
  beginning_date: '',
  beginning_date_display: '',
  items: [],
})

const props = defineProps({
  initialData: {
    type: Object,
    default: () => ({}),
  },
})

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

const itemUnitName = computed(() => {
  return (productId) => {
    if (!productId) return '-'
    const product = products.value.find(p => p.id === productId)
    return product?.unit_name || '-'
  }
})

const ProductDescription = computed(() => {
  return (productId) => {
    if (!productId) return '-'
    const product = products.value.find(p => p.id === productId)
    return product ? `${product.product_name} ${product.description}` : '-'
  }
})

const ProductCode = computed(() => {
  return (productId) => {
    if (!productId) return '-'
    const product = products.value.find(p => p.id === productId)
    return product ? `${product.item_code}` : '-'
  }
})

const addItem = (productId) => {
  try {
    if (isAddingItem) {
      console.log('addItem skipped due to debounce')
      return
    }
    isAddingItem = true

    if (!productId) {
      console.warn('No product ID provided')
      showAlert('Warning', 'Please select a product.', 'warning')
      return
    }
    const product = products.value.find(p => p.id === Number(productId))
    if (!product) {
      console.error('Product not found for ID:', productId)
      showAlert('Error', 'Selected product not found.', 'danger')
      return
    }

    // Check for duplicate product
    const existingItemIndex = form.value.items.findIndex(item => item.product_id === Number(productId))
    if (existingItemIndex !== -1) {
      // Increment quantity for existing product
      form.value.items[existingItemIndex].quantity += 1
      if (table.value) {
        table.value.row(existingItemIndex).invalidate().draw()
      }
      showAlert('Info', `Quantity increased for ${product.item_code}`, 'info')
    } else {
      // Add new item
      const newItem = {
        product_id: Number(productId),
        quantity: 1,
        unit_price: 0,
        remarks: '',
      }
      form.value.items.push(newItem)
      if (table.value) {
        table.value.rows.add([newItem]).draw()
      } else {
        console.warn('DataTable not initialized')
        showAlert('Error', 'Table not initialized.', 'danger')
      }
    }

    if (productSelect.value) {
      $(productSelect.value).val(null).trigger('select2:unselect')
    }
  } catch (err) {
    console.error('Error adding item:', err)
    showAlert('Error', 'Failed to add product to table.', 'danger')
  } finally {
    isAddingItem = false
  }
}

const removeItem = (index) => {
  try {
    form.value.items.splice(index, 1)
    if (table.value) {
      table.value.clear().rows.add(form.value.items).draw()
    }
  } catch (err) {
    console.error('Error removing item:', err)
    showAlert('Error', 'Failed to remove item.', 'danger')
  }
}

const handleFileUpload = (event) => {
  try {
    const file = event.target.files[0]
    const validMimeTypes = [
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'application/vnd.ms-excel',
      'text/csv',
      'application/csv',
      'text/plain',
    ]
    if (file) {
      selectedFileName.value = file.name
      if (!validMimeTypes.includes(file.type)) {
        showAlert('Error', 'Please upload a valid Excel or CSV file (.xlsx, .xls, or .csv).', 'danger')
        fileInput.value.value = ''
        selectedFileName.value = ''
      }
    }
  } catch (err) {
    console.error('Error handling file upload:', err)
    showAlert('Error', 'Failed to process file upload.', 'danger')
  }
}

const triggerFileInput = () => {
  try {
    if (fileInput.value && typeof fileInput.value.click === 'function') {
      fileInput.value.click()
    }
  } catch (err) {
    console.error('Error triggering file input:', err)
    showAlert('Error', 'Failed to open file picker.', 'danger')
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

    if (response.status === 200 && response.data.data) {
      const importedData = response.data.data
      form.value.items = importedData.items.map(item => ({
        id: item.id || null, // Include id if provided by import
        product_id: Number(item.product_id),
        quantity: parseFloat(item.quantity) || 1,
        unit_price: parseFloat(item.unit_price) || 0,
        remarks: item.remarks || '',
      }))

      if (importedData.beginning_date) {
        const [year, month, day] = importedData.beginning_date.split('-')
        if (year && month && day) {
          const date = new Date(year, month - 1, day)
          const formattedDate = date.toLocaleDateString('en-US', {
            month: 'short',
            day: '2-digit',
            year: 'numeric',
          })
          form.value.beginning_date_display = formattedDate
          $('#beginning_date').datepicker('setDate', formattedDate)
        }
      }

      await nextTick()
      if (table.value) {
        table.value.clear().rows.add(form.value.items).draw()
      }

      if (warehouseSelect.value) {
        destroySelect2(warehouseSelect.value)
        initSelect2(warehouseSelect.value, {
          placeholder: 'Select Warehouse',
          width: '100%',
          allowClear: true,
        }, (v) => (form.value.warehouse_id = v))
        $(warehouseSelect.value).val(form.value.warehouse_id).trigger('change')
      }

      showAlert('Success', 'Stock beginnings data loaded into the form.', 'success')
      fileInput.value.value = ''
      selectedFileName.value = ''
      return
    }

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
  if (form.value.items.length === 0) {
    showAlert('Error', 'At least one item is required to submit.', 'danger')
    return
  }
  if (form.value.items.some(item => !item.product_id)) {
    showAlert('Error', 'All items must have a valid product selected.', 'danger')
    return
  }
  isSubmitting.value = true
  try {
    const payload = {
      warehouse_id: form.value.warehouse_id,
      beginning_date: form.value.beginning_date,
      items: form.value.items.map(item => ({
        id: item.id || null, // Include id for updates
        product_id: item.product_id,
        quantity: parseFloat(item.quantity),
        unit_price: parseFloat(item.unit_price),
        remarks: item.remarks?.toString().trim() || null,
      })),
    }

    const url = isEditMode.value
      ? window.route('stock-beginnings.update', { mainStockBeginning: stockBeginningId.value })
      : window.route('stock-beginnings.store')
    const method = isEditMode.value ? 'put' : 'post'

    await axios[method](url, payload)
    await showAlert('Success', isEditMode.value ? 'Stock beginning updated successfully.' : 'Stock beginning created successfully.', 'success')
    window.location.href = indexRoute.value
  } catch (err) {
    console.error('Submit error:', err.response?.data || err)
    showAlert('Error', err.response?.data?.message || err.message || 'Failed to save stock beginning.', 'danger')
  } finally {
    isSubmitting.value = false
  }
}

const initDatepicker = async () => {
  try {
    await nextTick()
    $('#beginning_date').datepicker({
      format: 'M dd, yyyy',
      autoclose: true,
      todayHighlight: true,
      orientation: 'bottom left',
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
          year: 'numeric',
        })
      } else {
        form.value.beginning_date = ''
        form.value.beginning_date_display = ''
      }
    })
  } catch (err) {
    console.error('Error initializing datepicker:', err)
    showAlert('Error', 'Failed to initialize date picker.', 'danger')
  }
}

watch(() => form.value.beginning_date_display, (newDisplayDate) => {
  try {
    if (newDisplayDate) {
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
    } else {
      form.value.beginning_date = ''
      $('#beginning_date').datepicker('setDate', null)
    }
  } catch (err) {
    console.error('Error watching beginning_date_display:', err)
    showAlert('Error', 'Failed to process date change.', 'danger')
  }
})

onMounted(async () => {
  try {
    const currentRoute = window.route().current()
    stockBeginningId.value = window.route().params.mainStockBeginning
    isEditMode.value = currentRoute === 'stock-beginnings.edit' && !!stockBeginningId.value

    if (isEditMode.value && props.initialData?.id) {
      form.value.warehouse_id = props.initialData.warehouse_id
      form.value.beginning_date = props.initialData.beginning_date
      if (props.initialData.beginning_date) {
        const [year, month, day] = props.initialData.beginning_date.split('-')
        const date = new Date(year, month - 1, day)
        form.value.beginning_date_display = date.toLocaleDateString('en-US', {
          month: 'short',
          day: '2-digit',
          year: 'numeric',
        })
      }
      form.value.items = props.initialData.items?.length
        ? props.initialData.items.map(item => ({
            id: item.id || null, // Include id for existing items
            product_id: Number(item.product_id),
            quantity: parseFloat(item.quantity) || 1,
            unit_price: parseFloat(item.unit_price) || 0,
            remarks: item.remarks || '',
          }))
        : []
    }

    await Promise.all([fetchProducts(), fetchWarehouses()])

    table.value = $('#stockItemsTable').DataTable({
      data: form.value.items,
      columns: [
        {
          data: 'product_id',
          render: (data) => ProductCode.value(data)
        },
        {
          data: 'product_id',
          render: (data) => ProductDescription.value(data)
        },
        {
          data: 'product_id',
          render: (data) => itemUnitName.value(data)
        },
        {
          data: 'quantity',
          render: (data, type, row, meta) => `
            <input type="number" class="form-control quantity-input" value="${data}" min="0.0001" step="0.0001" required data-row="${meta.row}"/>
          `
        },
        {
          data: 'unit_price',
          render: (data, type, row, meta) => `
            <input type="number" class="form-control unit-price-input" value="${data}" min="0" step="0.0001" required data-row="${meta.row}"/>
          `
        },
        {
          data: null,
          render: (data) => (data.quantity * data.unit_price).toFixed(4)
        },
        {
          data: 'remarks',
          render: (data, type, row, meta) => `
            <textarea class="form-control remarks-input" rows="1" maxlength="1000" data-row="${meta.row}">${data || ''}</textarea>
          `
        },
        {
          data: null,
          orderable: false,
          searchable: false,
          render: (data, type, row, meta) => `
            <button type="button" class="btn btn-danger btn-sm remove-btn" data-row="${meta.row}">
              <i class="fal fa-trash-alt"></i> Remove
            </button>
          `
        }
      ]
    })

    // Event delegation for input changes
    $('#stockItemsTable').on('change', '.quantity-input', function () {
      try {
        const index = $(this).data('row')
        form.value.items[index].quantity = parseFloat($(this).val()) || 1
        table.value.row(index).invalidate().draw()
      } catch (err) {
        console.error('Error updating quantity:', err)
        showAlert('Error', 'Failed to update quantity.', 'danger')
      }
    })

    $('#stockItemsTable').on('change', '.unit-price-input', function () {
      try {
        const index = $(this).data('row')
        form.value.items[index].unit_price = parseFloat($(this).val()) || 0
        table.value.row(index).invalidate().draw()
      } catch (err) {
        console.error('Error updating unit price:', err)
        showAlert('Error', 'Failed to update unit price.', 'danger')
      }
    })

    $('#stockItemsTable').on('input', '.remarks-input', function () {
      try {
        const index = $(this).data('row')
        form.value.items[index].remarks = $(this).val()
      } catch (err) {
        console.error('Error updating remarks:', err)
        showAlert('Error', 'Failed to update remarks.', 'danger')
      }
    })

    $('#stockItemsTable').on('click', '.remove-btn', function () {
      try {
        const index = $(this).data('row')
        removeItem(index)
      } catch (err) {
        console.error('Error removing item:', err)
        showAlert('Error', 'Failed to remove item.', 'danger')
      }
    })

    await nextTick()
    if (warehouseSelect.value) {
      initSelect2(warehouseSelect.value, {
        placeholder: 'Select Warehouse',
        width: '100%',
        allowClear: true,
      }, (v) => (form.value.warehouse_id = v))
      if (form.value.warehouse_id) {
        $(warehouseSelect.value).val(form.value.warehouse_id).trigger('change')
      }
    }

    if (productSelect.value) {
      initSelect2(productSelect.value, {
        placeholder: 'Select Product',
        width: '100%',
        allowClear: true,
      })
      $(productSelect.value).on('select2:select', (e) => {
        const productId = e.params.data.id
        console.log('Selected product ID:', productId)
        addItem(productId)
      })
    } else {
      console.warn('productSelect ref is not defined')
      showAlert('Error', 'Product selection dropdown not initialized.', 'danger')
    }

    initDatepicker()
    if (isEditMode.value && form.value.beginning_date_display) {
      $('#beginning_date').datepicker('setDate', form.value.beginning_date_display)
    }
  } catch (err) {
    console.error('Error in onMounted:', err)
    showAlert('Error', 'Failed to initialize form.', 'danger')
  }
})

onUnmounted(() => {
  try {
    if (warehouseSelect.value) destroySelect2(warehouseSelect.value)
    if (productSelect.value) {
      $(productSelect.value).off('select2:select')
      destroySelect2(productSelect.value)
    }
    if (table.value) table.value.destroy()
    $('#beginning_date').datepicker('destroy')
  } catch (err) {
    console.error('Error in onUnmounted:', err)
  }
})
</script>