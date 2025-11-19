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
        <div class="d-flex flex-column mb-2">
          <!-- Top Row: Print Button + Filters Toggle -->
          <div class="d-flex mb-2 align-items-center">
            <button class="btn btn-success mr-2" @click="openPdfViewer" :disabled="loadingPdf">
              <i v-if="loadingPdf" class="fas fa-spinner fa-spin mr-1"></i>
              <i v-else class="fal fa-print mr-1"></i>
              Print Stock Report
            </button>
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
            <div class="d-flex align-items-center mb-2">
              <input type="text" ref="startDateRef" class="form-control mr-2" placeholder="Start Date" />
              <input type="text" ref="endDateRef" class="form-control mr-2" placeholder="End Date" />
              <select ref="warehouseSelect" class="form-control" multiple></select>
            </div>
            <div class="d-flex justify-content-end mb-2">
              <button class="btn btn-primary d-flex align-items-center" @click="applyFilters">
                <i class="fal fa-filter mr-2"></i> Apply
              </button>
            </div>
          </div>
        </div>
      </template>
    </datatable>

    <!-- File Viewer Modal -->
    <FileViewerModal ref="fileViewerModal" />

    <!-- Optional overlay spinner while loading PDF -->
    <div v-if="loadingPdf" class="pdf-loading-overlay">
      <div class="spinner-border text-primary" role="status">
        <span class="sr-only">Loading PDF...</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, nextTick } from 'vue'
import axios from 'axios'
import { initSelect2, destroySelect2 } from '@/Utils/select2.js'
import FileViewerModal from '@/components/Reusable/FileViewerModal.vue'

const datatableRef = ref(null)
const warehouseSelect = ref(null)
const startDateRef = ref(null)
const endDateRef = ref(null)
const selectedWarehouses = ref([])
const fileViewerModal = ref(null)
const loadingPdf = ref(false) // PDF loading state

// --- Datatable config ---
const datatableParams = reactive({
  sortColumn: 'item_code',
  sortDirection: 'asc',
  search: '',
  warehouse_ids: [],
  start_date: null,
  end_date: null,
  limit: 10,
  page: 1,
})

const datatableHeaders = [
  { text: 'Item Code', value: 'item_code' },
  { text: 'Description', value: 'description' },
  { text: 'Unit', value: 'unit_name' },
  { text: 'Begin Qty', value: 'beginning_quantity' },
  { text: 'Begin Amount', value: 'beginning_total' },
  { text: 'Stock In Qty', value: 'stock_in_quantity' },
  { text: 'Stock In Amount', value: 'stock_in_total' },
  { text: 'Stock Out Qty', value: 'stock_out_quantity' },
  { text: 'Stock Out Amount', value: 'stock_out_total' },
  { text: 'Ending Qty', value: 'ending_quantity' },
  { text: 'Average Price', value: 'average_price' },
  { text: 'Ending Amount', value: 'ending_total' },
]

const datatableFetchUrl = '/api/inventory/stock-reports'
const datatableActions = []
const datatableOptions = { autoWidth: false, responsive: true, pageLength: 10 }
const datatableHandlers = {}

// --- Open PDF in FileViewerModal (with preloading) ---
const openPdfViewer = async () => {
  loadingPdf.value = true
  try {
    const res = await axios.post('/inventory/stock-reports/pdf', {
      start_date: datatableParams.start_date,
      end_date: datatableParams.end_date,
      sortColumn: datatableParams.sortColumn,
      sortDirection: datatableParams.sortDirection,
      warehouse_ids: datatableParams.warehouse_ids,
      forPrint: 1,
    });

    const pdfData = res.data.pdf_base64;
    const pdfBlob = new Blob([Uint8Array.from(atob(pdfData), c => c.charCodeAt(0))], { type: 'application/pdf' });

    if (fileViewerModal.value) {
      fileViewerModal.value.openBlob(pdfBlob, res.data.filename);
    }
  } catch (error) {
    console.error(error)
    alert('Failed to generate PDF');
  } finally {
    loadingPdf.value = false
  }
};

// --- Fetch warehouses ---
const fetchWarehouses = async () => {
  try {
    const res = await axios.get('/api/inventory/stock-reports/get-warehouses')
    const warehouses = res.data.map(w => ({ id: w.id, text: w.text }))
    destroySelect2(warehouseSelect.value)
    initSelect2(
      warehouseSelect.value,
      { placeholder: 'Filter by WH', width: '220px', allowClear: true, data: warehouses },
      (value) => { selectedWarehouses.value = value.map(Number) }
    )
  } catch (error) {
    console.error(error)
  }
}

// --- Initialize datepickers ---
const initDatepickers = () => {
  nextTick(() => {
    if (window.$ && startDateRef.value) {
      window.$(startDateRef.value)
        .datepicker({ format: 'yyyy-mm-dd', autoclose: true, clearBtn: true })
        .on('changeDate', e => { datatableParams.start_date = e.format(0, 'yyyy-mm-dd') })
    }
    if (window.$ && endDateRef.value) {
      window.$(endDateRef.value)
        .datepicker({ format: 'yyyy-mm-dd', autoclose: true, clearBtn: true })
        .on('changeDate', e => { datatableParams.end_date = e.format(0, 'yyyy-mm-dd') })
    }
  })
}

// --- Apply filters ---
const applyFilters = () => {
  datatableParams.warehouse_ids = selectedWarehouses.value
  datatableRef.value.reload()
}

// --- Datatable events ---
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

<style scoped>
.pdf-loading-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(255,255,255,0.6);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 2000;
}
</style>
