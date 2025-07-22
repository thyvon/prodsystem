<template>
  <div>
    <datatable
      ref="datatableRef"
      :headers="datatableHeaders"
      :fetch-url="datatableFetchUrl"
      :fetch-params="datatableParams"
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

// Refs and state
const datatableRef = ref(null)
const pageLength = ref(10)

// Datatable configuration
const datatableParams = reactive({
  sortColumn: 'created_at',
  sortDirection: 'desc',
})

const datatableHeaders = [
  { text: 'Image', value: 'image', width: '8%', sortable: false },
  { text: 'Item Code', value: 'item_code', width: '8%', sortable: true },
  { text: 'EN Name', value: 'product_name', width: '20%', sortable: true },
  { text: 'KH Name', value: 'product_khmer_name', width: '20%', sortable: true },
  { text: 'Spec', value: 'description', width: '10%', sortable: true },
  { text: 'Category', value: 'category_name', width: '10%', sortable: false },
  { text: 'Sub-Category', value: 'sub_category_name', width: '10%', sortable: false },
  { text: 'UoM', value: 'unit_name', width: '10%', sortable: false },
  { text: 'Active', value: 'is_active', width: '5%', sortable: true },
  { text: 'Created By', value: 'created_by', width: '10%', sortable: true },
  { text: 'Created', value: 'created_at', width: '10%', sortable: true }
]

const datatableFetchUrl = '/api/inventory/items'
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
</script>