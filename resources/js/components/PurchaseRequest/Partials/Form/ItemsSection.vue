<template>
  <div class="border rounded p-3 mb-4">
    <div class="form-row mb-3">
      <div class="form-group col-md-4">
        <label class="font-weight-bold">📥 Import Items</label>
        <div class="input-group">
          <input
            type="file"
            class="d-none"
            ref="importFileInput"
            accept=".xlsx,.xls,.csv"
          />

          <button
            type="button"
            class="btn btn-outline-secondary flex-fill"
            @click="importFileInput?.click()"
          >
            Choose file
          </button>

          <button
            type="button"
            class="btn btn-primary ml-2"
            @click="$emit('import')"
            :disabled="isImporting || !importFileInput?.files?.length"
          >
            <span v-if="isImporting" class="spinner-border spinner-border-sm mr-1"></span>
            Import
          </button>

          <a
            class="btn btn-success ml-2"
            href="/sampleExcel/purchase_request_item_sample.xlsx"
            download
          >
            <i class="fal fa-file-excel"></i>
          </a>
        </div>
      </div>

      <div class="form-group col-md-8">
        <label class="font-weight-bold">➕ Add Product</label>
        <button
          type="button"
          class="btn btn-primary btn-block"
          @click="$emit('open-products')"
          :disabled="isLoadingProducts"
        >
          <span v-if="isLoadingProducts" class="spinner-border spinner-border-sm mr-2"></span>
          <i class="fal fa-plus"></i> Select Product
        </button>
      </div>
    </div>

    <h5 class="font-weight-bold mb-3 text-primary">
      📦 Items ({{ form.items.length }})
      <span v-if="totalAmount" class="badge badge-primary ml-2">{{ totalAmount }}</span>
    </h5>

    <div class="table-responsive" style="max-height: 700px; overflow-y: auto;">
      <table class="table table-bordered table-striped table-sm table-hover">
        <thead style="position: sticky; top: 0; background: #1E90FF; z-index: 10; text-align: center;">
          <tr>
            <th style="min-width: 120px;">Item Code</th>
            <th class="d-none d-md-table-cell" style="min-width: 200px;">Description</th>
            <th style="min-width: 60px;">UoM</th>
            <th class="d-none d-md-table-cell" style="min-width: 140px;">Remarks</th>
            <th style="min-width: 90px;">Currency</th>
            <th class="d-none d-md-table-cell" style="min-width: 80px;">Ex. Rate</th>
            <th style="min-width: 60px;">Qty</th>
            <th style="min-width: 90px;">Price</th>
            <th class="d-none d-lg-table-cell" style="min-width: 80px;">Value USD</th>
            <th class="d-none d-lg-table-cell" style="min-width: 120px;">Budget</th>
            <th class="d-none d-lg-table-cell" style="min-width: 100px;">Campus</th>
            <th class="d-none d-lg-table-cell" style="min-width: 100px;">Dept</th>
            <th style="min-width: 60px;">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(item, index) in form.items" :key="index">
            <td>{{ item.product_code }}</td>
            <td class="d-none d-md-table-cell">{{ item.product_description }}</td>
            <td>{{ item.unit_name }}</td>
            <td class="d-none d-md-table-cell">
              <textarea
                :name="`items[${index}][description]`"
                v-model="item.description"
                class="form-control form-control-sm"
              ></textarea>
            </td>
            <td>
              <select
                :name="`items[${index}][currency]`"
                v-model="item.currency"
                class="form-control form-control-sm"
              >
                <option value="">Select</option>
                <option value="USD">USD</option>
                <option value="KHR">KHR</option>
              </select>
            </td>
            <td class="d-none d-md-table-cell">
              <input
                type="number"
                :name="`items[${index}][exchange_rate]`"
                v-model.number="item.exchange_rate"
                class="form-control form-control-sm"
              />
            </td>
            <td>
              <input
                type="number"
                :name="`items[${index}][quantity]`"
                v-model.number="item.quantity"
                class="form-control form-control-sm"
              />
            </td>
            <td>
              <input
                type="number"
                :name="`items[${index}][unit_price]`"
                v-model.number="item.unit_price"
                class="form-control form-control-sm"
              />
            </td>
            <td class="d-none d-lg-table-cell">
              <input
                type="text"
                class="form-control form-control-sm"
                :value="formatItemValueUsd(item)"
                readonly
              />
            </td>
            <td class="d-none d-lg-table-cell">
              <select
                class="form-control budget-select"
                :data-index="index"
              ></select>
            </td>
            <td class="d-none d-lg-table-cell">
              <select
                multiple
                class="form-control campus-select"
                :data-index="index"
              ></select>
            </td>
            <td class="d-none d-lg-table-cell">
              <select
                multiple
                class="form-control department-select"
                :data-index="index"
              ></select>
            </td>
            <td class="text-center">
              <button
                @click.prevent="$emit('remove-item', index)"
                class="btn btn-danger btn-sm"
              >
                <i class="fal fa-trash"></i>
              </button>
            </td>
          </tr>
          <tr v-if="!form.items.length">
            <td colspan="13" class="text-center text-muted py-4">No items added</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

defineProps({
  form: { type: Object, required: true },
  totalAmount: { type: String, default: null },
  isImporting: { type: Boolean, required: true },
  isLoadingProducts: { type: Boolean, required: true },
  formatItemValueUsd: { type: Function, required: true },
})

defineEmits(['import', 'open-products', 'remove-item'])

const importFileInput = ref(null)
defineExpose({ importFileInput })
</script>
