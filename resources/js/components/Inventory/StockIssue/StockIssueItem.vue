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

          <!-- Top Row Buttons -->
          <div class="d-flex mb-2 align-items-center">
            <button class="btn btn-success mr-2" @click="goToStockIssueList">
              <i class="fal fa-list mr-1"></i> Stock Issue List
            </button>

            <button 
              class="btn btn-info"
              type="button"
              data-toggle="collapse"
              data-target="#filterCollapse"
              aria-expanded="false"
              aria-controls="filterCollapse"
            >
              <i class="fal fa-filter mr-2"></i> Filter + Export
            </button>
          </div>

          <!-- COLLAPSIBLE FILTER SECTION -->
          <div class="collapse" id="filterCollapse">
            <div class="card card-body shadow-sm">
              
              <!-- Row 1: Date Range + Warehouse -->
              <div class="row g-2 mb-3">
                <div class="col-md-3">
                  <input type="text" ref="startDateRef" class="form-control" placeholder="Start Date" />
                </div>
                <div class="col-md-3">
                  <input type="text" ref="endDateRef" class="form-control" placeholder="End Date" />
                </div>
                <div class="col-md-6">
                  <select ref="warehouseSelect" class="form-select" multiple></select>
                </div>
              </div>

              <!-- Row 2: Campus + Department + Transaction Type Multi-Select -->
              <div class="row g-2 mb-3">
                <div class="col-md-4">
                  <select ref="campusSelect" class="form-select" multiple></select>
                </div>
                <div class="col-md-4">
                  <select ref="departmentSelect" class="form-select" multiple></select>
                </div>
                <div class="col-md-4">
                  <select ref="transactionTypeSelectRef" class="form-select" multiple></select>
                </div>
              </div>

              <!-- Row 3: Apply + Export -->
              <div class="d-flex justify-content-end gap-2">
                <button class="btn btn-primary d-flex align-items-center" @click="applyFilters">
                  <i class="fal fa-filter mr-2"></i> Apply
                </button>
                <button class="btn btn-success d-flex align-items-center" @click="exportData">
                  <i class="fal fa-file-excel mr-2"></i> Export
                </button>
              </div>

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
import { showAlert } from '@/Utils/bootbox'

const datatableRef = ref(null)
const warehouseSelect = ref(null)
const campusSelect = ref(null)
const departmentSelect = ref(null)
const transactionTypeSelectRef = ref(null)
const startDateRef = ref(null)
const endDateRef = ref(null)

const selectedWarehouses = ref([])
const selectedCampuses = ref([])
const selectedDepartments = ref([])
const selectedTransactionTypes = ref([])

const datatableParams = reactive({
  sortColumn: 'id',
  sortDirection: 'desc',
  search: '',
  warehouse_ids: [],
  campus_ids: [],
  department_ids: [],
  start_date: null,
  end_date: null,
})

const datatableHeaders = [
  { text: 'Date', value: 'transaction_date', sortable: false  },
  { text: 'Issue No', value: 'stock_issue_reference', sortable: false },
  { text: 'Warehouse', value: 'warehouse_name', sortable: false },
  { text: 'Product Code', value: 'product_code' },
  { text: 'Description', value: 'description', sortable: false },
  { text: 'Quantity', value: 'quantity', sortable: false },
  { text: 'Unit', value: 'unit_name', sortable: false },
  { text: 'Unit Price', value: 'unit_price', sortable: false  },
  { text: 'Total Price', value: 'total_price', sortable: false  },
  { text: 'Requester', value: 'requester_name', sortable: false  },
  { text: 'Campus', value: 'campus_name', sortable: false  },
  { text: 'Division', value: 'division_name', sortable: false  },
  { text: 'Department', value: 'department_name', sortable: false  },
  { text: 'Purpose', value: 'purpose', sortable: false  },
  { text: 'Transaction Type', value: 'transaction_type', sortable: false  },
  { text: 'Remarks', value: 'remarks', sortable: false  },
]

const datatableFetchUrl = '/api/inventory/stock-issue/items'
const datatableActions = []
const datatableOptions = { autoWidth: false, responsive: true, pageLength: 10 }
const datatableHandlers = {}

const goToStockIssueList = () => {
  window.location.href = '/inventory/stock-issues'
}

// -------------------- FETCH WAREHOUSES --------------------
const fetchWarehouses = async () => {
  try {
    const res = await axios.get('/api/inventory/stock-issues/get-warehouses')
    const warehouses = res.data.map(w => ({ id: w.id, text: w.text }))

    destroySelect2(warehouseSelect.value)
    initSelect2(warehouseSelect.value, {
      placeholder: 'Warehouse',
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

// -------------------- FETCH CAMPUS --------------------
const fetchCampuses = async () => {
  try {
    const res = await axios.get('/api/main-value-lists/get-campuses')
    const campuses = res.data.map(c => ({ id: c.id, text: c.text }))

    destroySelect2(campusSelect.value)
    initSelect2(campusSelect.value, {
      placeholder: 'Campus',
      width: '220px',
      allowClear: true,
      data: campuses,
    }, (value) => {
      selectedCampuses.value = value.map(Number)
    })
  } catch (error) {
    console.error('Failed to fetch campuses:', error)
  }
}

// -------------------- FETCH DEPARTMENTS --------------------
const fetchDepartments = async () => {
  try {
    const res = await axios.get('/api/main-value-lists/get-departments')
    const depts = res.data.map(d => ({ id: d.id, text: d.text }))

    destroySelect2(departmentSelect.value)
    initSelect2(departmentSelect.value, {
      placeholder: 'Department',
      width: '220px',
      allowClear: true,
      data: depts,
    }, (value) => {
      selectedDepartments.value = value.map(Number)
    })
  } catch (error) {
    console.error('Failed to fetch departments:', error)
  }
}

// -------------------- DATE PICKERS --------------------
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

// -------------------- INIT TRANSACTION TYPE SELECT --------------------
const initTransactionTypeSelect = () => {
  nextTick(() => {
    if (transactionTypeSelectRef.value) {
      destroySelect2(transactionTypeSelectRef.value)

      initSelect2(transactionTypeSelectRef.value, {
        placeholder: 'Tran. Type',
        width: '220px',
        allowClear: true,
        multiple: true, // multi-select
        data: [
          { id: 'Issue', text: 'Issue' },
          { id: 'Transfer', text: 'Transfer' }
        ],
      }, (value) => {
        // Always store as array
        selectedTransactionTypes.value = Array.isArray(value) ? value : (value ? [value] : [])
      })
    }
  })
}

// -------------------- APPLY FILTERS --------------------
const applyFilters = () => {
  datatableParams.warehouse_ids = selectedWarehouses.value
  datatableParams.campus_ids = selectedCampuses.value
  datatableParams.department_ids = selectedDepartments.value
  datatableParams.transaction_type = selectedTransactionTypes.value
  datatableRef.value.reload()
}

// -------------------- EXPORT --------------------
const exportData = async () => {
  // Get the current filter values directly from Select2 and date pickers
  const currentWarehouses = selectedWarehouses.value
  const currentCampuses = selectedCampuses.value
  const currentDepartments = selectedDepartments.value
  const startDate = startDateRef.value ? window.$(startDateRef.value).val() : null
  const endDate = endDateRef.value ? window.$(endDateRef.value).val() : null

  const payload = {
    sortColumn: datatableParams.sortColumn,
    sortDirection: datatableParams.sortDirection,
    search: datatableParams.search || '',
    start_date: startDate || null,
    end_date: endDate || null,
    warehouse_ids: currentWarehouses,
    campus_ids: currentCampuses,
    department_ids: currentDepartments,
    transaction_type: selectedTransactionTypes.value,
  }
  if(!payload.start_date && !payload.end_date) {
    showAlert('Error', 'Please select a date range to export.', 'warning')
    return
  }
  try {
    const res = await axios.post('/api/inventory/stock-issues/export-items', payload, {
      responseType: 'blob', // important to handle file download
    })

    // Trigger download
    const url = window.URL.createObjectURL(new Blob([res.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', 'stock_issue_items.xlsx')
    document.body.appendChild(link)
    link.click()
    link.remove()
  } catch (error) {
    console.error('Export failed:', error)
  }
}


// -------------------- DATATABLE EVENTS --------------------
const handleSortChange = ({ column, direction }) => {
  datatableParams.sortColumn = column
  datatableParams.sortDirection = direction
}

const handlePageChange = (page) => { datatableParams.page = page }
const handleLengthChange = (length) => { datatableParams.limit = length }
const handleSearchChange = (search) => { datatableParams.search = search }

// -------------------- MOUNTED --------------------
onMounted(() => {
  fetchWarehouses()
  fetchCampuses()
  fetchDepartments()
  initDatepickers()
  initTransactionTypeSelect()
})
</script>
