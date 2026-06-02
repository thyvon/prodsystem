<template>
  <div style="overflow-x:hidden;">
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
        <div class="modern-header-container mb-4">
          <div class="modern-grid">
            <!-- All my approvals -->
            <div class="modern-card" :class="{ active: datatableParams.filterType === 'all' }" @click="filterApprovals('all')">
              <div class="modern-card-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <i class="fal fa-tasks"></i>
              </div>
              <div class="modern-card-content">
                <h3 class="modern-card-title">All Approvals</h3>
                <p class="modern-card-value">{{ statusCounts.all }}</p>
              </div>
            </div>

            <!-- Pending approvals -->
            <div class="modern-card" :class="{ active: datatableParams.filterType === 'pending' }" @click="filterApprovals('pending')">
              <div class="modern-card-icon" style="background: linear-gradient(135deg, #f6d365 0%, #fda085 100%);">
                <i class="fal fa-hourglass-half"></i>
              </div>
              <div class="modern-card-content">
                <h3 class="modern-card-title">Pending</h3>
                <p class="modern-card-value">{{ statusCounts.pending }}</p>
              </div>
            </div>

            <!-- Completed approvals -->
            <div class="modern-card" :class="{ active: datatableParams.filterType === 'completed' }" @click="filterApprovals('completed')">
              <div class="modern-card-icon" style="background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);">
                <i class="fal fa-check-circle"></i>
              </div>
              <div class="modern-card-content">
                <h3 class="modern-card-title">Completed</h3>
                <p class="modern-card-value">{{ statusCounts.completed }}</p>
              </div>
            </div>

            <!-- Upcoming approvals -->
            <div class="modern-card" :class="{ active: datatableParams.filterType === 'upcoming' }" @click="filterApprovals('upcoming')">
              <div class="modern-card-icon" style="background: linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%);">
                <i class="fal fa-clock"></i>
              </div>
              <div class="modern-card-content">
                <h3 class="modern-card-title">Upcoming</h3>
                <p class="modern-card-value">{{ statusCounts.upcoming }}</p>
              </div>
            </div>
            
            <!-- Returned Docs -->
            <div class="modern-card" :class="{ active: datatableParams.filterType === 'returned' }" @click="filterApprovals('returned')">
              <div class="modern-card-icon" style="background: linear-gradient(135deg, #fccb90 0%, #d57eeb 100%);">
                <i class="fal fa-undo"></i>
              </div>
              <div class="modern-card-content">
                <h3 class="modern-card-title">Returned</h3>
                <p class="modern-card-value">{{ statusCounts.returned }}</p>
              </div>
            </div>

            <!-- Rejected Docs -->
            <div class="modern-card" :class="{ active: datatableParams.filterType === 'rejected' }" @click="filterApprovals('rejected')">
              <div class="modern-card-icon" style="background: linear-gradient(135deg, #ff0844 0%, #ffb199 100%);">
                <i class="fal fa-ban"></i>
              </div>
              <div class="modern-card-content">
                <h3 class="modern-card-title">Rejected</h3>
                <p class="modern-card-value">{{ statusCounts.rejected }}</p>
              </div>
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
const datatableRows = ref([]) // <-- rows for your datatable
const pageLength = ref(10)

const datatableParams = reactive({
  sortColumn: 'created_at',
  sortDirection: 'desc',
  filterType: 'pending',
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
{ text: 'Requested Date', value: 'created_at', minWidth: '150px' },
{ text: 'Docs Name', value: 'document_name', minWidth: '250px' },
{ text: 'Docs Ref.', value: 'document_reference', minWidth: '180px' },
{ text: 'Requester', value: 'requester_name', sortable: false, minWidth: '180px' },
{ text: 'Position', value: 'requester_position', sortable: false, minWidth: '150px' },
{ text: 'Department', value: 'requester_department', sortable: false, minWidth: '150px' },
{ text: 'Action Type', value: 'request_type', minWidth: '140px' },
{ text: 'Responder Name', value: 'responder_name', minWidth: '180px' },
{ text: 'Status', value: 'approval_status', minWidth: '140px' },
{ text: 'Responded Date', value: 'responded_date', minWidth: '180px' },
{ text: 'Comment', value: 'comment', sortable: false, minWidth: '300px' },
]

const datatableFetchUrl = '/api/approvals'
const datatableActions = ['preview']

const datatableOptions = {
  autoWidth: false,
  responsive: false,
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
      'App\\Models\\WarehouseProductReport': 'approvals/stock-reports',
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
// Row click
// -----------------------
const previewRow = (row) => {
  if (datatableHandlers.preview) datatableHandlers.preview(row)
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
// Fetch rows and counts from backend
// -----------------------
const fetchRows = async () => {
  try {
    const { data } = await axios.get(datatableFetchUrl, { params: datatableParams })
    datatableRows.value = data.data || []
    if (data.statusCounts) Object.assign(statusCounts, data.statusCounts)
  } catch (error) {
    console.error(error)
  }
}

// Fetch status counts on mounted
onMounted(() => {
  fetchRows()
})
</script>

<style>
/* Modern Filter Cards */
.modern-header-container {
  padding: 10px 0 20px;
}

.modern-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 16px;
}

.modern-card {
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(10px);
  border-radius: 16px;
  padding: 16px 20px;
  display: flex;
  align-items: center;
  gap: 16px;
  cursor: pointer;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
  border: 1px solid rgba(226, 232, 240, 0.8);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  overflow: hidden;
}

.modern-card:hover {
  transform: translateY(-4px) scale(1.02);
  box-shadow: 0 14px 28px rgba(0, 0, 0, 0.08), 0 10px 10px rgba(0, 0, 0, 0.04);
}

.modern-card.active {
  background: #ffffff;
  border-color: transparent;
  box-shadow: 0 0 0 2px #4f46e5, 0 10px 20px rgba(79, 70, 229, 0.15);
}

.modern-card-icon {
  width: 52px;
  height: 52px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 1.5rem;
  box-shadow: 0 6px 12px rgba(0,0,0,0.15);
  flex-shrink: 0;
  transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.modern-card:hover .modern-card-icon {
  transform: rotate(5deg) scale(1.1);
}

.modern-card-title {
  font-size: 0.85rem;
  color: #64748b;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  margin: 0 0 4px 0;
  transition: color 0.3s ease;
}

.modern-card.active .modern-card-title {
  color: #4f46e5;
}

.modern-card-value {
  font-size: 1.85rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
  line-height: 1;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
</style>
