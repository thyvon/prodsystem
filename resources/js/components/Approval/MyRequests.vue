<template>
  <div>
    <datatable
      ref="datatableRef"
      :headers="datatableHeaders"
      :rows="datatableRows"
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
      @row-click="previewRow"
    >
      <!-- Additional Header Cards -->
      <template #additional-header>
        <div class="row g-3">
          <!-- All my approvals -->
          <div class="col-sm-6 col-xl-2" @click="filterApprovals('all')" style="cursor: pointer">
            <div
              class="filter-card p-3 bg-primary-300 rounded position-relative mb-g text-white"
              :class="{ active: datatableParams.filterType === 'all' }"
            >
              <h3 class="display-4 d-block l-h-n m-0 fw-500">
                {{ statusCounts.all }}
                <small class="m-0 l-h-n d-block">All documents</small>
              </h3>
              <i class="fal fa-tasks position-absolute pos-right pos-bottom opacity-25" style="font-size:4rem;"></i>
            </div>
          </div>

          <!-- Pending approvals -->
          <div class="col-sm-6 col-xl-2" @click="filterApprovals('pending')" style="cursor: pointer">
            <div
              class="filter-card p-3 bg-warning-400 rounded position-relative mb-g text-white"
              :class="{ active: datatableParams.filterType === 'pending' }"
            >
              <h3 class="display-4 d-block l-h-n m-0 fw-500">
                {{ statusCounts.pending }}
                <small class="m-0 l-h-n d-block">Pending approvals</small>
              </h3>
              <i class="fal fa-hourglass-half position-absolute pos-right pos-bottom opacity-25" style="font-size:4rem;"></i>
            </div>
          </div>

          <!-- Completed approvals -->
          <div class="col-sm-6 col-xl-2" @click="filterApprovals('completed')" style="cursor: pointer">
            <div
              class="filter-card p-3 bg-success-200 rounded position-relative mb-g text-white"
              :class="{ active: datatableParams.filterType === 'completed' }"
            >
              <h3 class="display-4 d-block l-h-n m-0 fw-500">
                {{ statusCounts.completed }}
                <small class="m-0 l-h-n d-block">Completed approvals</small>
              </h3>
              <i class="fal fa-check-circle position-absolute pos-right pos-bottom opacity-25" style="font-size:4rem;"></i>
            </div>
          </div>

          <!-- Upcoming approvals -->
          <div class="col-sm-6 col-xl-2" @click="filterApprovals('upcoming')" style="cursor: pointer">
            <div
              class="filter-card p-3 bg-info-200 rounded position-relative mb-g text-white"
              :class="{ active: datatableParams.filterType === 'upcoming' }"
            >
              <h3 class="display-4 d-block l-h-n m-0 fw-500">
                {{ statusCounts.upcoming }}
                <small class="m-0 l-h-n d-block">My upcoming approvals</small>
              </h3>
              <i class="fal fa-clock position-absolute pos-right pos-bottom opacity-25" style="font-size:4rem;"></i>
            </div>
          </div>

          <!-- Returned approvals -->
          <div class="col-sm-6 col-xl-2" @click="filterApprovals('returned')" style="cursor: pointer">
            <div
              class="filter-card p-3 bg-warning-200 rounded position-relative mb-g text-white border border-dark"
              :class="{ active: datatableParams.filterType === 'returned' }"
            >
              <h3 class="display-4 d-block l-h-n m-0 fw-500">
                {{ statusCounts.returned }}
                <small class="m-0 l-h-n d-block">My returned Docs</small>
              </h3>
              <i class="fal fa-undo position-absolute pos-right pos-bottom opacity-25" style="font-size:4rem;"></i>
            </div>
          </div>

          <!-- Rejected approvals -->
          <div class="col-sm-6 col-xl-2" @click="filterApprovals('rejected')" style="cursor: pointer">
            <div
              class="filter-card p-3 bg-danger-200 rounded position-relative mb-g text-white border border-dark"
              :class="{ active: datatableParams.filterType === 'rejected' }"
            >
              <h3 class="display-4 d-block l-h-n m-0 fw-500">
                {{ statusCounts.rejected }}
                <small class="m-0 l-h-n d-block">My rejected Docs</small>
              </h3>
              <i class="fal fa-ban position-absolute pos-right pos-bottom opacity-25" style="font-size:4rem;"></i>
            </div>
          </div>
        </div>
      </template>
    </datatable>
  </div>
</template>

<script setup>
import { ref, reactive, nextTick, onMounted } from 'vue'
import axios from 'axios'

const datatableRef = ref(null)
const datatableRows = ref([])
const pageLength = ref(10)

// -----------------------
// DataTable reactive params
// -----------------------
const datatableParams = reactive({
  sortColumn: 'created_at', // group document field
  sortDirection: 'desc',
  filterType: 'all',
  page: 1,
  limit: pageLength.value,
  search: '',
})

// -----------------------
// Filter card counts
// -----------------------
const statusCounts = reactive({
  all: 0,
  pending: 0,
  completed: 0,
  upcoming: 0,
  returned: 0,
  rejected: 0,
})

// -----------------------
// Table headers (match backend grouped data)
// -----------------------
const datatableHeaders = [
  { text: 'Requested Date', value: 'created_at', width: '10%', minWidth: '120px' },
  { text: 'Docs Name', value: 'document_name', width: '20%', minWidth: '200px' },
  { text: 'Docs Ref.', value: 'document_reference', width: '15%', minWidth: '150px' },
  { text: 'Status', value: 'approval_status', width: '10%', minWidth: '120px' },
  { text: 'Last Responded', value: 'responded_date', width: '15%', minWidth: '150px' },
  { text: 'Approvals', value: 'approvals', sortable: false, minWidth: '200px' },
]

// -----------------------
// Backend URL & Actions
// -----------------------
const datatableFetchUrl = '/api/approvals/my-requests'
const datatableActions = ['preview']

const datatableOptions = {
  autoWidth: false,
  responsive: false,
  pageLength: pageLength.value,
  lengthMenu: [[10, 20, 50, 100], [10, 20, 50, 100]],
}

const datatableHandlers = {
  preview: (document) => {
    // preview first approval inside the group
    const approval = document.approvals?.[0]
    if (!approval) return

    const typeRouteMap = {
      'App\\Models\\MainStockBeginning': 'approvals/stock-beginnings',
      'App\\Models\\StockRequest': 'approvals/stock-requests',
      'App\\Models\\StockTransfer': 'approvals/stock-transfers',
      'App\\Models\\DigitalDocsApproval': 'approvals/digital-docs-approvals',
      'App\\Models\\PurchaseRequest': 'approvals/purchase-requests',
      'App\\Models\\MonthlyStockReport': 'approvals/monthly-stock-reports',
      'App\\Models\\StockCount': 'approvals/stock-counts',
      'App\\Models\\WarehouseProductReport': 'approvals/stock-reports',
    }

    const routePrefix = typeRouteMap[approval.approvable_type]
    if (routePrefix) window.location.href = `/${routePrefix}/${approval.approvable_id}/show`
    else alert('No route defined for this approval type.')
  },
}

// -----------------------
// DataTable events
// -----------------------
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

// -----------------------
// Row click
// -----------------------
const previewRow = (row) => {
  if (datatableHandlers.preview) datatableHandlers.preview(row)
}

// -----------------------
// Filter approvals
// -----------------------
const filterApprovals = async (type) => {
  datatableParams.filterType = type
  datatableParams.page = 1
  await fetchRows() // <-- this updates statusCounts
  await nextTick()
  datatableRef.value?.reload?.()
}

// -----------------------
// Fetch grouped rows from backend
// -----------------------
// Fetch table rows and update status counts
const fetchRows = async () => {
  try {
    const { data } = await axios.get(datatableFetchUrl, { params: datatableParams })

    // Update table rows
    datatableRows.value = data.data || []

    // Update status counts if available
    if (data.statusCounts) {
      Object.assign(statusCounts, data.statusCounts)
    }
  } catch (error) {
    console.error('Error fetching rows:', error)
  }
}

// Fetch rows when component mounts
onMounted(() => {
  fetchRows()
})

</script>

<style>
/* Filter card styles */
.filter-card {
  position: relative;
  z-index: 1;
  cursor: pointer;
  border-radius: 0.5rem;
  overflow: hidden;
  transition: all 0.2s ease;
  padding: 1rem;
}

.filter-card:hover {
  box-shadow: 0 4px 8px rgba(16, 9, 209, 0.1);
}

.filter-card.active {
  border: none;
}

.filter-card.active::before {
  content: '';
  position: absolute;
  top: -2px;
  left: -2px;
  right: -2px;
  bottom: -2px;
  border-radius: 0.5rem;
  padding: 2px;
  background: linear-gradient(270deg, #ff0047, #2c34c7, #00ffe4, #ff0047);
  background-size: 600% 600%;
  z-index: -1;
  animation: borderAnimation 10s linear infinite;
}

@keyframes borderAnimation {
  0% {
    background-position: 0% 50%;
  }
  100% {
    background-position: 100% 50%;
  }
}

.filter-card.active h3,
.filter-card.active small {
  position: relative;
  z-index: 1;
}

.filter-card i {
  transition: transform 0.2s ease, opacity 0.2s ease;
}

.filter-card:hover i {
  transform: scale(1.05);
  opacity: 0.3;
}
</style>
