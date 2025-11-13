<template>
  <div>
    <!-- Datatable -->
    <datatable
      ref="datatableRef"
      :headers="datatableHeaders"
      :fetch-url="datatableFetchUrl"
      :fetch-params="datatableParams"
      :actions="datatableActions"
      :handlers="datatableHandlers"
      :options="datatableOptions"
      @sort-change="handleSortChange"
      @page-change="handlePageChange"
      @length-change="handleLengthChange"
      @search-change="handleSearchChange"
    >
      <template #cell-is_active="{ value }">
        <span :class="value ? 'badge badge-success' : 'badge badge-secondary'">
          {{ value ? 'Active' : 'Inactive' }}
        </span>
      </template>
    </datatable>

    <!-- BaseModal for stock_by_campus -->
    <BaseModal
      v-model="showModal"
      id="stockByCampusModal"
      title="Stock by Campus"
      size="lg"
    >
      <template #body>
        <table class="table table-sm table-bordered table-hover">
          <thead class="thead-light">
            <tr>
              <th>Warehouse</th>
              <th class="text-right">Stock On Hand</th>
              <th class="text-right">Average Price</th>
              <th class="text-right">Total Value</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!modalStockByCampus.length">
              <td colspan="4" class="text-center text-muted">No stock available</td>
            </tr>
            <tr v-for="stock in modalStockByCampus" :key="stock.warehouse_id">
              <td>{{ stock.warehouse_name }}</td>
              <td class="text-right">{{ stock.stock_on_hand }}</td>
              <td class="text-right">{{ stock.average_price.toFixed(2) }}</td>
              <td class="text-right">{{ stock.total_cost.toFixed(2) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <th class="text-right" colspan="3">Total Value:</th>
              <th class="text-right">{{ totalCost.toFixed(2) }}</th>
            </tr>
          </tfoot>
        </table>
      </template>
    </BaseModal>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import BaseModal from '@/components/Reusable/BaseModal.vue'

// Refs and state
const datatableRef = ref(null)
const pageLength = ref(10)
const showModal = ref(false)
const modalStockByCampus = ref([])

// Compute total cost
const totalCost = computed(() =>
  modalStockByCampus.value.reduce((sum, stock) => sum + (stock.total_cost || 0), 0)
)

// Datatable configuration
const datatableParams = reactive({
  sortColumn: 'created_at',
  sortDirection: 'desc',
})

const datatableHeaders = [
  { text: 'Image', value: 'image', width: '8%', sortable: false },
  { text: 'Item Code', value: 'item_code', width: '8%', sortable: true },
  { text: 'Description', value: 'description', width: '30%', sortable: true },
  { text: 'Category', value: 'category_name', width: '10%', sortable: false },
  { text: 'Sub-Category', value: 'sub_category_name', width: '10%', sortable: false },
  { text: 'UoM', value: 'unit_name', width: '10%', sortable: false },
  { text: 'Stock', value: 'stock_on_hand', width: '5%', sortable: true },
  { text: 'Price', value: 'average_price', width: '5%', sortable: true },
  { text: 'Active', value: 'is_active', width: '5%', sortable: true },
  { text: 'Created', value: 'created_at', width: '10%', sortable: true }
]

const datatableFetchUrl = '/api/inventory/items'
const datatableActions = ['preview']
const datatableOptions = {
  responsive: true,
  pageLength: pageLength.value,
  lengthMenu: [[10, 15, 20, 50, 100], [10, 15, 20, 50, 100]],
}

// Datatable event handlers
const handleSortChange = ({ column, direction }) => {
  datatableParams.sortColumn = column
  datatableParams.sortDirection = direction
}

const handlePageChange = (page) => {
  datatableParams.page = page
}

const handleLengthChange = (length) => {
  datatableParams.limit = length
}

const handleSearchChange = (search) => {
  datatableParams.search = search
}

// Open modal with stock details
const handlePreview = (row) => {
  modalStockByCampus.value = row.stock_by_campus || []
  showModal.value = true
}

const datatableHandlers = {
  preview: handlePreview,
}
</script>
