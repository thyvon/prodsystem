<template>
  <div>
    <!-- Datatable -->
    <datatable
      ref="datatableRef"
      :headers="datatableHeaders"
      :fetch-url="datatableFetchUrl"
      :fetch-params="datatableParams"
      :options="datatableOptions"
      :scrollable="true"
      @sort-change="handleSortChange"
      @page-change="handlePageChange"
      @length-change="handleLengthChange"
      @search-change="handleSearchChange"
    >
      <!-- Additional Header -->
      <template #additional-header>
        <div class="d-flex flex-column mb-2">
          <!-- Top Row: Print + Filter Button -->
          <div class="d-flex mb-2 align-items-center">
            <button class="btn btn-info" type="button" data-toggle="collapse" data-target="#filterCollapse">
              <i class="fal fa-filter mr-2"></i> Filters & Export
            </button>
          </div>

          <!-- Filter Section -->
          <div class="collapse" id="filterCollapse">
            <div class="d-flex align-items-center mb-2">
              <input type="text" ref="startDateRef" class="form-control mr-2" placeholder="Start Date" />
              <input type="text" ref="endDateRef" class="form-control mr-2" placeholder="End Date" />
              <!-- <select ref="warehouseSelect" class="form-control" multiple></select> -->
            </div>
            <div class="d-flex justify-content-end">
              <button class="btn btn-primary" @click="applyFilters">
                <i class="fal fa-filter mr-2"></i> Apply
              </button>
              <button class="btn btn-success mr-2" @click="exportReport">
                <i class="fal fa-file-export mr-1"></i> Export Stock Report
              </button>
            </div>
          </div>
        </div>
      </template>
    </datatable>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'
import { initSelect2, destroySelect2 } from '@/Utils/select2'

// ---------------- REFS ----------------
const datatableRef = ref(null)
const warehouseSelect = ref(null)
const startDateRef = ref(null)
const endDateRef = ref(null)
const selectedWarehouses = ref([])

// ---------------- DATATABLE PARAMS ----------------
const datatableParams = reactive({
  search: '',
  warehouse_ids: [],
  cutoff_date: null,
  limit: 15,
  page: 1,
  sortColumn: 'item_code',
  sortDirection: 'asc'
})

// ---------------- STATIC DATATABLE HEADERS ----------------
const datatableHeaders = ref([
  { text: 'Item Code', value: 'item_code', minWidth: '120px' },
  { text: 'Product', value: 'description', minWidth: '300px', sortable: false },
  { text: 'Unit', value: 'unit', minWidth: '60px', sortable: false },

  // Warehouse columns
  { text: 'PROD CEN WH', value: 'prod_cen_wh', minWidth: '120px', sortable: false, format: 'number' },
  { text: 'PROD TK WH', value: 'prod_tk_wh', minWidth: '120px', sortable: false, format: 'number' },
  { text: 'PROD CCV WH', value: 'prod_ccv_wh', minWidth: '120px', sortable: false, format: 'number' },
  { text: 'PROD CA WH', value: 'prod_ca_wh', minWidth: '120px', sortable: false, format: 'number' },
  { text: 'PROD CAP WH', value: 'prod_cap_wh', minWidth: '120px', sortable: false, format: 'number' },
  { text: 'PROD CKD WH', value: 'prod_ckd_wh', minWidth: '120px', sortable: false, format: 'number' },
  { text: 'PROD SS WH', value: 'prod_ss_wh', minWidth: '120px', sortable: false, format: 'number' },
  { text: 'PROD SR WH', value: 'prod_sr_wh', minWidth: '120px', sortable: false, format: 'number' },
  { text: 'PROD TAK WH', value: 'prod_tak_wh', minWidth: '120px', sortable: false, format: 'number' },

  // Total column
  { text: 'Total', value: 'total', minWidth: '120px', sortable: false, format: 'number' },
])


// ---------------- DATATABLE CONFIG ----------------
const datatableFetchUrl = '/api/inventory/stock-reports/stock-onhand-by-warehouse'
const datatableOptions = { autoWidth: false, responsive: false, pageLength: 15 }

// ---------------- DATATABLE EVENTS ----------------
const handleSortChange = ({ column, direction }) => {
  datatableParams.sortColumn = column
  datatableParams.sortDirection = direction
}
const handlePageChange = (page) => { datatableParams.page = page }
const handleLengthChange = (length) => { datatableParams.limit = length }
const handleSearchChange = (search) => { datatableParams.search = search }

// ---------------- FILTERS ----------------
const fetchWarehouses = async () => {
  try {
    const res = await axios.get('/api/main-value-lists/get-warehouses')
    const warehouses = res.data.map(w => ({ id: w.id, text: w.name || w.text }))

    destroySelect2(warehouseSelect.value)
    initSelect2(
      warehouseSelect.value,
      { placeholder: 'Filter by WH', width: '220px', data: warehouses, allowClear: true },
      value => { selectedWarehouses.value = value.map(Number) }
    )
  } catch (err) {
    console.error(err)
  }
}

const initDatepickers = () => {
  if (!window.$) return
  $(startDateRef.value).datepicker({ format: 'yyyy-mm-dd', autoclose: true })
    .on('changeDate', e => { datatableParams.cutoff_date = e.format(0, 'yyyy-mm-dd') })
  $(endDateRef.value).datepicker({ format: 'yyyy-mm-dd', autoclose: true })
    .on('changeDate', e => { /* optional end date */ })
}

const applyFilters = () => {
  datatableParams.warehouse_ids = selectedWarehouses.value.length ? selectedWarehouses.value : null
  datatableRef.value.reload()
}

// ---------------- EXPORT ----------------
// ---------------- EXPORT ----------------
const exportReport = async () => {
  try {
    const params = {
      search: datatableParams.search,
      warehouse_ids: datatableParams.warehouse_ids,
      cutoff_date: datatableParams.cutoff_date,
      sortColumn: datatableParams.sortColumn,
      sortDirection: datatableParams.sortDirection
    }

    const res = await axios.get('/inventory/stock-reports/stock-onhand-by-warehouse/export', {
      params,
      responseType: 'blob' // important for file download
    })

    // Create blob and trigger download
    const blob = new Blob([res.data], { type: res.headers['content-type'] })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', 'warehouse_stock_report.xlsx')
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)

  } catch (err) {
    console.error('Export failed:', err)
  }
}



// ---------------- ON MOUNT ----------------
onMounted(() => {
  fetchWarehouses()
  initDatepickers()
})
</script>
