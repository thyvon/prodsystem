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
      :scrollable="true"
      @sort-change="handleSortChange"
      @page-change="handlePageChange"
      @length-change="handleLengthChange"
      @search-change="handleSearchChange"
    >
      <template #additional-header>
        <div class="d-flex flex-column mb-2">

          <!-- Top Row: Route Button + Filters Toggle -->
          <div class="d-flex mb-2 align-items-center">
            <!-- Route to Stock In List -->
            <button class="btn btn-success mr-2" @click="goToStockInList">
              <i class="fal fa-list mr-1"></i> Stock In List
            </button>

            <!-- Toggle Button for Collapse -->
            <button 
              class="btn btn-info"
              type="button"
              data-toggle="collapse"
              data-target="#filterCollapse"
              aria-expanded="false"
              aria-controls="filterCollapse"
            >
              <i class="fal fa-filter mr-2"></i> Filters
            </button>
          </div>

          <!-- Collapsible Filter Section -->
          <div class="collapse" id="filterCollapse">
            <!-- Row 1: Filter fields -->
            <div class="d-flex align-items-center mb-2">
              <input type="text" ref="startDateRef" class="form-control mr-2" placeholder="Start Date" />
              <input type="text" ref="endDateRef" class="form-control mr-2" placeholder="End Date" />
              <select ref="warehouseSelect" class="form-control" multiple></select>
            </div>

            <!-- Row 2: Apply button aligned right -->
            <div class="d-flex justify-content-end mb-2">
              <button class="btn btn-primary d-flex align-items-center" @click="applyFilters">
                <i class="fal fa-filter mr-2"></i> Apply
              </button>
            </div>
          </div>

        </div>
      </template>
    </datatable>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, nextTick } from 'vue'
import axios from 'axios'
import { initSelect2, destroySelect2 } from '@/Utils/select2.js'

const datatableRef = ref(null)
const warehouseSelect = ref(null)
const startDateRef = ref(null)
const endDateRef = ref(null)
const selectedWarehouses = ref([])

const datatableParams = reactive({
  sortColumn: 'id',
  sortDirection: 'desc',
  search: '',
  warehouse_ids: [],
  start_date: null,
  end_date: null,
})
const datatableHeaders = [
  { text: 'Date', value: 'transaction_date', minWidth: '60px' },
  { text: 'Reference', value: 'stock_in_reference', minWidth: '100px' },
  { text: 'Warehouse', value: 'warehouse_name', minWidth: '80px' },
  { text: 'Product Code', value: 'product_code', minWidth: '80px' },
  { text: 'Description', value: 'description', minWidth: '300px' },
  { text: 'Quantity', value: 'quantity', minWidth: '10px' },
  { text: 'Unit', value: 'unit_name', minWidth: '10px' },
  { text: 'Price', value: 'unit_price', minWidth: '10px' },
  { text: 'Total', value: 'total_price', minWidth: '10px' },
  { text: 'Campus', value: 'campus_name', minWidth: '10px' },
  { text: 'Supplier', value: 'supplier_name', minWidth: '150px' },
  { text: 'Term', value: 'payment_terms', minWidth: '10px' },
  { text: 'Contact', value: 'supplier_contact', minWidth: '10px' },
  { text: 'Category', value: 'main_category', minWidth: '120px' },
  { text: 'Sub Category', value: 'sub_category', minWidth: '120px' },
  { text: 'Transaction Type', value: 'transaction_type', minWidth: '140px' },
  { text: 'Remarks', value: 'remarks', minWidth: '200px' },
];

const datatableFetchUrl = '/api/inventory/stock-in/items'
const datatableActions = []
const datatableOptions = { autoWidth: false, responsive: false, pageLength: 10 }
const datatableHandlers = {}

// --- Route to Stock In List ---
const goToStockInList = () => {
  window.location.href = '/inventory/stock-ins'
}

// --- Fetch warehouses ---
const fetchWarehouses = async () => {
  try {
    const res = await axios.get('/api/inventory/stock-ins/get-warehouses')
    const warehouses = res.data.map(w => ({ id: w.id, text: w.text }))
    destroySelect2(warehouseSelect.value)
    initSelect2(warehouseSelect.value, {
      placeholder: 'Filter by Warehouse',
      width: '220px',
      allowClear: true,
      data: warehouses,
    }, (value) => {
      selectedWarehouses.value = value.map(Number)
    })
  } catch (error) {
    console.error('Failed to fetch warehouses:', error)
  }
}

// --- Initialize SmartAdmin datepickers ---
const initDatepickers = () => {
  nextTick(() => {
    if (window.$ && startDateRef.value) {
      window.$(startDateRef.value).datepicker({ format: 'yyyy-mm-dd', autoclose: true, clearBtn: true })
        .on('changeDate', function(e) { datatableParams.start_date = e.format(0, 'yyyy-mm-dd') })
    }
    if (window.$ && endDateRef.value) {
      window.$(endDateRef.value).datepicker({ format: 'yyyy-mm-dd', autoclose: true, clearBtn: true })
        .on('changeDate', function(e) { datatableParams.end_date = e.format(0, 'yyyy-mm-dd') })
    }
  })
}

// --- Apply filters ---
const applyFilters = () => {
  datatableParams.warehouse_ids = selectedWarehouses.value
  datatableRef.value.reload()
}

// --- Datatable event handlers ---
const handleSortChange = ({ column, direction }) => { datatableParams.sortColumn = column; datatableParams.sortDirection = direction }
const handlePageChange = (page) => { datatableParams.page = page }
const handleLengthChange = (length) => { datatableParams.limit = length }
const handleSearchChange = (search) => { datatableParams.search = search }

// --- Mounted ---
onMounted(() => {
  fetchWarehouses()
  initDatepickers()
})
</script>

