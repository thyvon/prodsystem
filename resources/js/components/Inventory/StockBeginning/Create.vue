<template>
  <div class="container-fluid mt-3">
    <div class="card border mb-0">
      <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
        <h4 class="mb-0 font-weight-bold">Create Stock Beginning</h4>
        <a :href="indexRoute" class="btn btn-secondary btn-sm"><i class="fal fa-arrow-left"></i> Back to List</a>
      </div>
      <div class="card-body">
        <form @submit.prevent="submitForm">
          <!-- Header Information -->
          <div class="form-row">
            <div class="form-group col-md-6">
              <label for="reference_no" class="font-weight-bold">Reference No</label>
              <input
                v-model="form.reference_no"
                type="text"
                class="form-control"
                id="reference_no"
                required
              />
            </div>
            <div class="form-group col-md-6">
              <label for="warehouse_id" class="font-weight-bold">Warehouse</label>
              <select
                ref="warehouseSelect"
                v-model="form.warehouse_id"
                class="form-control"
                id="warehouse_id"
                required
              >
                <option value="">Select Warehouse</option>
                <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">
                  {{ warehouse.name }}
                </option>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-6">
              <label for="beginning_date" class="font-weight-bold">Beginning Date</label>
              <input
                v-model="form.beginning_date"
                type="date"
                class="form-control"
                id="beginning_date"
                required
              />
            </div>
          </div>

          <!-- Line Items -->
          <div class="mt-4">
            <h5 class="font-weight-bold">Line Items</h5>
            <div class="table-responsive">
              <table class="table table-bordered">
                <thead>
                  <tr>
                    <th style="min-width: 300px;">Product</th>
                    <th style="min-width: 100px;">Quantity</th>
                    <th style="min-width: 120px;">Unit Price</th>
                    <th style="min-width: 120px;">Total Value</th>
                    <th style="min-width: 200px;">Remarks</th>
                    <th style="min-width: 100px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, index) in form.items" :key="index">
                    <td style="min-width: 300px;">
                      <select
                        v-model="item.product_id"
                        class="form-control"
                        :id="'product_id_' + index"
                        required
                      >
                        <option value="">Select Product</option>
                        <option v-for="product in products" :key="product.id" :value="product.id">
                          ({{ product.item_code }}) - {{ product.product_name }} {{ product.description }} 
                        </option>
                      </select>
                    </td>
                    <td style="min-width: 100px;">
                      <input
                        v-model.number="item.quantity"
                        type="number"
                        class="form-control"
                        min="0.01"
                        step="0.01"
                        required
                      />
                    </td>
                    <td style="min-width: 120px;">
                      <input
                        v-model.number="item.unit_price"
                        type="number"
                        class="form-control"
                        min="0"
                        step="0.01"
                        required
                      />
                    </td>
                    <td style="min-width: 120px;">
                      {{ (item.quantity * item.unit_price).toFixed(2) }}
                    </td>
                    <td style="min-width: 200px;">
                      <textarea
                        v-model="item.remarks"
                        class="form-control"
                        rows="2"
                        maxlength="1000"
                      ></textarea>
                    </td>
                    <td style="min-width: 100px;">
                      <button
                        type="button"
                        class="btn btn-danger btn-sm"
                        @click="removeItem(index)"
                      >
                        Remove
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
              Add Item
            </button>
          </div>

          <!-- Form Actions -->
          <div class="form-group mt-4">
            <button
              type="submit"
              class="btn btn-primary btn-sm mr-2"
              :disabled="isSubmitting"
            >
              <span v-if="isSubmitting" class="spinner-border spinner-border-sm mr-1"></span>
              Create
            </button>
            <a :href="indexRoute" class="btn btn-secondary btn-sm">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue'
import axios from 'axios'
import { showAlert } from '@/Utils/bootbox'
import { initSelect2, destroySelect2 } from '@/Utils/select2'

const isSubmitting = ref(false)
const products = ref([])
const warehouses = ref([])
const warehouseSelect = ref(null)
const indexRoute = ref(window.route('stockBeginnings.index'))

const form = ref({
  reference_no: '',
  warehouse_id: null,
  beginning_date: '',
  items: [
    {
      product_id: null,
      quantity: 1,
      unit_price: 0,
      remarks: '',
    },
  ],
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

const addItem = async () => {
  form.value.items.push({
    product_id: null,
    quantity: 1,
    unit_price: 0,
    remarks: '',
  })

  // Wait for the DOM to update with the new item
  await nextTick()

  // Initialize Select2 for the new product's select element
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

const submitForm = async () => {
  if (isSubmitting.value) return
  isSubmitting.value = true
  try {
    const payload = {
      reference_no: form.value.reference_no?.toString().trim(),
      warehouse_id: form.value.warehouse_id,
      beginning_date: form.value.beginning_date,
      items: form.value.items.map(item => ({
        product_id: item.product_id,
        quantity: item.quantity,
        unit_price: item.unit_price,
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
})

onUnmounted(() => {
  if (warehouseSelect.value) destroySelect2(warehouseSelect.value)
  form.value.items.forEach((_, index) => {
    const select = document.getElementById(`product_id_${index}`)
    if (select) destroySelect2(select)
  })
})
</script>
