<template>
  <BaseModal
    v-model="showModal"
    id="warehouseProductModal"
    title="Edit Warehouse Product"
    size="xl"
    :loading="isLoading"
  >
    <template #body>
      <form @submit.prevent="submitForm">
        <div class="table-responsive">
          <table class="table table-bordered mb-0">
            <tbody>
              <tr>
                <th class="w-50">Product Code</th>
                <td>{{ form.variant_item_code }}</td>
              </tr>
              <tr>
                <th class="w-50">Product Description</th>
                <td>{{ form.product_name }}</td>
              </tr>
              <tr>
                <th>Warehouse</th>
                <td>{{ form.warehouse_name }}</td>
              </tr>
              <tr>
                <th>Alert Quantity <span class="text-danger">*</span></th>
                <td>
                  <input
                    type="number"
                    v-model.number="form.alert_quantity"
                    class="form-control"
                    style="width: 100%; height: 100%;"
                    required
                    min="0.0001"
                  />
                </td>
              </tr>
              <tr>
                <th>Order Leadtime Days</th>
                <td>
                  <input
                    type="number"
                    v-model.number="form.order_leadtime_days"
                    class="form-control"
                    style="width: 100%; height: 100%;"
                    min="0"
                  />
                </td>
              </tr>
              <tr>
                <th>Stock Out Forecast Days</th>
                <td>
                  <input
                    type="number"
                    v-model.number="form.stock_out_forecast_days"
                    class="form-control"
                    style="width: 100%; height: 100%;"
                    min="0"
                  />
                </td>
              </tr>
              <tr>
                <th>Target Inventory Turnover Days</th>
                <td>
                  <input
                    type="number"
                    v-model.number="form.target_inv_turnover_days"
                    class="form-control"
                    style="width: 100%; height: 100%;"
                    min="0"
                  />
                </td>
              </tr>
              <tr>
                <th>Active Status</th>
                <td>
                  <div class="custom-control custom-checkbox">
                    <input
                      type="checkbox"
                      class="custom-control-input"
                      id="isActive"
                      v-model="form.is_active"
                      :true-value="1"
                      :false-value="0"
                    />
                    <label class="custom-control-label" for="isActive">Active</label>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </form>
    </template>

    <template #footer>
      <button type="button" class="btn btn-secondary" @click="hideModal">Cancel</button>
      <button
        type="submit"
        class="btn btn-primary"
        @click="submitForm"
        :disabled="isSubmitting"
      >
        <span v-if="isSubmitting" class="spinner-border spinner-border-sm mr-1"></span>
        Update
      </button>
    </template>
  </BaseModal>
</template>

<script setup>
import { ref, nextTick } from 'vue'
import axios from 'axios'
import BaseModal from '@/components/Reusable/BaseModal.vue'
import { showAlert } from '@/Utils/bootbox'

const emit = defineEmits(['submitted'])

const showModal = ref(false)
const isSubmitting = ref(false)
const isLoading = ref(false)

const form = ref({
  id: null,
  product_id: null,
  variant_item_code: '',
  product_name: '',
  warehouse_id: null,
  warehouse_name: '',
  alert_quantity: 0,
  order_leadtime_days: 0,
  stock_out_forecast_days: 0,
  target_inv_turnover_days: 0,
  is_active: 1,
})

/**
 * Show the modal and fetch the warehouse product data from the backend
 * @param {Number} warehouseProductId
 */
const show = async (warehouseProductId) => {
  isLoading.value = true
  try {
    const response = await axios.get(`/api/inventory/warehouses/products/${warehouseProductId}/edit`)
    const warehouseProduct = response.data.data

    form.value = {
      id: warehouseProduct.id,
      product_id: warehouseProduct.variant_id,
      variant_item_code: warehouseProduct.variant_item_code,
      product_name: warehouseProduct.product_name,
      warehouse_id: warehouseProduct.warehouse_id,
      warehouse_name: warehouseProduct.warehouse_name,
      alert_quantity: warehouseProduct.alert_quantity,
      order_leadtime_days: warehouseProduct.order_leadtime_days || 0,
      stock_out_forecast_days: warehouseProduct.stock_out_forecast_days || 0,
      target_inv_turnover_days: warehouseProduct.target_inv_turnover_days || 0,
      is_active: warehouseProduct.is_active !== undefined ? warehouseProduct.is_active : 1,
    }

    await nextTick()
    showModal.value = true
  } catch (err) {
    console.error(err)
    showAlert('Error', 'Failed to fetch warehouse product data.', 'danger')
  } finally {
    isLoading.value = false
  }
}

const hideModal = () => {
  showModal.value = false
}

const submitForm = async () => {
  if (isSubmitting.value) return
  isSubmitting.value = true

  try {
    await axios.put(`/api/inventory/warehouses/products/${form.value.id}/update`, {
      alert_quantity: form.value.alert_quantity,
      order_leadtime_days: form.value.order_leadtime_days,
      stock_out_forecast_days: form.value.stock_out_forecast_days,
      target_inv_turnover_days: form.value.target_inv_turnover_days,
      is_active: form.value.is_active ? 1 : 0,
    })

    emit('submitted')
    hideModal()
    await showAlert('Success', 'Warehouse product updated successfully.', 'success')
  } catch (err) {
    console.error(err.response?.data || err)
    showAlert('Error', err.response?.data?.message || 'Failed to update warehouse product.', 'danger')
  } finally {
    isSubmitting.value = false
  }
}

defineExpose({ show })
</script>
