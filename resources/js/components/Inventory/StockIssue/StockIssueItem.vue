<template>
  <div>
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
      <template #additional-header>
        <div class="d-flex align-items-center">

          <!-- Multi-select Warehouse -->
          <select ref="warehouseSelect" class="form-control mr-2" multiple></select>

          <!-- Apply Filter Button -->
          <button class="btn btn-primary" @click="applyWarehouseFilter">
            <i class="fal fa-filter"></i> Apply Filter
          </button>

        </div>
      </template>
    </datatable>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from 'vue'
import { initSelect2, destroySelect2 } from '@/Utils/select2.js'

const datatableRef = ref(null)

const datatableParams = reactive({
  sortColumn: 'id',
  sortDirection: 'desc',
  search: '',
  warehouse_ids: [], // Multi WH filter
})

const warehouseList = ref([
  { id: 1, name: 'Main Warehouse' },
  { id: 2, name: 'Campus Warehouse' },
  { id: 3, name: 'Book Warehouse' },
])

const warehouseSelect = ref(null)
const selectedWarehouses = ref([])

/* Initialize Select2 */
onMounted(() => {
  initSelect2(warehouseSelect.value, {
    placeholder: 'Select Warehouses',
    width: '20px',
    allowClear: true,
    data: warehouseList.value.map(w => ({ id: w.id, text: w.name })),
  }, (value) => {
    selectedWarehouses.value = value
  })
})

/* Watch warehouseList if dynamic */
watch(warehouseList, (newList) => {
  destroySelect2(warehouseSelect.value)
  initSelect2(warehouseSelect.value, {
    placeholder: 'Select Warehouses',
    width: '220px',
    allowClear: true,
    data: newList.map(w => ({ id: w.id, text: w.name })),
  }, (value) => {
    selectedWarehouses.value = value
  })
})

/* Apply Filter */
const applyWarehouseFilter = () => {
  datatableParams.warehouse_ids = selectedWarehouses.value
  datatableRef.value.reload()
}

/* Datatable configuration */
const datatableHeaders = [
  { text: 'Date', value: 'transaction_date' },
  { text: 'Issue No', value: 'stock_issue_reference' },
  { text: 'Warehouse', value: 'warehouse_name' },
  { text: 'Product Code', value: 'product_code' },
  { text: 'Description', value: 'description' },
  { text: 'Quantity', value: 'quantity' },
  { text: 'Unit', value: 'unit_name' },
  { text: 'Unit Price', value: 'unit_price' },
  { text: 'Total Price', value: 'total_price' },
  { text: 'Requester', value: 'requester_name' },
  { text: 'Campus', value: 'campus_name' },
  { text: 'Division', value: 'division_name' },
  { text: 'Department', value: 'department_name' },
  { text: 'Purpose', value: 'purpose' },
  { text: 'Transaction Type', value: 'transaction_type' },
  { text: 'Remarks', value: 'remarks' },
]

const datatableFetchUrl = '/api/inventory/stock-issue/items'
const datatableActions = []
const datatableOptions = { autoWidth: false, responsive: true, scrollX: true, pageLength: 10 }
const datatableHandlers = {}
const handleSortChange = ({ column, direction }) => { datatableParams.sortColumn = column; datatableParams.sortDirection = direction }
const handleSearchChange = (search) => { datatableParams.search = search }
</script>
