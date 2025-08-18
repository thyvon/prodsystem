<template>
  <div>
    <datatable
      v-if="datatableHeaders.length"
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
      <!-- Image column -->
      <template #cell-image="{ value }">
        <img v-if="value" :src="value" class="img-thumbnail" style="max-width:50px; max-height:50px;" />
        <span v-else>-</span>
      </template>

      <!-- Active status -->
      <template #cell-is_active="{ value }">
        <span :class="value ? 'badge badge-success' : 'badge badge-secondary'">
          {{ value ? 'Active' : 'Inactive' }}
        </span>
      </template>

      <!-- Total stock -->
      <template #cell-total_stock="{ row }">
        {{ row.total_stock ?? 0 }}
      </template>

      <!-- Dynamic warehouse stock -->
      <template
        v-for="wh in warehouses"
        :key="wh.id"
        #[`cell-warehouse_${wh.id}`]="{ row }"
      >
        {{ row[`warehouse_${wh.id}`] ?? 0 }}
      </template>
    </datatable>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from 'vue'
import axios from 'axios'

const datatableRef = ref(null)
const pageLength = ref(10)
const warehouses = ref([])

const datatableParams = reactive({
  sortColumn: 'created_at',
  sortDirection: 'desc',
  search: '',
  page: 1,
  limit: pageLength.value,
  cutoff_date: null, // optional cutoff date
})

const datatableHeaders = ref([])
const datatableFetchUrl = '/api/inventory/stock-onhand'

const datatableOptions = {
  responsive: true,
  pageLength: pageLength.value,
  lengthMenu: [[10, 15, 20, 50, 100], [10, 15, 20, 50, 100]],
}

// Initialize datatable headers dynamically
const initDatatable = async () => {
  try {
    const { data } = await axios.get(datatableFetchUrl, { params: { limit: 1 } })

    // Map warehouses from backend
    warehouses.value = Object.keys(data.warehouses || {}).map(id => ({
      id: id.toString(),
      name: data.warehouses[id]
    }))

    // Static headers
    const staticHeaders = [
      { text: 'Image', value: 'image', width: '8%', sortable: false },
      { text: 'Item Code', value: 'item_code', width: '8%', sortable: true },
      { text: 'EN Name', value: 'product_name', width: '15%', sortable: true },
      { text: 'KH Name', value: 'product_khmer_name', width: '15%', sortable: true },
      { text: 'Spec', value: 'description', width: '15%', sortable: true },
      { text: 'Category', value: 'category_name', width: '10%', sortable: false },
      { text: 'Sub-Category', value: 'sub_category_name', width: '10%', sortable: false },
      { text: 'Total Stock', value: 'total_stock', width: '10%', sortable: false },
      { text: 'UoM', value: 'unit_name', width: '8%', sortable: false },
      { text: 'Active', value: 'is_active', width: '5%', sortable: true },
      { text: 'Created By', value: 'created_by', width: '10%', sortable: true },
      { text: 'Created', value: 'created_at', width: '10%', sortable: true },
    ]

    // Dynamic warehouse headers
    const warehouseHeaders = warehouses.value.map(wh => ({
      text: wh.name,
      value: `warehouse_${wh.id}`,
      width: '8%',
      sortable: false
    }))

    datatableHeaders.value = [...staticHeaders, ...warehouseHeaders]
  } catch (err) {
    console.error('Error initializing datatable headers:', err)
  }
}

// Fetch data from backend
const fetchData = async () => {
  try {
    const { data } = await axios.get(datatableFetchUrl, { params: datatableParams })

    // Update warehouses if backend returns updated list
    if (data.warehouses) {
      warehouses.value = Object.keys(data.warehouses).map(id => ({
        id: id.toString(),
        name: data.warehouses[id]
      }))
    }

    // Push data into datatable
    datatableRef.value?.setData?.(data.rows || [], data.recordsTotal || (data.rows || []).length)
  } catch (err) {
    console.error('Error fetching stock data:', err)
  }
}

onMounted(async () => {
  await initDatatable()
  await fetchData()
})

// Watch for parameter changes and reload
watch(datatableParams, fetchData, { deep: true })

// Event handlers
const handleSortChange = ({ column, direction }) => { datatableParams.sortColumn = column; datatableParams.sortDirection = direction }
const handlePageChange = (page) => { datatableParams.page = page }
const handleLengthChange = (length) => { datatableParams.limit = length }
const handleSearchChange = (search) => { datatableParams.search = search }
</script>
