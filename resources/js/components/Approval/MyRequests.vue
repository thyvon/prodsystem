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
      @row-click="previewRow"
    >
      <!-- Filter Cards -->
      <!-- <template #additional-header>
        <div class="row g-3">

          <div
            v-for="item in filters"
            :key="item.type ?? 'all'"
            class="col-sm-6 col-xl-2"
            style="cursor: pointer"
            @click="filterApprovals(item.type)"
          >
            <div
              class="filter-card p-3 rounded position-relative mb-g text-white"
              :class="[item.bg, { active: datatableParams.status === item.type }]"
            >
              <h3 class="display-4 d-block l-h-n m-0 fw-500">
                <small class="m-0 l-h-n d-block">{{ item.label }}</small>
              </h3>
              <i
                :class="item.icon"
                class="position-absolute pos-right pos-bottom opacity-25"
                style="font-size:4rem;"
              ></i>
            </div>
          </div>

        </div>
      </template> -->
    </datatable>
  </div>
</template>

<script setup>
import { ref, reactive, nextTick } from 'vue'

const datatableRef = ref(null)
const pageLength = ref(10)

/* -----------------------
   DataTable Params
----------------------- */
const datatableParams = reactive({
  sortColumn: 'created_at',
  sortDirection: 'desc',
  page: 1,
  limit: pageLength.value,
  search: '',
  status: null, // pending | approved | rejected | returned | null (all)
})

/* -----------------------
   Filter Cards (Backend Aligned)
----------------------- */
const filters = [
  { type: null, label: 'All documents', bg: 'bg-primary-300', icon: 'fal fa-tasks' },
  { type: 'pending', label: 'Pending approvals', bg: 'bg-warning-400', icon: 'fal fa-hourglass-half' },
  { type: 'approved', label: 'Approved documents', bg: 'bg-success-200', icon: 'fal fa-check-circle' },
  { type: 'returned', label: 'Returned documents', bg: 'bg-warning-200 border border-dark', icon: 'fal fa-undo' },
  { type: 'rejected', label: 'Rejected documents', bg: 'bg-danger-200 border border-dark', icon: 'fal fa-ban' },
]

/* -----------------------
   Table Headers
----------------------- */
const datatableHeaders = [
  { text: 'Submit Date', value: 'created_at', width: '10%', minWidth: '120px' },
  { text: 'Docs Name', value: 'document_name', width: '20%', minWidth: '200px' },
  { text: 'Docs Ref.', value: 'document_reference', width: '15%', minWidth: '150px' },
  { text: 'Status', value: 'approval_status', width: '10%', minWidth: '120px' },
//   { text: 'Last Responded', value: 'responded_date', width: '15%', minWidth: '150px' },
//   { text: 'Responder', value: 'responder_name', width: '15%', minWidth: '150px' },
  { text: 'Approvals', value: 'approvals', sortable: false, minWidth: '300px' },
]

/* -----------------------
   Backend
----------------------- */
const datatableFetchUrl = '/api/approvals/my-requests'
const datatableActions = ['preview']

const datatableOptions = {
  autoWidth: false,
  responsive: false,
  pageLength: pageLength.value,
  lengthMenu: [[10, 20, 50, 100], [10, 20, 50, 100]],
}

/* -----------------------
   Handlers
----------------------- */
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
      'App\\Models\\WarehouseProductReport': 'approvals/stock-reports',
    }
    const routePrefix = typeRouteMap[approval.approvable_type]
    if (routePrefix) window.location.href = `/${routePrefix}/${approval.approvable_id}/show`
    else alert('No route defined for this approval type.')
  },
}

/* -----------------------
   DataTable Events
----------------------- */
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

/* -----------------------
   Row Click
----------------------- */
const previewRow = (row) => {
  datatableHandlers.preview?.(row)
}

/* -----------------------
   Filter Action
----------------------- */
const filterApprovals = async (status) => {
  datatableParams.status = status
  datatableParams.page = 1

  await nextTick()
  datatableRef.value?.reload?.()
}
</script>

<style>
.filter-card {
  position: relative;
  cursor: pointer;
  border-radius: 0.5rem;
  overflow: hidden;
  transition: all 0.2s ease;
}

.filter-card:hover {
  box-shadow: 0 4px 8px rgba(16, 9, 209, 0.1);
}

.filter-card.active::before {
  content: '';
  position: absolute;
  inset: -2px;
  border-radius: 0.5rem;
  background: linear-gradient(270deg, #ff0047, #2c34c7, #00ffe4, #ff0047);
  background-size: 600% 600%;
  z-index: -1;
  animation: borderAnimation 10s linear infinite;
}

@keyframes borderAnimation {
  0% { background-position: 0% 50%; }
  100% { background-position: 100% 50%; }
}
</style>
