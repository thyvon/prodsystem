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
      <!-- Additional Header Cards -->
      <template #additional-header>
        <div class="row g-3">
          <!-- All my approvals -->
          <div
            class="col-sm-6 col-xl-3"
            @click="filterApprovals('all')"
            style="cursor: pointer"
          >
            <div
              class="filter-card p-3 bg-primary-300 rounded position-relative mb-g text-white"
              :class="{ active: datatableParams.filterType === 'all' }"
            >
              <h3 class="display-4 d-block l-h-n m-0 fw-500">
                {{ statusCounts.all }}
                <small class="m-0 l-h-n d-block">All my approvals</small>
              </h3>
              <i class="fal fa-tasks position-absolute pos-right pos-bottom opacity-25" style="font-size:4rem;"></i>
            </div>
          </div>

          <!-- Pending approvals -->
          <div
            class="col-sm-6 col-xl-3"
            @click="filterApprovals('pending')"
            style="cursor: pointer"
          >
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
          <div
            class="col-sm-6 col-xl-3"
            @click="filterApprovals('completed')"
            style="cursor: pointer"
          >
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
          <div
            class="col-sm-6 col-xl-3"
            @click="filterApprovals('upcoming')"
            style="cursor: pointer"
          >
            <div
              class="filter-card p-3 bg-info-200 rounded position-relative mb-g text-white"
              :class="{ active: datatableParams.filterType === 'upcoming' }"
            >
              <h3 class="display-4 d-block l-h-n m-0 fw-500">
                {{ statusCounts.upcoming }}
                <small class="m-0 l-h-n d-block">My upcoming approvals</small>
              </h3>
              <!-- Changed icon to clock -->
              <i class="fal fa-clock position-absolute pos-right pos-bottom opacity-25" style="font-size:4rem;"></i>
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
const pageLength = ref(10)

const datatableParams = reactive({
  sortColumn: 'created_at',
  sortDirection: 'desc',
  filterType: 'all',
  page: 1,
  limit: pageLength.value,
  search: '',
})

const statusCounts = reactive({
  all: 0,
  pending: 0,
  completed: 0,
  upcoming: 0,
})

const datatableHeaders = [
  { text: 'Requested Date', value: 'created_at', width: '10%' },
  { text: 'Docs Name', value: 'document_name', width: '20%' },
  { text: 'Docs Ref.', value: 'document_reference', width: '15%' },
  { text: 'Requester', value: 'requester_name', width: '15%',sortable: false },
  { text: 'Position', value: 'requester_position', width: '10%', sortable: false },
  { text: 'Department', value: 'requester_department', width: '10%', sortable: false },
  // { text: 'Responder', value: 'responder_name', width: '10%' },
  { text: 'Request Type', value: 'request_type', width: '5%' },
  { text: 'Status', value: 'approval_status', width: '10%' },
  { text: 'Responded Date', value: 'responded_date', width: '15%' },
]

const datatableFetchUrl = '/api/approvals'
const datatableActions = ['preview']

const datatableOptions = {
  responsive: true,
  pageLength: pageLength.value,
  lengthMenu: [[10, 20, 50, 100], [10, 20, 50, 100]],
}

const datatableHandlers = {
  preview: (approval) => {
    const typeRouteMap = {
      'App\\Models\\MainStockBeginning': 'approvals/stock-beginnings',
      'App\\Models\\StockRequest': 'approvals/stock-requests',
      'App\\Models\\StockTransfer': 'approvals/stock-transfers',
      'App\\Models\\DigitalDocsApproval': 'approvals/digital-docs-approvals',
      'App\\Models\\PurchaseRequest': 'approvals/purchase-requests',
      'App\\Models\\MonthlyStockReport': 'approvals/monthly-stock-reports',
      'App\\Models\\StockCount': 'approvals/stock-counts',
    }
    const routePrefix = typeRouteMap[approval.approvable_type]
    if (routePrefix) window.location.href = `/${routePrefix}/${approval.approvable_id}/show`
    else alert('No route defined for this approval type.')
  },
}

// -----------------------
// Datatable events
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
// Filter function
// -----------------------
const filterApprovals = async (type) => {
  datatableParams.filterType = type
  datatableParams.page = 1
  await nextTick()
  if (datatableRef.value && datatableRef.value.reload) {
    datatableRef.value.reload()
  }
}

// -----------------------
// Fetch counts from backend
// -----------------------
const fetchStatusCounts = async () => {
  try {
    const { data } = await axios.get(datatableFetchUrl, { params: datatableParams })
    if (data.statusCounts) {
      Object.assign(statusCounts, data.statusCounts)
    }
  } catch (error) {
    console.error('Error fetching status counts', error)
  }
}

// Fetch on mounted
onMounted(() => {
  fetchStatusCounts()
})
</script>

<style>
.filter-card {
  transition: all 0.2s ease;
}
.filter-card.active,
.filter-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
}
</style>
