<template>
  <div>
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
          <!-- Top Row: Print + Filters -->
          <div class="d-flex mb-2 align-items-center">
            <button class="btn btn-success mr-2" :disabled="isGeneratingPdf" @click="openPdfViewer">
              <i class="fal fa-print mr-1" v-if="!isGeneratingPdf"></i>
              <span v-if="!isGeneratingPdf">Print Stock Report</span>
              <span v-else class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
              <span v-else class="ml-1">Generating...</span>
            </button>
            <button class="btn btn-info" type="button" data-toggle="collapse" data-target="#filterCollapse">
              <i class="fal fa-filter mr-2"></i> Filters
            </button>
          </div>

          <!-- Filter Section -->
          <div class="collapse" id="filterCollapse">
            <div class="d-flex align-items-center mb-2">
              <input type="text" ref="startDateRef" class="form-control mr-2" placeholder="Start Date" />
              <input type="text" ref="endDateRef" class="form-control mr-2" placeholder="End Date" />
              <select ref="warehouseSelect" class="form-control" multiple></select>
            </div>
            <div class="d-flex justify-content-end">
              <button class="btn btn-primary" @click="applyFilters">
                <i class="fal fa-filter mr-2"></i> Apply
              </button>
            </div>
          </div>
        </div>
      </template>
    </datatable>

    <!-- Fullscreen PDF Modal -->
    <div class="modal fade modal-fullscreen modal-backdrop-transparent" id="pdfModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Stock Report PDF</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body p-0">
            <iframe
              v-if="pdfUrl"
              :src="pdfUrl"
              style="width: 100%; height: 100vh;"
              frameborder="0"
            ></iframe>
            <div v-else class="text-center p-5">Loading PDF...</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'
import { initSelect2, destroySelect2 } from '@/Utils/select2.js'

// --- Refs ---
const datatableRef = ref(null)
const warehouseSelect = ref(null)
const startDateRef = ref(null)
const endDateRef = ref(null)
const selectedWarehouses = ref([])
const pdfUrl = ref(null)
const isGeneratingPdf = ref(false)

// --- Datatable reactive params ---
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

// --- Datatable config ---
const datatableHeaders = [
  { text: 'Item Code', value: 'item_code', minWidth: '120px' },
  { text: 'Description', value: 'description', minWidth: '300px', sortable: false },
  { text: 'Unit', value: 'unit_name', minWidth: '60px', sortable: false },
  { text: 'Begin Qty', value: 'beginning_quantity', minWidth: '100px', sortable: false, format: 'number' },
  { text: 'Begin Price', value: 'beginning_price', minWidth: '120px', sortable: false, format: 'number' },
  { text: 'Begin Amount', value: 'beginning_total', minWidth: '120px', sortable: false, format: 'number' },
  { text: 'Stock In Qty', value: 'stock_in_quantity', minWidth: '100px', sortable: false, format: 'number' },
  { text: 'Stock In Amount', value: 'stock_in_total', minWidth: '120px', sortable: false, format: 'number' },
  { text: 'Available Qty', value: 'available_quantity', minWidth: '100px', sortable: false, format: 'number' },
  { text: 'Available Price', value: 'available_price', minWidth: '120px', sortable: false, format: 'number' },
  { text: 'Available Amount', value: 'available_total', minWidth: '120px', sortable: false, format: 'number' },
  { text: 'Stock Out Qty', value: 'stock_out_quantity', minWidth: '100px', sortable: false, format: 'number' },
  { text: 'Stock Out Amount', value: 'stock_out_total', minWidth: '120px', sortable: false, format: 'number' },
  { text: 'Ending Qty', value: 'ending_quantity', minWidth: '100px', sortable: false, format: 'number' },
  { text: 'Count Qty', value: 'counted_quantity', minWidth: '100px', sortable: false, format: 'number' },
  { text: 'Variance', value: 'variance_quantity', minWidth: '80px', sortable: false, format: 'number' },
  { text: 'Carried Qty', value: 'counted_quantity', minWidth: '100px', sortable: false, format: 'number' },
  { text: 'Avg Price', value: 'average_price', minWidth: '120px', sortable: false, format: 'number' },
  { text: 'Ending Amount', value: 'ending_total', minWidth: '120px', sortable: false, format: 'number' },
]

const datatableFetchUrl = '/api/inventory/stock-reports'
const datatableOptions = { 
  autoWidth: false,
  responsive: false, 
  pageLength: 10 }

// --- PDF export ---
const openPdfViewer = async () => {
  try {
    isGeneratingPdf.value = true
    pdfUrl.value = null

    const res = await axios.post('/inventory/stock-reports/pdf', datatableParams, {
      responseType: 'blob',
      headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') }
    })

    const blob = new Blob([res.data], { type: 'application/pdf' })
    pdfUrl.value = URL.createObjectURL(blob)

    $('#pdfModal').modal('show')
  } catch (err) {
    console.error(err)
    alert('Failed to generate PDF')
  } finally {
    isGeneratingPdf.value = false
  }
}

// --- Filters ---
const fetchWarehouses = async () => {
  try {
    const res = await axios.get('/api/main-value-lists/get-warehouses')
    const warehouses = res.data.map(w => ({ id: w.id, text: w.text }))
    destroySelect2(warehouseSelect.value)
    initSelect2(
      warehouseSelect.value,
      { placeholder: 'Filter by WH', width: '220px', allowClear: true, data: warehouses },
      value => { selectedWarehouses.value = value.map(Number) }
    )
  } catch (err) { console.error(err) }
}

const initDatepickers = () => {
  if (!window.$) return
  $(startDateRef.value).datepicker({ format: 'yyyy-mm-dd', autoclose: true, clearBtn: true })
    .on('changeDate', e => { datatableParams.start_date = e.format(0, 'yyyy-mm-dd') })
  $(endDateRef.value).datepicker({ format: 'yyyy-mm-dd', autoclose: true, clearBtn: true })
    .on('changeDate', e => { datatableParams.end_date = e.format(0, 'yyyy-mm-dd') })
}

const applyFilters = () => {
  datatableParams.warehouse_ids = selectedWarehouses.value
  datatableRef.value.reload()
}

// --- Datatable events ---
const handleSortChange = ({ column, direction }) => { datatableParams.sortColumn = column; datatableParams.sortDirection = direction }
const handlePageChange = (page) => { datatableParams.page = page }
const handleLengthChange = (length) => { datatableParams.limit = length }
const handleSearchChange = (search) => { datatableParams.search = search }

// --- On mounted ---
onMounted(() => {
  fetchWarehouses()
  initDatepickers()
  $('#pdfModal').on('hidden.bs.modal', () => {
    if (pdfUrl.value) { URL.revokeObjectURL(pdfUrl.value); pdfUrl.value = null }
  })
})
</script>
