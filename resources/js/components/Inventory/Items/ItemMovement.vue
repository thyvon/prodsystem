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
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import axios from 'axios'

// Refs and state
const datatableRef = ref(null)
const pageLength = ref(10)

// Datatable configuration
const datatableParams = reactive({
  sortColumn: 'movement_date',
  sortDirection: 'desc',
})

const datatableHeaders = [
  { text: 'Item Code', value: 'item_code', width: '10%', sortable: true },
  { text: 'EN Name', value: 'product_name', width: '15%', sortable: true },
  { text: 'KH Name', value: 'product_khmer_name', width: '15%', sortable: true },
  { text: 'Date', value: 'movement_date', width: '10%', sortable: true },
  { text: 'Type', value: 'movement_type', width: '10%', sortable: true },
  { text: 'Warehouse', value: 'warehouse_name', width: '10%', sortable: true },
  { text: 'Qty', value: 'quantity', width: '5%', sortable: true, align: 'right' },
  { text: 'Unit Price', value: 'unit_price', width: '8%', sortable: true, align: 'right' },
  { text: 'VAT', value: 'vat', width: '5%', sortable: true, align: 'right' },
  { text: 'Discount', value: 'discount', width: '5%', sortable: true, align: 'right' },
  { text: 'Delivery Fee', value: 'delivery_fee', width: '5%', sortable: true, align: 'right' },
  { text: 'Running Qty', value: 'running_qty', width: '5%', sortable: true, align: 'right' },
  { text: 'Running Value', value: 'running_value', width: '8%', sortable: true, align: 'right' },
  { text: 'WAP', value: 'running_wap', width: '5%', sortable: true, align: 'right' }
]

const datatableFetchUrl = '/api/inventory/stock-movements'
const datatableActions = [] // no modal preview needed
const datatableOptions = {
  responsive: true,
  pageLength: pageLength.value,
  lengthMenu: [[10, 15, 20, 50, 100], [10, 15, 20, 50, 100]]
}

// Event handlers
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

const datatableHandlers = {}
</script>
