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
        <div class="btn-group" role="group">
          <!-- Optional: Add create/export buttons if needed -->
          <!--
          <button class="btn btn-success" @click="createApproval">
            <i class="fal fa-plus"></i> Create Approval
          </button>
          -->
        </div>
      </template>
    </datatable>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'

// Refs and state
const datatableRef = ref(null)
const pageLength = ref(10)

const datatableParams = reactive({
  sortColumn: 'created_at',
  sortDirection: 'desc',
})

// Datatable headers for Approval list
const datatableHeaders = [
{ text: 'Requested Date', value: 'created_at', width: '8%' },
{ text: 'Docs Name', value: 'document_name', width: '10%' },
{ text: 'Docs Ref.', value: 'document_reference', width: '10%' },
{ text: 'Requester', value: 'requester_name', width: '11%' },
{ text: 'Position', value: 'requester_position', width: '11%' },
{ text: 'Department', value: 'requester_department', width: '13%' },
{ text: 'Responder', value: 'responder_name', width: '9%' },
{ text: 'Type', value: 'request_type', width: '7%' },
{ text: 'Status', value: 'approval_status', width: '9%' },
{ text: 'Responded Date', value: 'responded_date', width: '12%' },
]

// API route for approvals
const datatableFetchUrl = '/api/approvals'

// Action buttons to show
const datatableActions = ['preview'] // You can add 'delete' or 'edit' if needed

const datatableOptions = {
  responsive: true,
  pageLength: pageLength.value,
  lengthMenu: [[10, 20, 50, 100], [10, 20, 50, 100]],
}

// Preview handler with dynamic routing based on approvable_type
const handlePreview = (approval) => {
  const typeRouteMap = {
    'App\\Models\\MainStockBeginning': 'approvals/stock-beginnings',
    'App\\Models\\StockRequest': 'approvals/stock-requests',
    'App\\Models\\StockTransfer': 'approvals/stock-transfers',
    'App\\Models\\DigitalDocsApproval': 'approvals/digital-docs-approvals',
    'App\\Models\\PurchaseRequest': 'approvals/purchase-requests',
  }

  const routePrefix = typeRouteMap[approval.approvable_type]

  if (routePrefix) {
    window.location.href = `/${routePrefix}/${approval.approvable_id}/show`
  } else {
    alert('No route defined for this approval type.')
  }
}

// Datatable event handlers
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

// Map actions to handlers
const datatableHandlers = {
  preview: handlePreview,
}
</script>
